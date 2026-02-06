#!/usr/bin/env python3
# Author: David Edward England, PhD
# ORCID: https://orcid.org/0009-0001-2095-6646
# Repo: https://github.com/DavidEngland/genealogy
"""
Extract persons with unknown WikiTree IDs and generate search links.
Scans markdown files for entries with non-numeric WikiTree ID patterns (e.g., -?, -##, -TBD)
and outputs organized search links to unknowns/ subdirectory.
"""

import os
import re
from pathlib import Path
from urllib.parse import urlencode
from collections import defaultdict

def extract_wikitree_id(text):
    """Extract WikiTree ID from text like [[Hargrove-## | Name]]"""
    match = re.search(r'\[\[([A-Za-z]+-[^\]|]+)', text)
    if match:
        return match.group(1)
    return None

def is_unknown_id(wikitree_id):
    """Check if ID is non-numeric (e.g., -##, -?, -TBD, -unknown)"""
    if not wikitree_id:
        return False
    # Extract the part after the dash
    parts = wikitree_id.split('-', 1)
    if len(parts) != 2:
        return False
    id_part = parts[1]
    # If it's all digits, it's known. Otherwise it's unknown.
    return not id_part.isdigit()

def extract_person_from_section(section_text, source_file):
    """Extract person details from a biography section."""
    person_data = {}
    
    # Extract name and ID from the first line with a link
    id_match = re.search(r'\[\[([A-Za-z]+-[^\]|]+)\s*\|\s*([^\]]+)\]\]', section_text)
    if id_match:
        person_data['wikitree_id'] = id_match.group(1)
        person_data['name'] = id_match.group(2).strip()
    
    # Extract birth info
    birth_match = re.search(r'was born on (.+?) in (.+?)\n', section_text)
    if birth_match:
        person_data['birth_date'] = birth_match.group(1).strip()
        person_data['birth_location'] = birth_match.group(2).strip()
    
    # Extract birth year for search
    birth_year_match = re.search(r'(\d{1,2}\s+\w+\s+)?(\d{4})', person_data.get('birth_date', ''))
    if birth_year_match:
        person_data['birth_year'] = birth_year_match.group(2)
    
    # Extract death info
    death_match = re.search(r'[Hh]e died (?:on\s+)?(.+?)\s+(?:in|at)', section_text)
    if death_match:
        person_data['death_date'] = death_match.group(1).strip()
    
    # Try alternate death format: "died in 1876 in Marion, Oregon"
    if 'death_date' not in person_data:
        death_match2 = re.search(r'[Hh]e died[^,]*?(\d{4})[^,]*?in\s+([^,]+),\s+([^,]+)', section_text)
        if death_match2:
            person_data['death_year'] = death_match2.group(1)
            person_data['death_location'] = f"{death_match2.group(2)}, {death_match2.group(3)}"
    
    # Extract death location if not already found
    if 'death_location' not in person_data:
        death_loc_match = re.search(r'[Hh]e died[^,]*in\s+([^,]+(?:,\s+[^,]+)?),\s+([^.]+)', section_text)
        if death_loc_match:
            person_data['death_location'] = death_loc_match.group(1).strip()
    
    # Extract parents
    parents = []
    parent_matches = re.finditer(r'[Hh]is (?:father|mother),\s+\[\[([^\]|]+)\s*\|\s*([^\]]+)\]\]', section_text)
    for match in parent_matches:
        parents.append({'id': match.group(1), 'name': match.group(2).strip()})
    if parents:
        person_data['parents'] = parents
    
    # Extract spouse info
    spouse_match = re.search(r'[Hh]e married ([^,]+?) (?:in|on)\s+(\d{4})', section_text)
    if spouse_match:
        person_data['spouse_name'] = spouse_match.group(1).strip()
        person_data['marriage_year'] = spouse_match.group(2)
    
    # Extract children
    children = []
    children_section = re.search(r'=== Children ===\n(.*?)(?:== |<ref|$)', section_text, re.DOTALL)
    if children_section:
        child_matches = re.finditer(
            r'#\s+\[\[([^\]|]+)\s*\|\s*([^\]]+)\]\]\s+\((\d{4})(?:–(\d{4}))?\)',
            children_section.group(1)
        )
        for match in child_matches:
            child_id = match.group(1)
            child_name = match.group(2).strip()
            birth_yr = match.group(3)
            death_yr = match.group(4) if match.group(4) else None
            children.append({
                'id': child_id,
                'name': child_name,
                'birth_year': birth_yr,
                'death_year': death_yr
            })
    if children:
        person_data['children'] = children
    
    person_data['source_file'] = source_file
    return person_data if person_data.get('wikitree_id') else None

def scan_directory(directory):
    """Scan all markdown files in directory for unknown WikiTree IDs."""
    unknown_persons = defaultdict(list)
    
    for md_file in Path(directory).glob('*.md'):
        with open(md_file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Split by == Biography == sections
        bio_sections = re.split(r'== Biography ==', content)
        
        for section in bio_sections[1:]:  # Skip first split (content before first biography)
            person = extract_person_from_section(section, md_file.name)
            if person and is_unknown_id(person.get('wikitree_id')):
                # Extract surname for grouping
                surname = person['wikitree_id'].split('-')[0]
                unknown_persons[surname].append(person)
    
    return unknown_persons

def generate_wikitree_search_url(person_data):
    """Generate a WikiTree search URL for a person."""
    params = {}
    
    # Extract surname and given name
    name = person_data.get('name', '')
    
    params['q'] = name
    
    if person_data.get('birth_year'):
        birth_year = int(person_data['birth_year'])
        params['from'] = str(birth_year - 5)
        params['to'] = str(birth_year + 5)
    
    # Build URL
    base_url = "https://www.wikitree.com/search/results.php"
    query_string = urlencode(params)
    return f"{base_url}?{query_string}"

def format_person_entry(person_data):
    """Format a person's data as markdown."""
    output = []
    
    name = person_data.get('name', 'Unknown')
    wikitree_id = person_data.get('wikitree_id', 'Unknown')
    birth_date = person_data.get('birth_date', '')
    birth_year = person_data.get('birth_year', '')
    death_year = person_data.get('death_year', '')
    death_location = person_data.get('death_location', '')
    birth_location = person_data.get('birth_location', '')
    
    # Header with link
    search_url = generate_wikitree_search_url(person_data)
    output.append(f"### [{name}]({search_url})")
    output.append(f"**WikiTree ID:** `{wikitree_id}`")
    
    # Birth/Death info
    birth_str = f"{birth_date}" if birth_date else f"b. {birth_year}" if birth_year else "Birth: Unknown"
    death_str = f"d. {death_year}" if death_year else "Death: Unknown"
    location_str = f"in {death_location}" if death_location else f"in {birth_location}" if birth_location else ""
    
    output.append(f"**Dates:** {birth_str} – {death_str} {location_str}")
    
    # Parents if known
    if person_data.get('parents'):
        parents_str = ", ".join([f"{p['name']} ({p['id']})" for p in person_data['parents']])
        output.append(f"**Parents:** {parents_str}")
    
    # Spouse if known
    if person_data.get('spouse_name'):
        spouse = person_data['spouse_name']
        marriage_year = f" ({person_data.get('marriage_year', '')})" if person_data.get('marriage_year') else ""
        output.append(f"**Spouse:** {spouse}{marriage_year}")
    
    # Children if known
    if person_data.get('children'):
        output.append(f"**Children:** {len(person_data['children'])} children")
        for child in person_data['children'][:5]:  # Show first 5
            child_years = f"({child['birth_year']}–{child['death_year']})" if child.get('death_year') else f"(b. {child['birth_year']})"
            output.append(f"  - {child['name']} {child_years}")
        if len(person_data['children']) > 5:
            output.append(f"  - ... and {len(person_data['children']) - 5} more")
    
    output.append("")  # Blank line between entries
    return "\n".join(output)

def generate_output_files(unknown_persons, output_dir):
    """Generate markdown files in output_dir organized by source file surname."""
    Path(output_dir).mkdir(exist_ok=True)
    
    # Group by surname
    files_by_surname = defaultdict(list)
    for surname, persons in unknown_persons.items():
        for person in persons:
            source_file = person['source_file']
            files_by_surname[source_file].append(person)
    
    # Generate one markdown file per source file
    for source_file, persons in sorted(files_by_surname.items()):
        source_base = Path(source_file).stem
        output_file = Path(output_dir) / f"{source_base}.md"
        
        markdown_content = []
        markdown_content.append(f"# Unknown WikiTree IDs from {source_file}\n")
        markdown_content.append(f"Generated for {len(persons)} person(s) with non-numeric WikiTree IDs\n")
        markdown_content.append("Click on any name to search WikiTree directly.\n")
        markdown_content.append("---\n")
        
        for person in sorted(persons, key=lambda p: p.get('name', '')):
            markdown_content.append(format_person_entry(person))
        
        with open(output_file, 'w', encoding='utf-8') as f:
            f.write("\n".join(markdown_content))
        
        print(f"✓ Created {output_file} ({len(persons)} entries)")

def main():
    """Main execution."""
    genealogy_dir = '/Users/davidengland/Documents/GitHub/genealogy'
    output_dir = os.path.join(genealogy_dir, 'unknowns')
    
    print(f"Scanning {genealogy_dir} for unknown WikiTree IDs...\n")
    
    unknown_persons = scan_directory(genealogy_dir)
    
    if not unknown_persons:
        print("No unknown WikiTree IDs found.")
        return
    
    total_unknown = sum(len(persons) for persons in unknown_persons.values())
    print(f"Found {total_unknown} person(s) with unknown IDs across {len(unknown_persons)} surname(s):\n")
    
    for surname in sorted(unknown_persons.keys()):
        print(f"  {surname}: {len(unknown_persons[surname])} person(s)")
    
    print(f"\nGenerating output files to {output_dir}...\n")
    generate_output_files(unknown_persons, output_dir)
    
    print(f"\nDone! Generated files are ready in {output_dir}")

if __name__ == '__main__':
    main()
