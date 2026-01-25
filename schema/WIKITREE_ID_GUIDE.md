# WikiTree/FamilySearch ID Extraction Guide

## Overview

Your genealogy system now automatically extracts and maps external genealogy service IDs from GEDCOM files:

- **WikiTree IDs** (e.g., `England-1357`)
- **FamilySearch IDs** (e.g., `LDLZ-17W`)
- **Ancestry IDs** (e.g., `123456789`)
- **Find A Grave IDs** (e.g., `12345678`)

## What Gets Extracted

The enhanced parser searches GEDCOM records for:

1. **WikiTree IDs** - Pattern: `Surname-Number`
2. **FamilySearch IDs** - Pattern: `XXXX-XXX` (4 letters/numbers, dash, 2-3 letters/numbers)
3. **Ancestry IDs** - Pattern: 6+ digit numbers
4. **Find A Grave IDs** - Citation references and notes

IDs are extracted from:
- NOTE fields (genealogist notes, citations)
- SOUR (source references)
- _UID fields (external unique identifiers)
- Custom fields with ID patterns

## Tools

### Enhanced GEDCOM Parser

**File:** `schema/enhanced_gedcom_parser.php`

Parses GEDCOM and extracts external service IDs.

#### Usage

```bash
php schema/enhanced_gedcom_parser.php <input.ged> [output.json] [id_mapping.csv]
```

#### Examples

Single file with ID mapping:
```bash
php schema/enhanced_gedcom_parser.php GEDs/myfile.ged data/myfile.json data/myfile-ids.csv
```

Verbose output:
```bash
php schema/enhanced_gedcom_parser.php GEDs/myfile.ged data/myfile.json data/myfile-ids.csv -v
```

### Flexible GEDCOM Processor

**File:** `schema/process_gedcom.sh`

Bash wrapper for processing single or multiple GEDCOM files with full control.

#### Usage

```bash
schema/process_gedcom.sh [OPTIONS] [file1.ged] [file2.ged] ...
```

#### Options

| Option | Description |
|--------|-------------|
| `-h, --help` | Show help message |
| `-o, --output DIR` | Set output directory (default: `data/`) |
| `-v, --verbose` | Show detailed processing information |
| `--no-csv` | Skip CSV export (JSON only) |
| `--all` | Process all .ged files in GEDs/ folder |

#### Examples

**Process single file:**
```bash
schema/process_gedcom.sh GEDs/myfile.ged
```

**Process multiple files:**
```bash
schema/process_gedcom.sh GEDs/file1.ged GEDs/file2.ged GEDs/file3.ged
```

**Process all files:**
```bash
schema/process_gedcom.sh --all
```

**Verbose mode:**
```bash
schema/process_gedcom.sh -v GEDs/myfile.ged
```

**Custom output directory:**
```bash
schema/process_gedcom.sh -o /path/to/output GEDs/myfile.ged
```

**JSON only (no CSV):**
```bash
schema/process_gedcom.sh --no-csv GEDs/myfile.ged
```

## Output Files

For each GEDCOM file processed, the system generates:

### 1. JSON File
**Format:** `data/filename.json`

Contains all parsed genealogy data with extracted IDs embedded in each person record:

```json
{
  "wikitreeId": "England-1357",
  "familySearchId": "LDLZ-17W",
  "ancestryId": "123456789",
  "name": { "full": "David Edward England", ... },
  ...
}
```

### 2. ID Mapping CSV
**Format:** `data/filename-ids.csv`

Maps GEDCOM IDs to resolved external IDs with sources.

**Columns:**
- `GEDCOM ID` - Internal GEDCOM ID (@I123@)
- `WikiTree ID` - Resolved WikiTree ID (or generated)
- `Name` - Person's full name
- `ID Sources` - Which services provided the ID (wikitree, familysearch, ancestry, findagrave)

**Example:**
```
GEDCOM ID,WikiTree ID,Name,ID Sources
@I1@,England-1357,David Edward England,wikitree
@I2@,Duncan-166,Sarah Clark Duncan,familysearch
@I3@,Smith-4567,John Smith,
```

### 3. Lookup CSV
**Format:** `data/filename-lookup.csv`

Quick index for searching.

```
wikitree_id,name
England-1357,David Edward England
Duncan-166,Sarah Clark Duncan
```

### 4. Full CSV
**Format:** `data/filename-full.csv`

Complete genealogy data with all fields:

```
wikitree_id,name,given_name,surname,birth_date,birth_place,death_date,death_place,sex
England-1357,David Edward England,David Edward,England,5 MAY 1920,Alabama,12 JAN 2010,Alabama,M
```

## Extraction Statistics

The processor reports extraction statistics:

```
Statistics:
  People: 801
  Families: 266
  IDs extracted: WikiTree=45, FamilySearch=12, Ancestry=8
  IDs generated: 736
```

This tells you:
- **People:** Total individuals in the GEDCOM
- **Families:** Total family relationships
- **IDs extracted:** How many external IDs were found
- **IDs generated:** How many fallback IDs were created (from surname + number)

## Workflow Examples

### Example 1: Extract WikiTree IDs for Your Family

Your personal ID is `England-1357` and FamilySearch ID is `LDLZ-17W`.

```bash
# Process your GEDCOM file
schema/process_gedcom.sh GEDs/wikiEngland.ged

# Check extracted IDs
head -20 data/wikiEngland-ids.csv

# View your record
grep "England-1357" data/wikiEngland-ids.csv
```

Result:
```
@I1357@,England-1357,David Edward England,wikitree
```

### Example 2: Process Multiple Family GEDCOM Files

```bash
# Process three family lines
schema/process_gedcom.sh \
  GEDs/wikiEngland.ged \
  GEDs/Duncans.ged \
  GEDs/Hargroves.ged

# Combine all ID mappings into master file
cat data/*-ids.csv | grep -v "^GEDCOM" > data/master-id-mapping.csv

# View summary
wc -l data/master-id-mapping.csv
```

### Example 3: Find All WikiTree IDs in a File

```bash
# Process file and extract WikiTree IDs only
schema/process_gedcom.sh -v GEDs/myfile.ged

# Extract WikiTree IDs
grep 'wikitree' data/myfile-ids.csv | cut -d, -f2 | sort | uniq > wikitree-ids.txt

# Show count
wc -l wikitree-ids.txt
```

### Example 4: Compare FamilySearch vs WikiTree Coverage

```bash
# Process file
schema/process_gedcom.sh --all

# Combine all mappings
cat data/*-ids.csv | grep -v "^GEDCOM" > all-ids.csv

# WikiTree only
grep ',wikitree' all-ids.csv | wc -l

# FamilySearch only
grep ',familysearch' all-ids.csv | wc -l

# Both
awk -F',' '$4 ~ /wikitree/ && $4 ~ /familysearch/' all-ids.csv | wc -l
```

## ID Extraction Patterns

The parser automatically detects these patterns:

### WikiTree
- `England-1357`
- `WikiTree: England-1357`
- `wikitree England-1357`
- Referenced in notes and sources

### FamilySearch
- `LDLZ-17W`
- `FS: LDLZ-17W`
- `FamilySearch LDLZ-17W`
- Any pattern: 4 alphanumeric - 2-3 alphanumeric

### Ancestry
- `123456789` (6+ digits)
- `Ancestry: 123456789`
- In citations and sources

### Find A Grave
- `12345678` (in specific context)
- `FaG: 12345678`
- Cemetery references

## Troubleshooting

### No IDs Extracted

**Issue:** ID Sources column is empty

**Causes:**
- IDs not stored in GEDCOM (check original source file)
- IDs in non-standard format
- Custom fields not recognized

**Solution:**
- Manually add IDs to GEDCOM NOTE fields
- Use pattern: `WikiTree: England-1357` or `FamilySearch: LDLZ-17W`
- Re-process file

### Wrong ID Detected

**Issue:** Parser picked up wrong ID format

**Solution:**
- Check the extracted ID in `-ids.csv`
- Verify GEDCOM source contains the correct ID
- Patterns can be customized in the parser (lines 37-50 of enhanced_gedcom_parser.php)

### Large GEDCOM File Processing

**Issue:** Parser runs slowly with 10,000+ people

**Solution:**
- Use `--no-csv` flag to skip CSV export
- Process in chunks if needed
- Verbose mode shows progress

## Integrating with Your Workflow

### Using CSV in Excel/Google Sheets

1. Export lookup or full CSV:
   ```bash
   schema/process_gedcom.sh myfile.ged
   ```

2. Open in Excel/Google Sheets:
   ```bash
   open data/myfile-lookup.csv
   ```

3. Search and filter by:
   - WikiTree ID
   - Name
   - Birth/Death dates
   - Sources

### Creating Master Index

Combine all GEDCOM files into one searchable index:

```bash
# Process all files
schema/process_gedcom.sh --all

# Create master index with WikiTree IDs
echo "wikitree_id,name,source_file" > master-index.csv
for file in data/*-ids.csv; do
  base=$(basename "$file" -ids.csv)
  grep -v "^GEDCOM" "$file" | cut -d, -f2,3 | sed "s/$/,$base/" >> master-index.csv
done

# Sort and deduplicate
sort -u master-index.csv > master-index-final.csv
```

### Matching External Databases

Use ID mappings to match against external genealogy sites:

```bash
# Extract WikiTree IDs from a file
grep ',wikitree' data/myfile-ids.csv | cut -d, -f2 > wikitree-ids.txt

# Cross-reference with another source
grep -f wikitree-ids.txt external-database.txt
```

## Command Reference

| Task | Command |
|------|---------|
| Process single file | `schema/process_gedcom.sh GEDs/file.ged` |
| Process multiple files | `schema/process_gedcom.sh GEDs/f1.ged GEDs/f2.ged` |
| Process all files | `schema/process_gedcom.sh --all` |
| Verbose output | `schema/process_gedcom.sh -v GEDs/file.ged` |
| Custom output dir | `schema/process_gedcom.sh -o /path GEDs/file.ged` |
| JSON only | `schema/process_gedcom.sh --no-csv GEDs/file.ged` |
| Show help | `schema/process_gedcom.sh --help` |
| View ID mappings | `head -20 data/*-ids.csv` |
| Find your ID | `grep 'England-1357' data/*-ids.csv` |
| Count WikiTree IDs | `grep ',wikitree' data/*-ids.csv \| wc -l` |
| Export IDs only | `cut -d, -f2 data/*-ids.csv \| grep -E '^[A-Z]' \| sort` |

## Next Steps

1. **Extract IDs from your GEDCOM files:**
   ```bash
   schema/process_gedcom.sh --all
   ```

2. **Check your personal records:**
   ```bash
   grep "England-1357" data/*-ids.csv
   grep "LDLZ-17W" data/*-ids.csv
   ```

3. **Create master index:**
   ```bash
   cat data/*-ids.csv | grep -v "^GEDCOM" > all-ids.csv
   ```

4. **Use in spreadsheet application:**
   ```bash
   open data/Duncans-ids.csv
   ```

---

For more information, see [schema/README.md](README.md) and [QUICKSTART.md](QUICKSTART.md)
