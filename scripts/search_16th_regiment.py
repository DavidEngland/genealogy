#!/usr/bin/env python3
"""
Search WikiTree for soldiers from the 16th Regiment (Burrus') Mississippi Militia, War of 1812.

Extracts soldier names/ranks from the muster roll, searches WikiTree API with:
- Name matching
- Estimated birth date range (1763-1795 for 18-50 year olds in Oct 1813)
- Location filtering (Madison County, Mississippi preferred)

Output: CSV file for manual review with columns:
  Name, Rank, Est. Birth Year, WikiTree ID, Match Confidence, Location Match
"""

import re
import csv
import json
import time
from datetime import datetime
from pathlib import Path
from typing import List, Dict, Tuple, Optional
import urllib.request
import urllib.error
import urllib.parse

# Configuration
MUSTER_ROLL_FILE = "/Users/davidengland/Documents/GitHub/genealogy/16th-Regiment-Mississippi-Militia-War-of-1812.md"
OUTPUT_DIR = Path("/Users/davidengland/Documents/GitHub/genealogy/search-results")
CACHE_DIR = Path("/Users/davidengland/Documents/GitHub/genealogy/search-results/16th-regiment-cache")
OUTPUT_CSV = OUTPUT_DIR / "16th_regiment_wikitree_search.csv"
OUTPUT_JSON = OUTPUT_DIR / "16th_regiment_search_raw.json"

# WikiTree API settings
WIKITREE_API_URL = "https://api.wikitree.com/api.php"
BIRTH_YEAR_MIN = 1763  # 50 years old in Oct 1813
BIRTH_YEAR_MAX = 1795  # 18 years old in Oct 1813
LOCATION_KEYWORDS = ["Madison", "Mississippi", "Shoals", "Tennessee", "Alabama"]
API_RATE_LIMIT_DELAY = 2.0  # seconds between requests (increased for rate limiting)

# Known matches - soldiers already verified on WikiTree
# Format: (FirstName, LastName, Rank) -> WikiTree ID
KNOWN_MATCHES = {
    ('Valentine', 'Hargrove', 'adjutant'): 'Hargrove-277',
    ('James', 'Hartgrove', 'private'): 'Hargrove-287',  # Note: spelled "Hartgrove" in muster roll
    # Add more verified matches here as needed
}


def extract_soldiers_from_markdown(filepath: str) -> List[Dict[str, str]]:
    """
    Parse the muster roll markdown file and extract soldier data.
    Returns list of dicts: {first_name, last_name, rank, full_name}
    """
    soldiers = []
    
    try:
        with open(filepath, 'r') as f:
            lines = f.readlines()
    except FileNotFoundError:
        print(f"Error: Muster roll file not found: {filepath}")
        return []
    
    # Find the start of muster roll (look for "=== Muster Roll ===" marker)
    in_muster_roll = False
    
    for line in lines:
        # Check if we're starting the muster roll section
        if "=== Muster Roll ===" in line:
            in_muster_roll = True
            continue
        
        # Stop if we hit the next major section (3 equals, not subsections with 4 equals)
        if in_muster_roll and line.startswith("===") and "Muster Roll" not in line and not line.startswith("===="):
            break
        
        # Extract soldier entries: "* LastName, FirstName, rank"
        # Skip subsection headers like "==== A ===="
        if in_muster_roll and line.strip().startswith("* ") and not line.strip().startswith("=="):
            entry = line.strip()[2:].strip()  # Remove "* "
            
            # Remove markdown bold markers (''')
            entry = entry.replace("'''", "")
            
            # Parse: "LastName, FirstName, rank"
            parts = [p.strip() for p in entry.split(',')]
            
            if len(parts) == 3:
                last_name = parts[0]
                first_name = parts[1]
                rank = parts[2]
                
                soldiers.append({
                    'last_name': last_name,
                    'first_name': first_name,
                    'rank': rank,
                    'full_name': f"{first_name} {last_name}"
                })
    
    return soldiers


def search_wikitree(first_name: str, last_name: str, birth_year: Optional[int] = None) -> Dict:
    """
    Search WikiTree API for a person.
    Returns parsed API response (matches array).
    Implements retry logic for rate limiting.
    """
    params = {
        'action': 'searchPerson',
        'firstName': first_name,
        'lastName': last_name,
        'limit': 10,
        'fields': 'Id,Name,FirstName,LastName,BirthDate,DeathDate,BirthLocation,DeathLocation,BirthDateDecade'
    }
    
    # Add estimated birth date range if provided
    if birth_year:
        params['BirthDate'] = f"{birth_year}-06-15"  # Mid-year estimate
        params['dateSpread'] = 20  # +/- 20 years tolerance
    
    # Properly encode URL with spaces and special characters
    query_string = urllib.parse.urlencode(params)
    url = f"{WIKITREE_API_URL}?{query_string}"
    
    max_retries = 3
    retry_delay = 2
    
    for attempt in range(max_retries):
        try:
            with urllib.request.urlopen(url, timeout=10) as response:
                data = json.loads(response.read().decode('utf-8'))
                time.sleep(API_RATE_LIMIT_DELAY)
                
                # WikiTree API returns data in 'matches' key if successful
                # Response structure: {'matches': [...]} or just a list
                if isinstance(data, list):
                    return {'matches': data}
                elif isinstance(data, dict) and 'matches' in data:
                    return data
                else:
                    return {'matches': []}
        except urllib.error.HTTPError as e:
            if e.code == 429 and attempt < max_retries - 1:
                # Rate limited, wait and retry
                wait_time = retry_delay * (2 ** attempt)  # Exponential backoff
                time.sleep(wait_time)
                continue
            else:
                return {'matches': []}
        except Exception as e:
            return {'matches': []}
    
    return {'matches': []}


def score_location_match(birth_location: str, death_location: str) -> Tuple[float, str]:
    """
    Score location relevance to Madison County, MS / borderlands region.
    Returns: (score 0-1, reason)
    """
    locations = [birth_location or "", death_location or ""]
    combined = " ".join(locations).upper()
    
    if "MADISON" in combined and "MISSISSIPPI" in combined:
        return 1.0, "Madison County, MS"
    if "MADISON" in combined:
        return 0.9, "Madison County (state unclear)"
    if "MISSISSIPPI" in combined:
        return 0.8, "Mississippi (county unclear)"
    if any(kw in combined for kw in ["SHOALS", "TENNESSEE", "ALABAMA"]):
        return 0.7, "Borderlands region (TN/AL/MS)"
    if any(kw in combined for kw in ["TENNESSEE", "ALABAMA"]):
        return 0.6, "Tennessee/Alabama"
    
    return 0.3, "Location uncertain"


def estimate_birth_year_from_rank(rank: str, service_year: int = 1813) -> int:
    """
    Estimate birth year based on military rank in 1813.
    General heuristic for militia service.
    """
    rank_lower = rank.lower()
    
    # Officers and NCOs likely older
    if "captain" in rank_lower or "major" in rank_lower or "colonel" in rank_lower:
        return service_year - 40  # ~40 years old
    if "lieutenant" in rank_lower:
        return service_year - 35  # ~35 years old
    if "ensign" in rank_lower:
        return service_year - 28  # ~28 years old
    if "sergeant" in rank_lower or "corporal" in rank_lower:
        return service_year - 25  # ~25 years old
    if "drummer" in rank_lower or "fifer" in rank_lower:
        return service_year - 15  # ~15 years old (younger)
    
    # Default for privates: ~25-30 years old
    return service_year - 27


def process_search_result(soldier: Dict, api_result: Dict) -> Optional[Dict]:
    """
    Process a single search result and return best match info.
    """
    matches = api_result.get('matches', [])
    
    if not matches:
        return None
    
    # Score each match
    scored_matches = []
    for match in matches:
        birth_year = None
        if match.get('BirthDate'):
            try:
                birth_year = int(match['BirthDate'][:4])
            except (ValueError, IndexError):
                pass
        
        location_score, location_reason = score_location_match(
            match.get('BirthLocation', ''),
            match.get('DeathLocation', '')
        )
        
        # Check if birth year is plausible
        year_score = 1.0
        est_birth_year = estimate_birth_year_from_rank(soldier['rank'])
        if birth_year:
            year_diff = abs(birth_year - est_birth_year)
            if year_diff > 30:
                year_score = 0.3
            elif year_diff > 20:
                year_score = 0.5
            elif year_diff > 10:
                year_score = 0.7
        
        # Combined confidence score
        confidence = (location_score * 0.6 + year_score * 0.4)
        
        scored_matches.append({
            'match': match,
            'confidence': confidence,
            'location_score': location_score,
            'location_reason': location_reason,
            'year_score': year_score,
            'estimated_birth_year': est_birth_year
        })
    
    # Return best match
    best = max(scored_matches, key=lambda x: x['confidence'])
    
    return {
        'wikitree_id': best['match'].get('Name', ''),
        'wikitree_name': best['match'].get('FirstName', '') + ' ' + best['match'].get('LastName', ''),
        'birth_date': best['match'].get('BirthDate', ''),
        'confidence': best['confidence'],
        'location_score': best['location_score'],
        'location_reason': best['location_reason'],
        'estimated_birth_year': best['estimated_birth_year']
    }


def run_search(limit: Optional[int] = None):
    """
    Main search function. Extract soldiers, search WikiTree, output results.
    """
    # Setup output directories
    OUTPUT_DIR.mkdir(exist_ok=True)
    CACHE_DIR.mkdir(exist_ok=True)
    
    print(f"[1] Extracting soldiers from muster roll: {MUSTER_ROLL_FILE}")
    soldiers = extract_soldiers_from_markdown(MUSTER_ROLL_FILE)
    print(f"    Found {len(soldiers)} soldiers\n")
    
    if not soldiers:
        print("Error: No soldiers extracted from muster roll")
        return
    
    if limit:
        soldiers = soldiers[:limit]
        print(f"    (Limited to first {limit} for testing)\n")
    
    # Search WikiTree for each soldier
    print(f"[2] Searching WikiTree for {len(soldiers)} soldiers...")
    results = []
    known_matches_used = 0
    
    for i, soldier in enumerate(soldiers, 1):
        if i % 50 == 0:
            print(f"    Progress: {i}/{len(soldiers)}")
        
        # Get estimated birth year from rank
        est_birth_year = estimate_birth_year_from_rank(soldier['rank'])
        
        # Check if this soldier is in known matches (override API search)
        match_key = (soldier['first_name'], soldier['last_name'], soldier['rank'].lower())
        if match_key in KNOWN_MATCHES:
            wikitree_id = KNOWN_MATCHES[match_key]
            match_info = {
                'wikitree_id': wikitree_id,
                'wikitree_name': f"{soldier['first_name']} {soldier['last_name']}",
                'birth_date': '',
                'confidence': 1.0,  # 100% confidence for known matches
                'location_score': 1.0,
                'location_reason': 'Known match (pre-verified)'
            }
            known_matches_used += 1
        else:
            # Search WikiTree API
            api_result = search_wikitree(
                soldier['first_name'],
                soldier['last_name'],
                birth_year=est_birth_year
            )
            
            # Process result
            match_info = process_search_result(soldier, api_result)
        
        result_row = {
            'name': soldier['full_name'],
            'rank': soldier['rank'],
            'est_birth_year': est_birth_year,
            'wikitree_id': match_info['wikitree_id'] if match_info else '',
            'wikitree_name': match_info['wikitree_name'] if match_info else '',
            'birth_date': match_info['birth_date'] if match_info else '',
            'confidence': match_info['confidence'] if match_info else 0.0,
            'location_score': match_info['location_score'] if match_info else 0.0,
            'location_reason': match_info['location_reason'] if match_info else 'Not searched'
        }
        
        results.append(result_row)
    
    print(f"    Completed {len(soldiers)} searches\n")
    
    # Write CSV output
    print(f"[3] Writing results to: {OUTPUT_CSV}")
    fieldnames = ['name', 'rank', 'est_birth_year', 'wikitree_id', 'wikitree_name', 
                  'birth_date', 'confidence', 'location_score', 'location_reason']
    
    with open(OUTPUT_CSV, 'w', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(results)
    
    print(f"    Wrote {len(results)} rows\n")
    
    # Statistics
    matched = sum(1 for r in results if r['wikitree_id'])
    high_confidence = sum(1 for r in results if r['confidence'] > 0.7)
    good_location = sum(1 for r in results if r['location_score'] > 0.6)
    
    print(f"[4] Summary:")
    print(f"    Total soldiers: {len(results)}")
    print(f"    Known matches (pre-verified): {known_matches_used}")
    print(f"    Matched on WikiTree: {matched} ({matched*100//len(results)}%)")
    print(f"    High confidence matches (>0.7): {high_confidence}")
    print(f"    Good location matches (>0.6): {good_location}")
    print(f"\nOutput file: {OUTPUT_CSV}")
    print(f"Open in Excel or text editor for manual review and refinement.")


if __name__ == "__main__":
    import sys
    
    limit = None
    if len(sys.argv) > 1:
        try:
            limit = int(sys.argv[1])
            print(f"Testing with first {limit} soldiers\n")
        except ValueError:
            pass
    
    run_search(limit=limit)
