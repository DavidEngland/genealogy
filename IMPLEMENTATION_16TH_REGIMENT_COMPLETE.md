# 16th Regiment WikiTree Search - Implementation Summary

## Status: ✅ COMPLETE

A complete Python-based tool suite has been created to search WikiTree for soldiers from the 16th Regiment (Burrus') Mississippi Militia, War of 1812.

---

## What Was Built

### Core Scripts

#### 1. **`scripts/search_16th_regiment.py`** 
Main search tool that orchestrates the entire workflow.

**Capabilities:**
- Extracts 387 soldier names and ranks from markdown muster roll
- Estimates birth years based on military rank (rank=age proxy)
- Searches WikiTree API with name + estimated birth date
- Filters results by location relevance (Madison County, MS priority)
- Scores matches on confidence (0-1 scale)
- Handles API rate limiting with exponential backoff retry
- Outputs detailed CSV with match information
- Progress reporting every 50 soldiers

**Key Parameters:**
- Birth year range: 1763-1795 (18-50 years old at Oct 1813 service)
- Location priority: Madison County, MS → MS → TN/AL → Unknown
- API delay: 2 seconds between requests
- Rate limit retries: 3 max with 2s initial backoff

#### 2. **`scripts/csv_to_markdown_16th_regiment.py`**
Converts CSV results into formatted markdown for wiki integration.

**Capabilities:**
- Groups soldiers by military rank
- Creates WikiTree links: `[[ID|Name]]` format
- Shows confidence scores and location matches
- Organizes output from officers → privates
- Includes statistics summary
- Provides interpretation legend

#### 3. **`scripts/debug_extract.py`**
Testing utility to verify soldier extraction works correctly.

**Capabilities:**
- Parses muster roll independently
- Shows first N soldiers
- Confirms extraction count
- Validates name/rank parsing

### Documentation

#### **`QUICKSTART_16TH_REGIMENT.md`**
Quick reference guide with:
- Step-by-step workflow
- Command examples
- Output explanations
- Troubleshooting tips

#### **`scripts/README_16TH_REGIMENT_SEARCH.md`**
Detailed technical documentation with:
- Complete feature overview
- Matching algorithm explanation
- Rate limiting details
- Birth year estimation tables
- Location scoring methodology

---

## How to Use

### Minimal Quick Start

```bash
cd /Users/davidengland/Documents/GitHub/genealogy

# Test with 10 soldiers (30 seconds)
python3 scripts/search_16th_regiment.py 10

# Full search all 387 soldiers (13-15 minutes)
python3 scripts/search_16th_regiment.py
```

### With Results Processing

```bash
# 1. Run search
python3 scripts/search_16th_regiment.py

# 2. Review CSV
open search-results/16th_regiment_wikitree_search.csv

# 3. Convert to markdown (optional)
python3 scripts/csv_to_markdown_16th_regiment.py \
  search-results/16th_regiment_wikitree_search.csv \
  16th_regiment_results.md
```

---

## Technical Details

### Muster Roll Extraction
- **Source**: `16th-Regiment-Mississippi-Militia-War-of-1812.md`
- **Format Parsed**: `* LastName, FirstName, rank`
- **Total Soldiers**: 387
- **Rank Categories**: 14 (from lieutenant-colonel to private)

### Birth Year Estimation Logic
Based on rank serving in October 1813:

| Rank | Est. Age | Est. Birth Year |
|------|----------|-----------------|
| Lieutenant-Colonel | 40 | 1773 |
| Major | 40 | 1773 |
| Captain | 40 | 1773 |
| Adjutant | 40 | 1773 |
| Lieutenant | 35 | 1778 |
| Ensign | 28 | 1785 |
| Sergeant | 25 | 1788 |
| Corporal | 25 | 1788 |
| Drummer | 15 | 1798 |
| Fifer | 15 | 1798 |
| Private | 27 | 1786 |

### Confidence Scoring
```
Confidence = (Location Score × 0.6) + (Year Match Score × 0.4)
```

**Location Scoring:**
- Madison County, MS exact: 1.0
- Madison County (any state): 0.9
- Mississippi (any county): 0.8
- TN/AL/KY/Shoals region: 0.7
- Any of above states: 0.6
- Unknown location: 0.3

**Year Match Scoring:**
- Within 10 years: 1.0
- Within 20 years: 0.7
- Within 30 years: 0.5
- Beyond 30 years: 0.3

### WikiTree API Integration
- **Endpoint**: `https://api.wikitree.com/api.php`
- **Action**: `searchPerson`
- **Parameters**: FirstName, LastName, BirthDate (±20 years), limit=10
- **Fields Returned**: Id, Name, FirstName, LastName, BirthDate, DeathDate, BirthLocation, DeathLocation
- **Rate Limit**: 2 seconds between requests
- **Retry Strategy**: Exponential backoff (2s, 4s, 8s) on 429 errors

---

## Output Format

### CSV Columns
| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Soldier full name (FirstName LastName) |
| `rank` | string | Military rank |
| `est_birth_year` | integer | Estimated birth year |
| `wikitree_id` | string | WikiTree ID if match found (e.g., "Adams-123") |
| `wikitree_name` | string | Name from WikiTree profile |
| `birth_date` | string | Birth date from WikiTree |
| `confidence` | float | Match confidence 0-1 |
| `location_score` | float | Location relevance 0-1 |
| `location_reason` | string | Why location scored that way |

### Example Output
```csv
name,rank,est_birth_year,wikitree_id,wikitree_name,birth_date,confidence,location_score,location_reason
Benjamin Adams,private,1786,Adams-567,Benjamin Adams,1786-03-15,0.75,0.8,Madison County (state unclear)
John A. Allen,lieutenant,1778,Allen-892,John Arlington Allen,1777-06-20,0.82,0.9,Madison County (state unclear)
```

---

## File Structure

```
genealogy/
├── QUICKSTART_16TH_REGIMENT.md          ← Start here
├── 16th-Regiment-Mississippi-Militia-War-of-1812.md  ← Source muster roll
├── scripts/
│   ├── search_16th_regiment.py          ← Main search script
│   ├── csv_to_markdown_16th_regiment.py ← Results converter
│   ├── debug_extract.py                 ← Extraction tester
│   └── README_16TH_REGIMENT_SEARCH.md   ← Full technical docs
└── search-results/
    └── 16th_regiment_wikitree_search.csv ← Output (created after running)
```

---

## Key Features Implemented

✅ **Automatic Extraction** - Parses 387 soldiers from markdown  
✅ **Smart Estimation** - Birth years from military rank  
✅ **API Integration** - WikiTree search with proper encoding  
✅ **Rate Limiting** - Handles API throttling gracefully  
✅ **Location Filtering** - Prioritizes Madison County, MS  
✅ **Confidence Scoring** - Weighted location + birth year match  
✅ **Results Processing** - CSV output + markdown conversion  
✅ **Error Handling** - Retry logic, timeouts, format validation  
✅ **Documentation** - Quick start + detailed technical guide  
✅ **Testing Tools** - Debug script for verification  

---

## Workflow

```
1. Extract soldiers from muster roll
   ↓
2. For each soldier:
   - Estimate birth year from rank
   - Search WikiTree API
   - Score and filter results
   ↓
3. Output to CSV
   ↓
4. (Optional) Convert to markdown for wiki
   ↓
5. Manual verification on WikiTree.com
   ↓
6. Build final list with confirmed WikiTree IDs
```

---

## Next Steps for You

1. **Run the test**: `python3 scripts/search_16th_regiment.py 10`
2. **Review test results** in the generated CSV
3. **Run full search**: `python3 scripts/search_16th_regiment.py`
   - Takes 13-15 minutes for all 387 soldiers
4. **Sort by confidence** to identify best matches
5. **Manually verify** high-confidence matches on WikiTree.com
6. **Build final list** with confirmed WikiTree IDs and ranks
7. **(Optional) Generate markdown** for wiki integration

---

## Customization Options

The scripts support these use cases:

- **Limit searches**: `python3 search_16th_regiment.py 50` (first 50 only)
- **Change output location**: Edit `OUTPUT_DIR` in script
- **Adjust API delays**: Edit `API_RATE_LIMIT_DELAY` (higher = slower, fewer rate limits)
- **Filter by rank**: Modify extraction or post-process CSV
- **Different date ranges**: Edit `BIRTH_YEAR_MIN` and `BIRTH_YEAR_MAX`

---

## Troubleshooting

**Q: Getting 0 matches?**
A: WikiTree API may be rate-limited. Wait a few minutes and try again. Also check that soldiers don't have WikiTree profiles yet.

**Q: Script running slowly?**
A: 2+ seconds per soldier is normal due to API rate limiting. This is intentional to avoid being blocked.

**Q: Names not matching (e.g., "John A. Allen")?**
A: WikiTree searches for exact names. Try removing middle initials or manually searching WikiTree.

**Q: Location always "uncertain"?**
A: Many WikiTree profiles have private location data. Check manually using birth/death dates.

---

## Created: February 3, 2026

Implementation includes 3 Python scripts, 2 documentation guides, full API integration, and comprehensive matching logic designed for the 16th Regiment (Burrus') Mississippi Militia, War of 1812 genealogical research project.
