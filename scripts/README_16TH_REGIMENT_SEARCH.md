# 16th Regiment (Burrus') Mississippi Militia WikiTree Search

## Overview

This tool searches WikiTree for soldiers from the 16th Regiment (Burrus') Mississippi Militia, War of 1812. It extracts ~387 names from the muster roll and attempts to match them with WikiTree profiles, with a focus on reasonable location and birth year estimates.

## Files

- **`search_16th_regiment.py`** - Main search script
- **`debug_extract.py`** - Debug/test script for verifying muster roll extraction
- **Output**: `search-results/16th_regiment_wikitree_search.csv`

## How to Run

### Test Mode (First 10 soldiers)
```bash
cd /Users/davidengland/Documents/GitHub/genealogy
python3 scripts/search_16th_regiment.py 10
```

### Full Search (All 387 soldiers)
```bash
python3 scripts/search_16th_regiment.py
```

### Debug/Verify Extraction
```bash
python3 scripts/debug_extract.py
```

## Output Format

The CSV output includes these columns:

| Column | Description |
|--------|-------------|
| `name` | Soldier's full name (FirstName LastName) |
| `rank` | Military rank (private, sergeant, captain, etc.) |
| `est_birth_year` | Estimated birth year based on age + rank in 1813 |
| `wikitree_id` | WikiTree ID if match found (e.g., "Adams-123") |
| `wikitree_name` | Full name from WikiTree profile |
| `birth_date` | Birth date from WikiTree (if available) |
| `confidence` | Match confidence score (0-1) |
| `location_score` | Location match relevance (0-1) |
| `location_reason` | Why location scored that way |

## Matching Logic

### Birth Year Estimation
Based on military rank in October 1813:
- **Captains/Majors/Colonels**: ~40 years old (born ~1773)
- **Lieutenants**: ~35 years old (born ~1778)
- **Ensigns**: ~28 years old (born ~1785)
- **Sergeants/Corporals**: ~25 years old (born ~1788)
- **Drummers/Fifers**: ~15 years old (born ~1798)
- **Privates**: ~27 years old (born ~1786)

### Location Scoring
Searches prioritize profiles with location data matching:
- Madison County, Mississippi (score 1.0)
- Madison County, any state (score 0.9)
- Mississippi, any county (score 0.8)
- Tennessee/Alabama/Kentucky/Shoals region (score 0.7)
- Tennessee or Alabama (score 0.6)
- Unknown location (score 0.3)

### Confidence Calculation
```
Confidence = (Location Score × 0.6) + (Year Score × 0.4)
```

Where Year Score reflects how close WikiTree's birth date is to the estimated age.

## Rate Limiting

The WikiTree API has rate limits. The script includes:
- 2-second delay between requests by default
- Exponential backoff retry logic for 429 (Too Many Requests) errors
- Max 3 retry attempts per search

**Expected runtime for full search**: ~13-15 minutes (387 soldiers × 2 seconds)

## Manual Review

1. Open the CSV in Excel or a text editor
2. Sort by `confidence` (descending) to see best matches first
3. For high-confidence matches (>0.7), verify manually on WikiTree.com
4. Create a secondary list with confirmed WikiTree IDs for integration into wiki

## Notes

- Some soldiers may not have WikiTree profiles yet
- Common names (Smith, Williams, Jones) may have many candidates - use confidence scores to filter
- Birth years are estimates and may differ from actual
- Location data on WikiTree is often incomplete or private - "Location uncertain" doesn't mean no match
- Names with middle initials (e.g., "John A. Allen") search for exact matches

## Future Enhancements

- Cache search results to avoid re-querying
- Add option to search additional databases (FamilySearch, Ancestry)
- Generate markdown with `[[WikiTree-ID|Name]]` format for wiki integration
- Add command-line filters (e.g., by rank, name pattern)
