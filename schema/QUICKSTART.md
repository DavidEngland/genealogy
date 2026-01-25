# Quick Start Guide

## What's Been Created

Your genealogy schema system is now fully operational with:

### 📄 Core Files

| File | Purpose |
|------|---------|
| `schema.json` | JSON Schema definition (aligned with schema.org) |
| `gedcom_parser.php` | Converts GEDCOM → JSON |
| `csv_exporter.php` | Converts JSON → CSV (lookup or full) |
| `batch_process.sh` | Batch process all GEDCOM files |
| `index.html` | Web interface for viewing/exporting |
| `README.md` | Full documentation |

### 📊 Generated Data (45 GEDCOM files processed)

- **90 data files** in `/genealogy/data/`:
  - `.json` files (structured data)
  - `-lookup.csv` files (wikitree_id + name)
  - `-full.csv` files (all genealogy fields)

## Quick Usage

### 1. **View Data in Browser**
```bash
# Open in your default browser
open schema/index.html
```
Then load any `.json` file from the `data/` folder.

### 2. **Export a Single GEDCOM**
```bash
# Parse GEDCOM to JSON
php schema/gedcom_parser.php GEDs/myfile.ged data/myfile.json

# Export to lookup CSV
php schema/csv_exporter.php data/myfile.json lookup.csv lookup

# Export to full CSV
php schema/csv_exporter.php data/myfile.json full.csv full
```

### 3. **Batch Process All GEDCOM Files**
```bash
schema/batch_process.sh
```
Processes all `.ged` files in `GEDs/` folder and outputs to `data/`.

### 4. **Search the Data**
Use the web interface:
1. Load a JSON file
2. Use the search box to find by name or wikitree ID
3. Export to CSV

## CSV Formats

### Lookup CSV (small, fast lookup)
```
wikitree_id,name
England-1357,David Edward England
Duncan-166,Sarah Clark Duncan
```
**Use for:** Quick ID lookups, index files

### Full CSV (complete genealogy fields)
```
wikitree_id,name,given_name,surname,birth_date,birth_place,death_date,death_place,sex
England-1357,David Edward England,David Edward,England,5 MAY 1920,Alabama,12 JAN 2010,Alabama,M
```
**Use for:** Spreadsheet analysis, merging with other data sources

## Schema Structure

```
genealogy data
├── metadata (project info)
├── people[] (individuals)
│   ├── wikitreeId (e.g., England-1357)
│   ├── name (full, given, surname)
│   ├── birth (date, place)
│   ├── death (date, place)
│   ├── familyAsSpouse
│   └── familyAsChild
├── families[] (marriages/parentage)
│   ├── familyId
│   ├── husband/wife
│   ├── marriage (date, place)
│   └── children[]
├── places[] (geographic locations)
└── sources[] (citations)
```

## Examples

### Example 1: Get all ancestors of England-1357

```bash
# Load Duncans.json and search for "England-1357"
open schema/index.html
# Load data/Duncans.json
# Search box will highlight matches
```

### Example 2: Export Hargroves family to CSV

```bash
# File already processed - just export
php schema/csv_exporter.php data/Hargroves.json Hargroves-export.csv full
# Now use in Excel/Google Sheets
```

### Example 3: Create an index of all people across all files

```bash
# Combine all lookup CSVs
cat data/*-lookup.csv | grep -v "^wikitree_id" | sort | uniq > all-people.csv
# Now you have a master index of 19,000+ ancestors
```

## Notes

- **WikiTree IDs**: Format is `Surname-Number` (e.g., `England-1357`)
- **Dates**: Support GEDCOM format (e.g., "5 MAY 1920", "ABT 1788", "BEF 1900")
- **Places**: Normalized from GEDCOM (e.g., "Lauderdale County, Alabama")
- **No Python needed**: Uses PHP for all processing (your preference!)
- **Standalone HTML**: Works offline, no server required for viewing

## File Statistics

```
Total GEDCOM files: 45
Total parsed successfully: 45 (100%)

Largest files:
- Hargroves: 12,967 people, 4,761 families
- JamesDuncan: 1,388 people, 556 families
- WilliamEnglandIre: 1,221 people, 497 families

All data files in: /genealogy/data/
```

## Troubleshooting

**CSV not showing data?**
- Make sure you selected "full" CSV for complete fields
- Check that the JSON file was parsed correctly (has "people" array)

**Can't open HTML file?**
- Use `open schema/index.html` or drag file to browser
- No web server needed - it works locally

**GEDCOM parse errors?**
- Check GEDCOM file format (should be valid 5.5/5.5.1)
- Look for unmatched tags or encoding issues

---

**Next Steps:**
1. ✅ Schema defined
2. ✅ All GEDCOMs parsed
3. ✅ CSVs generated
4. 👉 Use the web interface to explore your genealogy data!
