# GEDCOM to Biography Converter - Quick Start Guide

## What's New (Enhanced Version)

The enhanced GEDCOM to Biography converter now supports:

### ✅ Multi-Database ID Extraction
- Automatically extracts WikiTree, FamilySearch, Ancestry, and Find A Grave IDs
- Stores IDs from `WWW` and `NOTE` fields in structured `externalIds` object
- Exports ID mapping to reference databases (JSON and CSV)

### ✅ Biographical Section Parsing
- Parses WikiTree-formatted sections from NOTE fields: `== Biography ==`, `=== Subsection ===`
- Preserves section hierarchy in both Markdown and JSON output
- Maintains rich biographical structure with land grants, military service, etc.

### ✅ JSON Output with Schema Compliance
- Generate `schema.json`-compliant JSON for all or individual GEDCOM entries
- Includes structured biographical sections, dates, places, and relationships
- Perfect for database integration, web development, or archival purposes

### ✅ WikiTree Link Conversion
- Automatically converts bold name references to WikiTree links
- `'''Name'''` → `'''[[ProfileID|Name]]'''` when profile exists

### ✅ Reference Database Export
- Generates both JSON and CSV reference files
- Includes all extracted external service IDs
- Useful for data validation, spreadsheet import, or database lookups

---

## Quick Examples

### Generate Markdown for Single Person
```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/MyFamily.ged \
  --person @I123@ \
  --out output/MyPerson.md
```

### Generate Markdown for All People
```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/MyFamily.ged \
  --out output/biographies
```
Creates: `output/biographies/Hargrove-261.md`, etc. + reference databases

### Generate JSON for Schema Integration
```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/MyFamily.ged \
  --json \
  --out output/genealogy.json
```

### Generate JSON for Single Person
```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/MyFamily.ged \
  --person @I123@ \
  --json \
  --out /tmp/person.json
```

---

## GEDCOM Preparation Tips

For best results, structure your GEDCOM with:

### 1. WikiTree URLs in WWW field
```
0 @I1@ INDI
1 WWW https://www.WikiTree.com/wiki/Hargrove-261
```

### 2. Multi-database IDs in NOTE fields
```
1 NOTE FamilySearch: F2K4-Q8M
1 NOTE Ancestry: 1234567
1 NOTE Find A Grave: 98765432
```

### 3. Biographical Sections (WikiTree format)
```
1 NOTE == Biography ==
2 CONC Text describing life events...
1 NOTE === Military Service ===
2 CONC Service details...
1 NOTE === Land Grants ===
2 CONC Grant information...
1 NOTE == Sources ==
2 CONC Bibliographic citations...
```

---

## Output Formats

### Markdown (.md)
- **For**: Human-readable biographies, WikiTree import, editing
- **Contains**: Sections, cross-links, formatted narrative
- **Example**: `Hargrove-261.md`

### JSON (.json)
- **For**: Data integration, web applications, analysis
- **Contains**: Structured data, relationships, external IDs
- **Schemas**: Conforms to `schema/schema.json`

### Reference Databases
- **reference-database.json**: Full structured reference with all IDs
- **reference-database.csv**: Spreadsheet-friendly format for analysis

---

## Understanding the Output

### Markdown Biography Example
```markdown
== Biography ==
'''John Washington Hargrove''' (1750 – 1838) was born on ABT 1750 in Amherst, Virginia.

=== Military Service ===
* VIRGINIA Rank: SOLDIER
* Unit: Amherst County Militia

== Sources ==
* "Amherst County Story" by Alfred Percy, p. 47
```

### JSON Structure Example
```json
{
  "wikitreeId": "Hargrove-261",
  "name": {
    "full": "John Washington Hargrove",
    "given": "John",
    "surname": "Hargrove"
  },
  "externalIds": {
    "wikitree": "Hargrove-261",
    "familysearch": "F2K4-Q8M"
  },
  "biographicalSections": {
    "Biography": [...],
    "Military Service": [...],
    "Sources": [...]
  }
}
```

---

## Reference Database CSV

Headers:
```
WikiTree ID | Name | Given Name | Surname | Sex | FamilySearch ID | Ancestry ID | Find A Grave ID | Birth Date | Birth Place | Death Date | Death Place
```

Use for:
- Validating ID extraction
- Importing to spreadsheet or database
- Cross-referencing multiple genealogy services
- Identifying missing IDs

---

## Common Workflows

### 1. Batch Process Entire GEDCOM
```bash
# Step 1: Generate all biographies
php scripts/gedcom_to_biography.php \
  --input GEDs/research.ged \
  --out biographies/

# Step 2: Review reference database CSV
open biographies/reference-database.csv

# Step 3: Export to JSON for integration
php scripts/gedcom_to_biography.php \
  --input GEDs/research.ged \
  --json \
  --out data/genealogy.json
```

### 2. Update Single Person Biography
```bash
# Find the person's ID in reference database CSV
# e.g., Hargrove-261 = @I2@

# Generate latest markdown
php scripts/gedcom_to_biography.php \
  --input GEDs/research.ged \
  --person @I2@ \
  --out biographies/Hargrove-261.md

# Edit in text editor, copy to WikiTree
```

### 3. Cross-Check Multiple Genealogy Services
```bash
# Generate reference database with all IDs
php scripts/gedcom_to_biography.php \
  --input GEDs/research.ged \
  --out biographies/

# Open reference-database.csv in spreadsheet
# Filter for empty FamilySearch/Ancestry/Find A Grave columns
# Research and add missing IDs to GEDCOM
```

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Input file not found" | Check GEDCOM path is correct |
| "Person ID not found" | Verify @I###@ reference in GEDCOM |
| No JSON output | Use `--json` flag |
| Missing IDs in reference | Ensure IDs are in WWW or NOTE fields in GEDCOM |
| Sections not parsed | Verify NOTE uses `== Header ==` format |

---

## File Locations

```
genealogy/
├── scripts/
│   ├── gedcom_to_biography.php          ← Main enhanced script
│   └── GEDCOM_TO_BIOGRAPHY_ENHANCED.md  ← Full documentation
├── GEDs/
│   └── *.ged                            ← Input GEDCOM files
└── schema/
    └── schema.json                      ← JSON schema reference
```

---

## Next Steps

1. **Prepare GEDCOM** with WikiTree URLs and biographical sections
2. **Run converter** with appropriate flags for your use case
3. **Review output** in Markdown or JSON format
4. **Validate IDs** using reference database
5. **Integrate or publish** depending on your workflow

For detailed feature documentation, see `GEDCOM_TO_BIOGRAPHY_ENHANCED.md`

Quickstart stub.
