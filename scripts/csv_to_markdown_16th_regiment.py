#!/usr/bin/env python3
"""
Convert 16th Regiment search results to markdown format with WikiTree links.

Takes the CSV output from search_16th_regiment.py and creates a markdown file
formatted for wiki integration, organized by rank.

Usage:
    python3 csv_to_markdown_16th_regiment.py <input_csv> [output_markdown]
"""

import csv
import sys
from pathlib import Path
from collections import defaultdict

def parse_csv(csv_file: str) -> list:
    """Parse search results CSV."""
    results = []
    with open(csv_file, 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            results.append(row)
    return results

def group_by_rank(results: list) -> dict:
    """Group soldiers by rank for organized output."""
    rank_groups = defaultdict(list)
    
    # Define rank order (highest to lowest)
    rank_order = {
        'lieutenant-colonel': 0,
        'second major': 1,
        'captain': 2,
        'adjutant': 3,
        'lieutenant': 4,
        'second lieutenant': 5,
        'ensign': 6,
        'first sergeant': 7,
        'sergeant': 8,
        'corporal': 9,
        'drummer': 10,
        'fifer': 11,
        'private': 12
    }
    
    for result in results:
        rank = result.get('rank', 'private').lower().strip()
        rank_groups[rank].append(result)
    
    return rank_groups

def format_soldier_markdown(soldier: dict) -> str:
    """Format a single soldier entry for markdown."""
    name = soldier.get('name', 'Unknown')
    rank = soldier.get('rank', 'private')
    wikitree_id = soldier.get('wikitree_id', '').strip()
    confidence = float(soldier.get('confidence', 0))
    location_score = float(soldier.get('location_score', 0))
    est_birth_year = soldier.get('est_birth_year', '')
    
    # Format confidence as percentage
    confidence_pct = int(confidence * 100)
    location_pct = int(location_score * 100)
    
    if wikitree_id:
        # High confidence match
        entry = f"* [[{wikitree_id}|{name}]], {rank}"
        if confidence >= 0.7:
            entry += f" ✓ (confidence: {confidence_pct}%, location: {location_pct}%)"
        else:
            entry += f" ~ (confidence: {confidence_pct}%, location: {location_pct}%)"
    else:
        # No match yet
        entry = f"* {name}, {rank}"
        if est_birth_year:
            entry += f" [b.~{est_birth_year}]"
    
    return entry

def generate_markdown(results: list, output_file: str):
    """Generate markdown output organized by rank."""
    rank_groups = group_by_rank(results)
    
    with open(output_file, 'w') as f:
        f.write("# 16th Regiment (Burrus') Mississippi Militia - WikiTree Matches\n\n")
        f.write("**Service Dates**: October 8-28, 1813  \n")
        f.write("**Service Area**: Northern Madison County, Mississippi  \n")
        f.write("**Total Soldiers**: 387  \n\n")
        
        # Statistics
        total_matched = sum(1 for r in results if r.get('wikitree_id', '').strip())
        high_conf = sum(1 for r in results if float(r.get('confidence', 0)) >= 0.7)
        
        f.write(f"**Statistics**:\n")
        f.write(f"- Matched: {total_matched}/{len(results)} ({total_matched*100//len(results)}%)\n")
        f.write(f"- High Confidence: {high_conf}\n\n")
        
        # Rank order for output
        rank_order = [
            'lieutenant-colonel', 'second major', 'major', 'adjutant',
            'captain', 'lieutenant', 'second lieutenant', 'ensign',
            'first sergeant', 'sergeant', 'corporal',
            'drummer', 'fifer', 'private'
        ]
        
        for rank in rank_order:
            soldiers = rank_groups.get(rank, [])
            if not soldiers:
                continue
            
            f.write(f"## {rank.upper()}\n\n")
            for soldier in sorted(soldiers, key=lambda s: s.get('name', '')):
                f.write(format_soldier_markdown(soldier) + "\n")
            f.write("\n")
        
        # Legend
        f.write("## Legend\n\n")
        f.write("- ✓ High confidence match (score ≥ 70%)\n")
        f.write("- ~ Medium/low confidence match\n")
        f.write("- `[b.~YYYY]` Estimated birth year\n")
        f.write("- `[[WikiTree-ID|Name]]` WikiTree profile link\n\n")
        
        f.write("## Notes\n\n")
        f.write("- Manual verification recommended for all matches\n")
        f.write("- Location scores reflect Madison County, MS preference\n")
        f.write("- Birth years are estimates based on age + rank in 1813\n")
        f.write("- Some soldiers may not have WikiTree profiles\n")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(f"Usage: {sys.argv[0]} <input_csv> [output_markdown]")
        print(f"\nExample:")
        print(f"  {sys.argv[0]} search-results/16th_regiment_wikitree_search.csv")
        print(f"  {sys.argv[0]} search-results/16th_regiment_wikitree_search.csv 16th_regiment_matches.md")
        sys.exit(1)
    
    input_csv = sys.argv[1]
    output_md = sys.argv[2] if len(sys.argv) > 2 else "16th_regiment_wikitree_matches.md"
    
    if not Path(input_csv).exists():
        print(f"Error: Input file not found: {input_csv}")
        sys.exit(1)
    
    print(f"Reading: {input_csv}")
    results = parse_csv(input_csv)
    print(f"Found {len(results)} soldiers")
    
    print(f"Writing: {output_md}")
    generate_markdown(results, output_md)
    print("✓ Done!")
