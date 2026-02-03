# 16th Regiment WikiTree Search Tool - Implementation Summary

## ✅ IMPLEMENTATION COMPLETE

A complete, production-ready Python tool suite has been created to search WikiTree for soldiers from the 16th Regiment (Burrus') Mississippi Militia, War of 1812.

---

## Quick Start

```bash
# Navigate to project
cd /Users/davidengland/Documents/GitHub/genealogy

# Test with 10 soldiers (takes ~30 seconds)
python3 scripts/search_16th_regiment.py 10

# Full search of all 387 soldiers (takes ~13-15 minutes)
python3 scripts/search_16th_regiment.py

# Convert results to markdown (optional)
python3 scripts/csv_to_markdown_16th_regiment.py search-results/16th_regiment_wikitree_search.csv
```

---

## Files Created

### Scripts
- ✅ `scripts/search_16th_regiment.py` - Main search engine
- ✅ `scripts/csv_to_markdown_16th_regiment.py` - Results formatter
- ✅ `scripts/debug_extract.py` - Extraction tester

### Documentation
- ✅ `QUICKSTART_16TH_REGIMENT.md` - Quick reference guide
- ✅ `IMPLEMENTATION_16TH_REGIMENT_COMPLETE.md` - Detailed technical summary
- ✅ `scripts/README_16TH_REGIMENT_SEARCH.md` - Full API documentation

---

## What It Does

### 1. Extracts Soldiers
- Parses `16th-Regiment-Mississippi-Militia-War-of-1812.md`
- Extracts 387 soldier names and military ranks
- Validates format: `* LastName, FirstName, rank`

### 2. Estimates Birth Years
Uses military rank as age proxy for October 1813 service:
- Captains/Majors: ~40 years old (born ~1773)
- Lieutenants: ~35 years old (born ~1778)  
- NCOs: ~25 years old (born ~1788)
- Privates: ~27 years old (born ~1786)

### 3. Searches WikiTree (with Known Matches Support)
- **Checks for known/pre-verified matches first** (skips API)
- Calls WikiTree API with name + estimated birth date range
- Handles rate limiting and retries automatically
- Returns multiple candidates per person
- Scores matches by location relevance and birth year accuracy

**Current known matches**: Valentine Hargrove (Hargrove-277), James Hartgrove (Hargrove-287)

### 4. Outputs Results
**CSV Format** with columns:
- `name` - Soldier full name
- `rank` - Military rank
- `est_birth_year` - Estimated birth year
- `wikitree_id` - WikiTree ID if matched (e.g., "Adams-567")
- `confidence` - Match confidence score (0-1)
- `location_score` - Location relevance (0-1)
- `location_reason` - Why location scored that way

### 5. Generates Markdown (Optional)
Converts CSV to formatted markdown organized by rank with:
- WikiTree links: `[[ID|Name]]`
- Confidence indicators
- Statistics summary

---

## Output Location

**CSV Results**: `search-results/16th_regiment_wikitree_search.csv`

Open in Excel and sort by `confidence` (descending) to see best matches first.

---

## Key Features

| Feature | Details |
|---------|---------|
| **Soldiers Extracted** | 387 from muster roll |
| **Birth Year Range** | 1763-1795 (18-50 year olds) |
| **Location Priority** | Madison County, MS > MS > TN/AL > Unknown |
| **API Rate Limiting** | 2 second delays + exponential backoff |
| **Confidence Scoring** | 60% location + 40% birth year |
| **Rank Categories** | 14 types (lieutenant-colonel through private) |
| **Expected Runtime** | 13-15 minutes for all 387 |
| **Known Matches** | Pre-verified WikiTree IDs (skips API) |
| **Current Known Matches** | 2 (Valentine Hargrove, James Hartgrove) |

---

## Known Matches Feature

Pre-verified soldier matches are stored in `KNOWN_MATCHES` dictionary:

```python
KNOWN_MATCHES = {
    ('Valentine', 'Hargrove', 'adjutant'): 'Hargrove-277',
    ('James', 'Hartgrove', 'private'): 'Hargrove-287',
}
```

**Benefits:**
- 100% confidence score (no API uncertainty)
- Skips 2-second API delay for faster searches
- "Known match (pre-verified)" label in CSV output
- Easy to add more verified matches

**To add more known matches**, see [KNOWN_MATCHES_GUIDE.md](KNOWN_MATCHES_GUIDE.md)

---

## Matching Strategy

### Score Calculation
```
Confidence = (Location Match × 0.6) + (Birth Year Match × 0.4)
```

### Location Weighting
- Madison County, MS exact match: 1.0 (100%)
- Mississippi, any county: 0.8 (80%)
- Tennessee/Alabama/Shoals: 0.7 (70%)
- Unknown location: 0.3 (30%)

### Birth Year Weighting
- Within ±10 years: 1.0 (100%)
- Within ±20 years: 0.7 (70%)
- Within ±30 years: 0.5 (50%)
- Beyond ±30 years: 0.3 (30%)

---

## Workflow

```
STEP 1: Run Search
python3 scripts/search_16th_regiment.py
└─ Outputs: search-results/16th_regiment_wikitree_search.csv

STEP 2: Review Results
- Open CSV in Excel
- Sort by 'confidence' descending
- High confidence (>0.7) = likely good matches
- Good location score (>0.6) = nearby region

STEP 3: Manual Verification
- Click WikiTree IDs on wikitree.com
- Verify dates and location match
- Note any corrections needed

STEP 4: Build Final List
- Document confirmed WikiTree IDs
- Format as [[ID|Name]] for wiki
- Organized by rank for your stickers project

STEP 5: (Optional) Generate Markdown
python3 scripts/csv_to_markdown_16th_regiment.py search-results/16th_regiment_wikitree_search.csv
└─ Outputs formatted markdown organized by rank
```

---

## Documentation Hierarchy

1. **START HERE**: `QUICKSTART_16TH_REGIMENT.md`
   - Quick reference, command examples
   - Troubleshooting tips
   
2. **Full Details**: `IMPLEMENTATION_16TH_REGIMENT_COMPLETE.md`
   - Complete technical documentation
   - All features explained
   - Example outputs

3. **API Reference**: `scripts/README_16TH_REGIMENT_SEARCH.md`
   - Detailed algorithm explanations
   - Rate limiting information
   - Birth year estimation tables

---

## Testing

Verify everything works:

```bash
# Test extraction
python3 scripts/debug_extract.py
# Should output: ✓ Found 387 soldiers

# Test search with 5 soldiers
python3 scripts/search_16th_regiment.py 5
# Should create CSV with 5 rows of results
```

---

## Next Actions

1. ✅ Read: `QUICKSTART_16TH_REGIMENT.md`
2. ✅ Run: `python3 scripts/search_16th_regiment.py 10` (test)
3. ✅ Review: Results in `search-results/16th_regiment_wikitree_search.csv`
4. ✅ Run: `python3 scripts/search_16th_regiment.py` (full - takes 13-15 min)
5. ✅ Sort CSV by confidence to find best matches
6. ✅ Manually verify matches on wikitree.com
7. ✅ Build final list with WikiTree IDs and ranks

---

## Customization

All parameters are configurable in `search_16th_regiment.py`:

```python
# Adjust API rate delay (seconds)
API_RATE_LIMIT_DELAY = 2.0  # Higher = slower but fewer 429 errors

# Adjust birth year range
BIRTH_YEAR_MIN = 1763
BIRTH_YEAR_MAX = 1795

# Change output directory
OUTPUT_DIR = Path("/your/preferred/path")
```

---

## Support

- **Quick questions?** See: `QUICKSTART_16TH_REGIMENT.md`
- **Technical details?** See: `scripts/README_16TH_REGIMENT_SEARCH.md`
- **Algorithm explanation?** See: `IMPLEMENTATION_16TH_REGIMENT_COMPLETE.md`

---

## Notes

- The tool estimates birth years based on rank/age assumptions - verify against actual records
- WikiTree API has rate limits; 2-second delays between requests are intentional
- Some soldiers may not have WikiTree profiles yet
- Location data on WikiTree is often incomplete or private
- High confidence scores (>0.7) still require manual verification

---

**Created**: February 3, 2026  
**Status**: Production Ready  
**Tested**: ✅ Extracts 387 soldiers correctly, API integration verified
