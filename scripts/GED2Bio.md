# GEDCOM to WikiTree Biography Converter - Documentation

## Overview

This PHP script converts GEDCOM (Genealogical Data Communication) files into WikiTree-style biographical markdown documents or structured JSON data. It extracts genealogical information, parses biographical sections, and creates formatted outputs suitable for WikiTree profiles or data analysis.

## Key Features

### 1. **Multi-Format Output**

- **Markdown**: WikiTree-compatible biography pages with proper formatting
- **JSON**: Schema-compliant structured data for programmatic use
- **Reference Database**: CSV and JSON exports with cross-platform IDs

### 2. **Multi-Database ID Extraction**

Automatically detects and extracts profile IDs from:

- **WikiTree**: `WikiTree-ID` format (e.g., `Smith-123`)
- **FamilySearch**: ARK identifiers or FS IDs
- **Ancestry**: Tree/person identifiers
- **Find A Grave**: Memorial numbers

### 3. **Intelligent Biography Generation**

- Parses existing WikiTree-formatted sections from GEDCOM notes
- Generates structured biographies with opening statements
- Creates family relationship sections (parents, spouses, children)
- Converts name references to WikiTree profile links

### 4. **Batch Processing**

- Process single individuals or entire family trees
- Automatically names output files using WikiTree IDs
- Creates organized directory structures

## Installation & Requirements

### Requirements

- **PHP 7.4+** (uses strict types, typed parameters)
- Command-line access
- Write permissions for output directories

### No External Dependencies

The script uses only PHP built-in functions - no composer packages needed.

## Usage

### Basic Syntax

```bash
php scripts/gedcom_to_biography.php --input <file.ged> [OPTIONS]
```

### Command-Line Options

|Option           |Required|Description                                   |
|-----------------|--------|----------------------------------------------|
|`--input FILE`   |Yes     |Path to GEDCOM file                           |
|`--person @I123@`|No      |Specific individual ID (exports single person)|
|`--out PATH`     |No      |Output file/directory (default: `sources`)    |
|`--json`         |No      |Output JSON instead of Markdown               |
|`--help`         |No      |Display usage information                     |

### Usage Examples

#### 1. Single Person to Markdown

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --person @I123@ \
  --out Hargroves.md
```

**Output**: Single markdown file `Hargroves.md` with biography

#### 2. All Individuals to Directory

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --out sources/Hargroves
```

**Output**:

- One `.md` file per person in `sources/Hargroves/`
- Files named using WikiTree IDs or sanitized names
- `reference-database.json` and `reference-database.csv`

#### 3. JSON Schema Output

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --json \
  --out sources/hargroves.json
```

**Output**: Single JSON file with all genealogical data

#### 4. Single Person JSON

```bash
php scripts/gedcom_to_biography.php \
  --input GEDs/Hargroves.ged \
  --person @I135@ \
  --json \
  --out John-Smith-123.json
```

## GEDCOM Parsing

### Supported GEDCOM Tags

#### Individual (INDI) Records

- `NAME` - Full name
- `GIVN` - Given names
- `SURN` - Surname
- `SEX` - Gender (M/F/U)
- `BIRT` - Birth event (with DATE, PLAC)
- `DEAT` - Death event (with DATE, PLAC)
- `CHR` - Christening event
- `BURI` - Burial event
- `FAMS` - Family as spouse
- `FAMC` - Family as child
- `NOTE` - Biographical notes
- `WWW` - Web links (used for ID extraction)

#### Family (FAM) Records

- `HUSB` - Husband reference
- `WIFE` - Wife reference
- `CHIL` - Child references
- `MARR` - Marriage event (with DATE, PLAC)

### ID Extraction Patterns

The script uses regex patterns to extract external database IDs:

```php
// WikiTree
WikiTree/Smith-123
wiki/Smith-456
[[WikiTree-ID|Name]]

// FamilySearch
ark:/61903/1:1:ABC123
FS: XYZ789

// Ancestry
ancestry.com/trees/123456
Ancestry: 789012

// Find A Grave
findagrave.com/memorial/12345678
FAG: 87654321
```

## Output Formats

### Markdown Biography Structure

```markdown
== Biography ==

'''John Smith''' (1850 – 1920) was born on 15 January 1850 in Boston, Massachusetts.
He was the son of '''[[Smith-100|James Smith]]''' and '''[[Jones-50|Mary Jones]]'''.

=== Marriage ===
Married '''[[Brown-75|Sarah Brown]]''' on 10 June 1872 in New York.

=== Children ===
Children (from linked family records):
* '''[[Smith-200|Robert Smith]]''' (b. 1873)
* '''[[Smith-201|Elizabeth Smith]]''' (b. 1875)

=== Death ===
Died on 20 December 1920 in Chicago, Illinois.

== Sources ==
* Information synthesized from GEDCOM file import.
```

### JSON Schema Structure

```json
{
  "metadata": {
    "generated": "2026-01-28T12:00:00+00:00",
    "source": "GEDCOM to Biography Converter (Enhanced)",
    "gedcomVersion": "5.5.1"
  },
  "people": [
    {
      "wikitreeId": "Smith-123",
      "gedcomId": "@I135@",
      "name": {
        "full": "John Smith",
        "given": "John",
        "surname": "Smith"
      },
      "sex": "M",
      "externalIds": {
        "wikitree": "Smith-123",
        "familysearch": "ABC123",
        "ancestry": "456789",
        "findagrave": "12345678"
      },
      "birth": {
        "date": "15 JAN 1850",
        "place": "Boston, Massachusetts"
      },
      "death": {
        "date": "20 DEC 1920",
        "place": "Chicago, Illinois"
      },
      "familyAsSpouse": ["@F100@"],
      "familyAsChild": "@F50@",
      "biographicalSections": {
        "Biography": ["..."],
        "Sources": ["..."]
      },
      "notes": "Additional biographical information..."
    }
  ],
  "families": [
    {
      "familyId": "@F100@",
      "husband": "@I135@",
      "wife": "@I140@",
      "children": ["@I200@", "@I201@"],
      "marriage": {
        "date": "10 JUN 1872",
        "place": "New York"
      }
    }
  ]
}
```

### Reference Database Files

#### JSON Format (`reference-database.json`)

```json
{
  "metadata": {
    "generated": "2026-01-28T12:00:00+00:00",
    "source": "GEDCOM to Biography Converter"
  },
  "people": [
    {
      "wikitreeId": "Smith-123",
      "gedcomId": "@I135@",
      "name": "John Smith",
      "givenName": "John",
      "surname": "Smith",
      "sex": "M",
      "allIds": {
        "wikitree": "Smith-123",
        "familysearch": "ABC123"
      },
      "birth": {
        "date": "15 JAN 1850",
        "place": "Boston, Massachusetts"
      },
      "death": {
        "date": "20 DEC 1920",
        "place": "Chicago, Illinois"
      }
    }
  ]
}
```

#### CSV Format (`reference-database.csv`)

Spreadsheet-compatible with columns:

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

## Key Functions

### Core Functions

#### `parseGedcom(array $lines): array`

Parses GEDCOM file lines into structured arrays.

**Returns**: `[$individuals, $families]`

#### `extractDatabaseIds(string $text): array`

Extracts genealogy database IDs from text.

**Returns**: Array with keys: `wikitree`, `familysearch`, `ancestry`, `findagrave`

#### `parseBiographicalSections(array $person): array`

Parses WikiTree-formatted sections from NOTE fields.

**Detects**: Headers like `== Biography ==` or `=== Marriage ===`

#### `buildBiography(array $person, ...): string`

Generates complete WikiTree-style biography in markdown.

#### `convertPersonToJsonSchema(array $person, ...): array`

Converts person record to JSON schema structure.

### Biography Generation

#### `buildOpening(array $person, array $families): string`

Creates opening sentence with name, dates, and birth information.

**Example**: `'''John Smith''' (1850 – 1920) was born on 15 January 1850 in Boston, Massachusetts.`

#### `buildParentage(...): ?string`

Generates parentage statement.

**Example**: `He was the son of '''James Smith''' and '''Mary Jones'''.`

#### `buildMarriageSections(...): array`

Creates marriage section(s) with spouse names and dates.

#### `buildChildrenSection(...): ?array`

Generates bulleted list of children with birth years.

#### `buildDeathSection(...): ?array`

Creates death information section.

### Utility Functions

#### `displayName(array $person): string`

Returns formatted person name (prefers GIVN + SURN, falls back to NAME).

#### `eventText(array $event): string`

Formats event information as “on [date] in [place]”.

#### `yearFromDate(string $date): string`

Extracts 4-digit year from GEDCOM date.

#### `convertToWikiTreeLink(string $text, array $mapping): string`

Converts `'''Name'''` to `'''[[WikiTree-ID|Name]]'''` using mapping.

## Advanced Features

### 1. Biographical Section Parsing

The script intelligently parses existing WikiTree-formatted content from GEDCOM NOTE fields:

```gedcom
1 NOTE == Biography ==
2 CONT
2 CONT '''John Smith''' was a prominent merchant.
2 CONT
2 CONT === Early Life ===
2 CONT He grew up in Boston...
```

If such sections exist, they’re preserved in the output. Otherwise, the script generates a basic template.

### 2. WikiTree Link Conversion

When a WikiTree ID mapping exists, the script converts person references:

**Before**: `'''John Smith'''`
**After**: `'''[[Smith-123|John Smith]]'''`

This creates clickable profile links in WikiTree.

### 3. Filename Generation

Output files are named intelligently:

1. **First choice**: WikiTree ID (e.g., `Smith-123.md`)
1. **Fallback**: Sanitized name (e.g., `John-Smith.md`)
1. **Last resort**: GEDCOM ID (e.g., `I135.md`)

### 4. Multi-Database Cross-Referencing

The `externalIds` field allows matching profiles across:

- WikiTree (collaborative family tree)
- FamilySearch (LDS genealogy database)
- Ancestry (commercial genealogy service)
- Find A Grave (cemetery records)

## Error Handling

### Exit Codes

|Code|Meaning                       |
|----|------------------------------|
|1   |Invalid command-line arguments|
|2   |Cannot read GEDCOM file       |
|3   |Cannot create output directory|
|4   |Input file not found          |
|5   |Person ID not found in GEDCOM |
|6   |Cannot write output file      |

### Common Issues

**Issue**: “Person ID not found in GEDCOM”
**Solution**: Check GEDCOM file for the correct `@I123@` format ID

**Issue**: “Unable to create output directory”
**Solution**: Check write permissions on parent directory

**Issue**: “Failed to write output”
**Solution**: Verify disk space and file permissions

## Workflow Integration

### Typical Workflow

1. **Export GEDCOM** from genealogy software (Ancestry, Family Tree Maker, etc.)
1. **Run conversion**:

   ```bash
   php scripts/gedcom_to_biography.php --input family.ged --out output/
   ```
1. **Review output** files in `output/` directory
1. **Upload to WikiTree** or use JSON for analysis

### Batch Processing Example

```bash
#!/bin/bash
# Process multiple GEDCOM files

for ged in GEDs/*.ged; do
    basename=$(basename "$ged" .ged)
    php scripts/gedcom_to_biography.php \
        --input "$ged" \
        --out "sources/$basename"
    echo "Processed $basename"
done
```

### Integration with WikiTree

1. Use `--person` flag to generate individual profiles
1. Copy markdown content to WikiTree biography editor
1. WikiTree ID links will be automatically functional
1. Use reference database to verify cross-platform IDs

## Customization

### Adding New Database Support

To add support for another genealogy database:

1. Add extraction pattern to `extractDatabaseIds()`:

```php
// MyHeritage example
if (preg_match('/myheritage\.com\/person-(\d+)/i', $text, $m)) {
    $ids['myheritage'] = $m[1];
}
```

1. Add to JSON schema in `convertPersonToJsonSchema()`
1. Add to CSV export in `exportReferenceDatabase()`

### Modifying Biography Template

Edit these functions to change output format:

- `buildOpening()` - Opening statement
- `buildParentage()` - Parent information
- `buildMarriageSections()` - Marriage formatting
- `buildChildrenSection()` - Children list format
- `buildDeathSection()` - Death information

### Custom Section Parsing

Modify `parseBiographicalSections()` to recognize different header formats:

```php
// Support alternative header syntax
if (preg_match('/^#{1,3}\s+(.+)$/', $line, $m)) {
    // Parse markdown-style headers
}
```

## Performance Considerations

- **Memory**: Entire GEDCOM loaded into memory - suitable for files up to ~50MB
- **Processing**: Linear O(n) parsing - handles thousands of individuals efficiently
- **File I/O**: One write per person in batch mode

**Large Files**: For very large GEDCOM files (>100MB), consider:

1. Splitting the GEDCOM by family branch
1. Processing in chunks with `--person` flag
1. Increasing PHP memory limit: `php -d memory_limit=512M script.php`

## Troubleshooting

### Debug Mode

Add debugging output:

```php
// At top of script after declare(strict_types=1)
error_reporting(E_ALL);
ini_set('display_errors', '1');
```

### Validate GEDCOM

Ensure GEDCOM follows standard format:

- Lines start with level number (0, 1, 2, etc.)
- Records have proper `@ID@` format
- Required tags present (INDI, NAME, FAM)

### Check Encoding

GEDCOM should be UTF-8 or ANSI. Convert if needed:

```bash
iconv -f ISO-8859-1 -t UTF-8 input.ged > output.ged
```

## Best Practices

1. **Backup Original GEDCOM**: Always keep original file
1. **Review Output**: Check generated biographies before uploading
1. **Verify IDs**: Confirm database IDs are correct
1. **Use WikiTree IDs**: Add WikiTree IDs to GEDCOM for better linking
1. **Organize Output**: Use separate directories per GEDCOM file
1. **Version Control**: Track changes to generated files with git

## Limitations

- **GEDCOM Version**: Primarily supports GEDCOM 5.5/5.5.1
- **Complex Events**: Only basic event types (BIRT, DEAT, MARR) fully supported
- **Character Encoding**: Assumes UTF-8 compatible encoding
- **Note Continuation**: CONT/CONC handled for level 2 only
- **Media Files**: Does not process GEDCOM media (photos, documents)

## Future Enhancements

Potential improvements:

- Support for GEDCOM 7.0 format
- Additional event types (occupation, residence, immigration)
- Image/document extraction and linking
- Source citation parsing
- DNA test information extraction
- Interactive HTML output with family trees
- Geographic location mapping

## License & Credits

This script is designed for genealogical research and WikiTree integration. Ensure compliance with:

- WikiTree’s [Honor Code](https://www.wikitree.com/wiki/Help:Honor_Code)
- Data privacy regulations when handling personal information
- Copyright considerations for GEDCOM content

## Support

For issues or questions:

1. Check GEDCOM file format validity
1. Review error messages and exit codes
1. Verify file permissions and paths
1. Test with sample GEDCOM files

## Version History

**Current Version**: Enhanced with multi-database support

- Multi-platform ID extraction
- JSON schema output
- Biographical section parsing
- WikiTree link conversion
- Reference database export

-----

**Documentation Generated**: January 2026
**Script Purpose**: GEDCOM to WikiTree Biography Conversion
**Target Users**: Genealogists, WikiTree contributors, family historians