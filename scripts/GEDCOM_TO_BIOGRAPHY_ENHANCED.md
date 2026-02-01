# Enhanced GEDCOM to Biography Converter

Converts GEDCOM files to WikiTree-style Markdown biographies and schema.json-compliant JSON with support for multi-database ID extraction, biographical section parsing, and rich formatting.

## Features

### 1. Multi-Database ID Extraction

Automatically extracts genealogy service IDs from GEDCOM `WWW` and `NOTE` fields:

- **WikiTree**: `wiki/ProfileID` or `[[ProfileID|Name]]`
- **FamilySearch**: `ark:/61903/1:1:ID` patterns
- **Ancestry**: `ancestry.com` URLs or `Ancestry: ID` notation
- **Find A Grave**: `findagrave.com` URLs or `FAG: ID` notation

IDs are stored in the `externalIds` field and exported to reference databases.

### 2. Biographical Section Parsing

Intelligently parses NOTE fields containing WikiTree-formatted sections:

```
== Biography ==
== Military Service ==
=== Land Grants ===
== Sources ==
```

Preserves section structure when exporting to Markdown or JSON, maintaining rich biographical organization.

### 3. JSON Output with Schema Compliance

Generates `schema.json`-compliant JSON output with:

- Person records with structured name/date/place fields
- Family relationships and cross-references
- Parsed biographical sections as structured data
- External database IDs for interoperability
- Metadata timestamps and source tracking

### 4. WikiTree Link Conversion

Automatically converts bold name references (`'''Name'''`) to WikiTree links (`'''[[ProfileID|Name]]'''`) when matching individuals exist in the GEDCOM file.

### 5. Reference Database Export

Generates both JSON and CSV reference files containing:

- WikiTree IDs
- All extracted external service IDs (FamilySearch, Ancestry, Find A Grave)
- Birth/death dates and places
- Summary name information

## Usage

### Basic Markdown Output (Single Person)

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --person @I123@ \
  --out Hargroves.md
```

### Batch Markdown Output (All People)

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --out sources/Hargroves
```

Generates:
- Individual `.md` files for each person (named by WikiTree ID or sanitized name)
- `reference-database.json` - Structured reference data
- `reference-database.csv` - Spreadsheet-friendly ID mapping

### JSON Output (Entire GEDCOM)

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --json \
  --out sources/hargroves.json
```

Generates single JSON file with all people and families.

### JSON Output (Single Person)

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --person @I123@ \
  --json \
  --out /tmp/person.json
```

## Output Examples

### Markdown Output

```markdown
== Biography ==

'''John Washington Hargrove''' (1750 – 1838) was born on ABT 1750 in Amherst, Virginia.

He was the son of '''[[Hargrove-261|Samuel Hargrave]]''' and '''Martha Cheadle'''.

=== Marriage ===
Married '''[[Ball-555|Mary (Ball) Hargrove]]''' on 31 Jan. 1733 in Middlesex Co., VA.

=== Children ===
Children (from linked family records):
* '''[[Hartgrove-97|James B Hartgrove]]''' (b. 1776)
* '''[[Hargrove-273|William Hargrove]]'''

=== Death ===
Died on ABT 1838 in Pulaski, Kentucky, United States.

== Sources ==
* Information synthesized from GEDCOM file import.
```

### JSON Output Structure

```json
{
  "metadata": {
    "generated": "2026-01-28T21:59:05+00:00",
    "source": "GEDCOM to Biography Converter (Enhanced)",
    "gedcomVersion": "5.5.1"
  },
  "people": [
    {
      "wikitreeId": "Hargrove-261",
      "gedcomId": "@I2@",
      "name": {
        "full": "John Washington Hargrove",
        "given": "John",
        "surname": "Hargrove"
      },
      "sex": "M",
      "externalIds": {
        "wikitree": "Hargrove-261"
      },
      "birth": {
        "date": "ABT 1750",
        "place": "Amherst, Virginia"
      },
      "death": {
        "date": "ABT 1838",
        "place": "Pulaski, Kentucky, United States"
      },
      "familyAsSpouse": ["@F1@"],
      "familyAsChild": "@F2@",
      "biographicalSections": {
        "Biography": ["John Hargrove served as a Minuteman..."],
        "Military Service": ["Rank: Soldier", "Unit: Amherst County Militia..."]
      },
      "notes": "Full NOTE text with preserved formatting..."
    }
  ],
  "families": [
    {
      "familyId": "@F1@",
      "husband": "@I2@",
      "wife": "@I3@",
      "marriage": {
        "date": "31 Jan. 1733",
        "place": "Middlesex Co., VA"
      },
      "children": ["@I1@", "@I4@"]
    }
  ]
}
```

### Reference Database CSV

```csv
"WikiTree ID","Name","Given Name","Surname","Sex","FamilySearch ID","Ancestry ID","Find A Grave ID","Birth Date","Birth Place","Death Date","Death Place"
"Hargrove-261","John Washington Hargrove","John","Hargrove","M","","","","ABT 1750","Amherst, Virginia","ABT 1838","Pulaski, Kentucky, United States"
"Ball-555","Mary Ball","Mary","Ball","F","","","",1749,"Henrico County, Colony of Virginia",1813,"Pulaski, Pulaski County, Kentucky, United States"
```

## GEDCOM Structure Examples

### WikiTree ID from WWW Field

```
0 @I2@ INDI
1 NAME John /Washington/ /Hargrove/
1 BIRT
2 DATE ABT 1750
2 PLAC Amherst, Virginia
1 WWW https://www.WikiTree.com/wiki/Hargrove-261
```

### Multi-Database IDs in NOTE Fields

```
0 @I5@ INDI
1 NAME Sarah /Jones/
1 NOTE FamilySearch: F2K4-Q8M
1 NOTE Ancestry: 1234567
1 NOTE Find A Grave: 98765432
```

### Biographical Sections in NOTE

```
0 @I2@ INDI
1 NOTE == Biography ==
2 CONC John Hargrove served as a Minuteman from Amherst Co., VA...
2 CONC He received a Treasury Land Warrant for 2000 acres...
1 NOTE == Military Service ==
2 CONC * VIRGINIA Rank: SOLDIER
2 CONC * Unit: Amherst County Militia
1 NOTE === Land Grants ===
2 CONC * 2000 acres in Kentucky (Treasury Land Warrant)
1 NOTE == Sources ==
2 CONC * "Amherst County Story" by Alfred Percy, p. 47
```

## Schema Alignment

Output JSON conforms to `schema/schema.json` with these properties:

- `metadata`: Project info and timestamps
- `people`: Array of person records with:
  - `wikitreeId`: WikiTree identifier
  - `externalIds`: Map of service-specific IDs
  - `name`: Full, given, and surname
  - `birth`/`death`: Events with date and place
  - `familyAsSpouse`/`familyAsChild`: Family relationship IDs
  - `biographicalSections`: Parsed NOTE field structure
  - `notes`: Full NOTE text (raw)

- `families`: Array of family records with:
  - `familyId`: GEDCOM family reference
  - `husband`/`wife`: Spouse IDs
  - `children`: Child ID list
  - `marriage`: Event with date and place

## Reference Database Files

### reference-database.json

Structured JSON with all people having WikiTree IDs:

```json
{
  "metadata": {
    "generated": "2026-01-28T21:59:05+00:00",
    "source": "GEDCOM to Biography Converter"
  },
  "people": [
    {
      "wikitreeId": "Hargrove-261",
      "gedcomId": "@I2@",
      "name": "John Washington Hargrove",
      "givenName": "John",
      "surname": "Hargrove",
      "sex": "M",
      "allIds": {
        "wikitree": "Hargrove-261",
        "familysearch": "F2K4-Q8M"
      },
      "birth": {"date": "ABT 1750", "place": "Amherst, Virginia"},
      "death": {"date": "ABT 1838", "place": "Pulaski, Kentucky, United States"}
    }
  ]
}
```

### reference-database.csv

Spreadsheet-friendly format with columns:

- WikiTree ID
- Name
- Given Name
- Surname
- Sex
- FamilySearch ID
- Ancestry ID
- Find A Grave ID
- Birth Date
- Birth Place
- Death Date
- Death Place

## Command Line Options

| Option | Description | Example |
|--------|-------------|---------|
| `--input FILE` | Path to GEDCOM file (required) | `--input GEDs/Hargroves.ged` |
| `--person ID` | Specific individual ID | `--person @I123@` |
| `--out PATH` | Output file or directory | `--out sources/` |
| `--json` | Output JSON instead of Markdown | `--json` |
| `--help` | Show usage information | `--help` |

## Batch Processing Workflow

### Step 1: Export GEDCOM from WikiTree

Save your research GEDCOM with:
- WikiTree URLs in `WWW` fields
- Rich biographical notes with `== Section ==` headers
- Multiple genealogy service IDs in NOTE fields

### Step 2: Process All Individuals

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/MyResearch.ged \
  --out sources/MyResearch
```

Output:
- Individual `.md` files for each person
- `reference-database.json` for programmatic use
- `reference-database.csv` for spreadsheet/database import

### Step 3: Review and Refine

- Check generated Markdown biographies
- Verify cross-links (WikiTree IDs recognized)
- Export reference database to spreadsheet for validation

### Step 4: Export to JSON for Integration

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/MyResearch.ged \
  --json \
  --out data/genealogy-complete.json
```

Use JSON output for:
- Integration with genealogy databases
- Building web interfaces
- Data analysis and validation
- Backup/archive purposes

## Error Handling

| Error | Cause | Solution |
|-------|-------|----------|
| `Input file not found` | GEDCOM path incorrect | Verify GED file path |
| `Person ID not found` | Invalid `@I###@` reference | Check GEDCOM for correct ID |
| `Unable to create output directory` | Permission issue | Ensure write access to output path |
| `Failed to write output` | Disk full or permission error | Check disk space and permissions |

## Implementation Notes

- **ID Extraction**: Uses regex patterns to find multiple database IDs in WWW and NOTE fields
- **Section Parsing**: Detects `== ===` markers to split biographical content into sections
- **WikiTree Linking**: Converts bold names to WikiTree links when mapping available
- **UTF-8 Safe**: All JSON output is UTF-8 encoded and unescaped for readability

## Performance

- GEDCOM with 100 people: ~1-2 seconds
- Output generation: Primarily I/O bound
- Memory: ~50MB for typical 500-person genealogy

## Compatibility

- PHP 7.1+
- GEDCOM 5.5 and 5.5.1
- UTF-8, ANSI character sets
- Windows, macOS, Linux
