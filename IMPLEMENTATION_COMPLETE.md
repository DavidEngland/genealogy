# WikiTree ID Extraction - Implementation Complete

## What's New

Your genealogy system now automatically extracts and manages:
- **WikiTree IDs** (e.g., `England-1357`)
- **FamilySearch IDs** (e.g., `LDLZ-17W`)
- **Ancestry IDs** (6+ digits)
- **Find A Grave IDs** (cemetery records)

## 2 NEW FILES CREATED

### 1. `schema/enhanced_gedcom_parser.php`
- Enhanced PHP parser with ID extraction capability
- Searches GEDCOM records for external service IDs
- Generates ID mapping CSV showing which person maps to which external ID
- Recognizes patterns in NOTE, SOUR, _UID fields

### 2. `schema/process_gedcom.sh`
- Flexible bash processor for single or multiple GEDCOM files
- Command-line options for full control
- Automatic CSV generation
- Verbose reporting of extraction statistics

## Quick Start

Process your GEDCOM file and extract your WikiTree ID:
```bash
schema/process_gedcom.sh -v GEDs/wikiEngland.ged
```

Find your ID:
```bash
grep "England-1357" data/wikiEngland-ids.csv
grep "LDLZ-17W" data/wikiEngland-ids.csv
```

## Output Files Generated

For each GEDCOM file processed, you get:

### 1. `filename.json`
Parsed genealogy data with extracted IDs embedded in each person record

### 2. `filename-ids.csv` ← MOST USEFUL FOR ID EXTRACTION
Maps GEDCOM IDs to external service IDs (WikiTree, FamilySearch, etc.)

Example row:
```
@I1357@,England-1357,David Edward England,wikitree
```

Columns:
- GEDCOM ID (internal reference)
- External ID (WikiTree/FamilySearch/Ancestry/Find A Grave)
- Person Name
- ID Source (which system provided the ID)

### 3. `filename-lookup.csv`
Quick search index (ID + Name)

### 4. `filename-full.csv`
Complete genealogy with all fields

## Key Features

- ✓ Extracts from GEDCOM NOTE fields (where genealogists document sources)
- ✓ Extracts from SOUR (source citations)
- ✓ Extracts from _UID (external unique identifiers)
- ✓ Recognizes WikiTree pattern: `Surname-Number` (e.g., `England-1357`)
- ✓ Recognizes FamilySearch pattern: `XXXX-XXX` (e.g., `LDLZ-17W`)
- ✓ Recognizes Ancestry ID pattern: 6+ digits
- ✓ Recognizes Find A Grave pattern
- ✓ Shows extraction statistics (how many of each type found)
- ✓ Smart fallback when external ID not found
- ✓ Process single file or batch process all files
- ✓ Full control with command-line options

## Common Commands

### Process single file:
```bash
schema/process_gedcom.sh GEDs/myfile.ged
```

### Process multiple files:
```bash
schema/process_gedcom.sh GEDs/file1.ged GEDs/file2.ged GEDs/file3.ged
```

### Process all files in GEDs/ folder:
```bash
schema/process_gedcom.sh --all
```

### Verbose mode (show extraction statistics):
```bash
schema/process_gedcom.sh -v GEDs/myfile.ged
```

### Show help:
```bash
schema/process_gedcom.sh --help
```

### View ID mappings:
```bash
head -20 data/*-ids.csv
```

### Find your WikiTree ID:
```bash
grep "England-1357" data/*-ids.csv
```

### Find your FamilySearch ID:
```bash
grep "LDLZ-17W" data/*-ids.csv
```

### Count WikiTree IDs extracted:
```bash
grep ',wikitree' data/*-ids.csv | wc -l
```

### Create master index:
```bash
cat data/*-ids.csv | grep -v "^GEDCOM" > all-ids.csv
```

## File Structure

```
genealogy/
├── schema/
│   ├── enhanced_gedcom_parser.php ....... NEW: Parser with ID extraction
│   ├── process_gedcom.sh ............... NEW: Flexible processor
│   ├── WIKITREE_ID_GUIDE.md ............ NEW: Complete documentation
│   ├── gedcom_parser.php .............. (original parser, still available)
│   └── [other schema files]
│
├── data/
│   ├── *.json ........................... Parsed genealogy
│   ├── *-ids.csv ....................... NEW: ID mappings
│   ├── *-lookup.csv ................... Quick search
│   └── *-full.csv ..................... Complete data
│
├── IMPLEMENTATION_COMPLETE.md .......... This file
└── WIKITREE_ID_EXTRACTION.txt ......... Feature description
```

## What Happens When You Process a File

1. **Read**: Parser reads GEDCOM file
2. **Search**: Searches for ID patterns in:
   - NOTE fields (where genealogists write references)
   - SOUR (source citations)
   - _UID (external IDs)
   - Custom genealogy fields
3. **Extract**: Recognizes patterns and extracts:
   - WikiTree: `England-1357`
   - FamilySearch: `LDLZ-17W`
   - Ancestry: `123456789`
   - Find A Grave: `12345678`
4. **Map**: Creates ID mapping CSV showing:
   - GEDCOM ID → External ID
   - Person Name
   - Which services provided the ID
5. **Report**: Shows statistics:
   - Total people found
   - How many WikiTree IDs extracted
   - How many FamilySearch IDs extracted
   - How many fallback IDs generated

## Next Steps

### 1. Process your main GEDCOM file:
```bash
schema/process_gedcom.sh GEDs/wikiEngland.ged
```

### 2. Verify your ID is captured:
```bash
grep "England-1357" data/wikiEngland-ids.csv
grep "LDLZ-17W" data/wikiEngland-ids.csv
```

### 3. Process all files:
```bash
schema/process_gedcom.sh --all
```

### 4. Create master index of all people and IDs:
```bash
cat data/*-ids.csv | grep -v "^GEDCOM" > all-ancestors.csv
```

### 5. Use in analysis:
```bash
open data/*-lookup.csv
```
(Import into Excel/Google Sheets)

## Documentation

For more information, see:
- `schema/WIKITREE_ID_GUIDE.md` - Complete guide with examples
- `WIKITREE_ID_EXTRACTION.txt` - Full feature description
- `schema/process_gedcom.sh --help` - Command-line help
- `schema/README.md` - Technical reference

## Ready to Extract Your IDs!

Start with:
```bash
schema/process_gedcom.sh -v GEDs/wikiEngland.ged
```

---

**Created**: Today
**Status**: Ready for use
**Testing**: Validated on test.ged (found England-1357 ✓)
