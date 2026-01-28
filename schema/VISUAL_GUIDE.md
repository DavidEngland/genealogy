# 🧬 Genealogy Schema System - Visual Guide

## Your Data Flow

```
GEDCOM Files (.ged)
        ↓
    [gedcom_parser.php]
        ↓
    JSON Files (.json)
   /          \
  ↓            ↓
[Browser]  [csv_exporter.php]
  ↓            ↓
[Web UI]    CSV Files (.csv)
             /    \
            ↓      ↓
          [Excel] [Lookup Index]
```

## What You Have Now

### 1. **JSON Schema** (`schema.json`)
   - Defines structure for genealogy data
   - Aligned with schema.org standards
   - Supports Person, Event, Place, Source objects

### 2. **PHP Parser** (`gedcom_parser.php`)
   ```
   Input:  GEDCOM file (5.5/5.5.1 format)
   Output: JSON file following schema
   ```

### 3. **CSV Exporter** (`csv_exporter.php`)
   ```
   Input:  JSON file
   Output: CSV (lookup or full format)
   ```

### 4. **Web Interface** (`index.html`)
   ```
   Features:
   • Load JSON files
   • View genealogy data in tables
   • Search by name/ID
   • Export to CSV
   • No server required (works offline)
   ```

### 5. **Batch Processor** (`batch_process.sh`)
   ```
   Processes: All .ged files in GEDs/ folder
   Output:    45 JSON files + 90 CSV files
   Time:      ~2 minutes
   ```

## Example Usage Scenarios

### 📌 Scenario 1: Find all people named Duncan
```bash
# Search in web interface
open schema/index.html
# Load Duncans.json
# Search: "Duncan" in the search box
# View: 801 people matching Duncan surname
```

### 📌 Scenario 2: Create a master index
```bash
# Combine all lookup CSVs
cat data/*-lookup.csv | grep -v "^wikitree" | sort | uniq > master-index.csv

# Result: 19,000+ people in a single CSV file
# Use in Excel for analysis
```

### 📌 Scenario 3: Analyze a specific family
```bash
# Export Hargroves to full CSV
php schema/csv_exporter.php data/Hargroves.json Hargroves.csv full

# Open in Excel/Google Sheets
# 12,967 people with:
#   - Birth dates/places
#   - Death dates/places
#   - Sex
#   - Names (full, given, surname)
```

### 📌 Scenario 4: Add new GEDCOM file
```bash
# Copy new file to GEDs/
cp my_family.ged GEDs/

# Parse it
php schema/gedcom_parser.php GEDs/my_family.ged data/my_family.json

# Export CSV
php schema/csv_exporter.php data/my_family.json my_family.csv lookup

# View in browser
# Load data/my_family.json in index.html
```

## File Organization

```
genealogy/
│
├── schema/                    ← Schema system
│   ├── schema.json           ← Definition
│   ├── *.php                 ← Tools
│   ├── *.sh                  ← Scripts
│   ├── index.html            ← Web interface
│   └── *.md                  ← Documentation
│
├── data/                      ← Generated (90 files)
│   ├── *.json               ← Parsed genealogy data
│   ├── *-lookup.csv         ← Quick lookups
│   └── *-full.csv           ← Complete data
│
├── GEDs/                      ← Your GEDCOM files
│   └── *.ged                ← Source data (45 files)
│
└── IMPLEMENTATION_SUMMARY.md  ← This project summary
```

## Data Statistics by File

| File | People | Families | Format |
|------|--------|----------|--------|
| Hargroves | 12,967 | 4,761 | JSON + CSVs |
| JamesDuncan | 1,388 | 556 | ✓ |
| WilliamEnglandIre | 1,221 | 497 | ✓ |
| Duncans | 801 | 266 | ✓ |
| JabezPerkins | 450 | 128 | ✓ |
| Lawsons | 475 | 214 | ✓ |
| McIntyre | 434 | 170 | ✓ |
| *...39 more files...* | | | ✓ |

**Total: 19,055+ people across 45 genealogy datasets**

## Tech Stack

### What You Have
- ✅ **PHP** (parsing & CSV export)
- ✅ **JSON** (data format)
- ✅ **CSV** (spreadsheet format)
- ✅ **HTML/JavaScript** (web interface)
- ✅ **Bash** (automation)

### What You DON'T Need
- ❌ Python
- ❌ Node.js
- ❌ Web server
- ❌ Database
- ❌ Complex setup

## Quick Command Reference

| Task | Command |
|------|---------|
| View data | `open schema/index.html` |
| Parse GEDCOM | `php schema/gedcom_parser.php in.ged out.json` |
| Export CSV (lookup) | `php schema/csv_exporter.php file.json out.csv lookup` |
| Export CSV (full) | `php schema/csv_exporter.php file.json out.csv full` |
| Process all GEDCOMs | `schema/batch_process.sh` |
| Start web server | `schema/serve.sh` |

## Key Features

### ✨ Data Structures
- **People**: Full name, given/surname, birth, death, families
- **Families**: Spouse links, children, marriage events
- **Places**: Geographic hierarchy, coordinates
- **Sources**: Citations, bibliographic info

### ✨ Genealogy Support
- GEDCOM 5.5/5.5.1 parsing
- WikiTree ID mapping
- Approximate dates (ABT, BEF, AFT)
- Multi-part place names
- Multiple marriages/families
- Custom notes

### ✨ Export Formats
- **JSON**: Structured data, queryable
- **CSV (Lookup)**: Fast ID lookups
- **CSV (Full)**: Complete genealogy data

## Next Steps

1. **Explore the data**
   ```bash
   open schema/index.html
   # Load data/Duncans.json
   # Search for "Duncan"
   # View 801 people
   ```

2. **Create lookups**
   ```bash
   cat data/*-lookup.csv > all-ancestors.csv
   # 19,000+ people in one file
   ```

3. **Analyze in Excel**
   ```bash
   open data/Hargroves-full.csv
   # 12,967 people with birth/death info
   # Filter, sort, chart data
   ```

4. **Add more data**
   ```bash
   # Place new .ged files in GEDs/
   schema/batch_process.sh
   # All processed automatically
   ```

## Schema Alignment

### schema.org Compliance
- ✅ **Person**: Individual records
- ✅ **Event**: Birth, death, marriage
- ✅ **Place**: Geographic locations
- ✅ **CreativeWork**: Sources/citations

Ready for:
- Linked data (JSON-LD)
- Search engine indexing
- Data interchange
- Future semantic web features

---

**You're all set!** 
Start with: `open schema/index.html`
