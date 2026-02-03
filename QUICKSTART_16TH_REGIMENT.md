# 16th Regiment WikiTree Search - Quick Start Guide

## Implementation Complete ✓

I've created a complete solution to search for 16th Regiment soldiers on WikiTree. Here's what you have:

## Scripts Created

### 1. **`scripts/search_16th_regiment.py`** - Main Search Tool
Extracts all 387 soldiers from the muster roll and searches WikiTree for matches.

**Features:**
- Extracts soldier names and ranks from markdown
- Estimates birth years based on rank/age at 1813 service
- Searches WikiTree API with name + estimated birth date
- Scores matches by location relevance (Madison County, MS preferred)
- Outputs CSV with detailed match information
- Implements retry logic for API rate limiting

**Usage:**
```bash
# Test with first 10 soldiers
python3 scripts/search_16th_regiment.py 10

# Full search (all 387 soldiers) - takes ~13-15 minutes
python3 scripts/search_16th_regiment.py
```

**Output:** `search-results/16th_regiment_wikitree_search.csv`

### 2. **`scripts/csv_to_markdown_16th_regiment.py`** - Results Converter
Converts CSV search results into formatted markdown organized by rank.

**Usage:**
```bash
# Convert to markdown
python3 scripts/csv_to_markdown_16th_regiment.py search-results/16th_regiment_wikitree_search.csv

# Or specify output filename
python3 scripts/csv_to_markdown_16th_regiment.py search-results/16th_regiment_wikitree_search.csv 16th_regiment_results.md
```

**Output:** Markdown file with:
- Soldiers organized by rank
- WikiTree links formatted as `[[ID|Name]]`
- Confidence scores and location matches
- Statistics summary

### 3. **`scripts/debug_extract.py`** - Extraction Tester
Quickly verify that soldier extraction is working correctly.

**Usage:**
```bash
python3 scripts/debug_extract.py
```

## Workflow

### Step 1: Run the Search
```bash
cd /Users/davidengland/Documents/GitHub/genealogy
python3 scripts/search_16th_regiment.py
```

This creates: `search-results/16th_regiment_wikitree_search.csv`

Expected runtime: 13-15 minutes for full 387 soldiers

### Step 2: Review Results (CSV)
Open the CSV in Excel and sort by `confidence` (descending):
- High matches: confidence > 0.7
- Good location score: location_score > 0.6
- Manually verify matches on wikitree.com

### Step 3: (Optional) Convert to Markdown
```bash
python3 scripts/csv_to_markdown_16th_regiment.py search-results/16th_regiment_wikitree_search.csv 16th_regiment_matches.md
```

This creates a nicely formatted markdown file organized by rank for wiki integration.

## Output CSV Columns

| Column | Meaning |
|--------|---------|
| `name` | Soldier full name |
| `rank` | Military rank |
| `est_birth_year` | Estimated birth year based on age + rank in 1813 |
| `wikitree_id` | WikiTree ID if match found (e.g., "Adams-123") |
| `wikitree_name` | Name from WikiTree profile |
| `birth_date` | Birth date from WikiTree |
| `confidence` | 0-1 score, higher = better match |
| `location_score` | 0-1 score for location relevance |
| `location_reason` | Why location scored that way |

## Matching Strategy

### Birth Year Estimation
Based on military rank and assuming service in Oct 1813:
- Officers (Captain+): ~40 years old → born ~1773
- Lieutenants: ~35 years old → born ~1778
- NCOs (Sergeant/Corporal): ~25 years old → born ~1788
- Privates: ~27 years old → born ~1786
- Youth (Drummer/Fifer): ~15 years old → born ~1798

### Location Filtering
Prioritizes profiles with:
- Madison County, Mississippi (highest score)
- Mississippi (any county)
- Tennessee/Alabama/Shoals region
- Unknown location (lowest score)

**Score = 60% Location + 40% Birth Year Match**

## Rate Limiting Notes

WikiTree API has rate limits:
- 2-second delay between requests
- Automatic retry with exponential backoff on 429 errors
- Max 3 retry attempts per person

## Next Steps

1. **Run full search** and let it complete (takes ~13-15 minutes with 387 soldiers)
2. **Review CSV** - open in Excel, sort by confidence
3. **Manual verification** - click WikiTree links and verify matches
4. **Build final list** - create markdown with confirmed WikiTree IDs
5. **Consider enhancements**:
   - Combine with FamilySearch records (War of 1812 pension records)
   - Cross-reference with land grants from the period
   - Link to other family members already on WikiTree
   - Create family groupings for frontier settlements

## Files Reference

```
genealogy/
├── scripts/
│   ├── search_16th_regiment.py          ← Main search tool
│   ├── csv_to_markdown_16th_regiment.py ← Converter
│   ├── debug_extract.py                 ← Debug utility
│   └── README_16TH_REGIMENT_SEARCH.md   ← Full documentation
├── search-results/
│   └── 16th_regiment_wikitree_search.csv ← Main output (CSV)
└── 16th-Regiment-Mississippi-Militia-War-of-1812.md ← Source data
```

## Troubleshooting

**Q: Script returns 0 matches even with known soldiers**
A: WikiTree API may be rate-limited or the person might not have a profile yet. Try running again later or manually searching WikiTree.

**Q: Takes too long to run**
A: 2+ minutes per soldier is normal due to API rate limits. Use `python3 search_16th_regiment.py 50` to test with a smaller batch first.

**Q: Names with middle initials not matching**
A: API searches for exact name combinations. "John A. Allen" searches as written. You may need to manually search variants on WikiTree.

**Q: Location always shows "Location uncertain"**
A: Many WikiTree profiles don't have public location data. This doesn't mean they're wrong matches - verify manually using birth/death dates.

## Questions?

Refer to [README_16TH_REGIMENT_SEARCH.md](README_16TH_REGIMENT_SEARCH.md) for detailed technical documentation.
