# Genealogy Data Schema

A comprehensive JSON schema for genealogical GEDCOM data with schema.org microdata alignment, plus PHP tools for parsing and CSV export.

## Overview

This schema system provides:

1. **schema.json** - JSON Schema defining the structure for genealogical records
2. **gedcom_parser.php** - Converts GEDCOM (.ged) files to JSON
3. **csv_exporter.php** - Exports JSON data to CSV (lookup or full format)
4. **index.html** - Standalone web interface for viewing data and exports

## Schema Structure

### Top-Level Objects

```json
{
  "metadata": { ... },
  "people": [ ... ],
  "families": [ ... ],
  "places": [ ... ],
  "sources": [ ... ]
}
```

### People (schema.org/Person)

Each person record includes:
- `wikitreeId` - WikiTree identifier (e.g., "England-1357")
- `gedcomId` - GEDCOM internal ID (e.g., "@I135@")
- `name` - Full name with given/surname breakdown
- `sex` - M/F/U
- `birth` - Birth event with date and place
- `death` - Death event with date and place
- `familyAsSpouse` - Family records where person is spouse
- `familyAsChild` - Family records where person is child
- `residences` - Known residences
- `sources` - Bibliographic citations
- `notes` - Additional narrative

Example:
```json
{
  "wikitreeId": "England-1357",
  "gedcomId": "@I135@",
  "name": {
    "full": "David Edward England",
    "given": "David Edward",
    "surname": "England"
  },
  "sex": "M",
  "birth": {
    "date": "5 MAY 1920",
    "place": "Alabama",
    "placeId": "AL"
  },
  "death": {
    "date": "12 JAN 2010",
    "place": "Alabama",
    "placeId": "AL"
  },
  "familyAsSpouse": ["@F1@"],
  "familyAsChild": ["@F0@"]
}
```

### Families

Links individuals through marriage and parentage:
- `familyId` - GEDCOM family ID
- `husband` - Husband's wikitreeId
- `wife` - Wife's wikitreeId
- `marriage` - Marriage event (date, place)
- `children` - Array of child wikitreeIds
- `sources` - Citations

Example:
```json
{
  "familyId": "@F1@",
  "husband": "England-1357",
  "wife": "Duncan-166",
  "marriage": {
    "date": "1945",
    "place": "Alabama"
  },
  "children": ["England-1360", "England-1362"]
}
```

### Places (schema.org/Place)

Geographic locations:
- `placeId` - Unique identifier
- `name` - Place name
- `type` - country, state, county, city, township, parish, landmark
- `parent` - Parent place ID
- `coordinates` - Lat/long

### Sources

Bibliographic references:
- `sourceId` - Unique identifier
- `title` - Source title
- `type` - book, census, land_record, bible_record, court_record, cemetery_record, newspaper, family_search, ancestry, wikitree, other
- `author`, `publication`, `date`, `url`, `repository`, `callNumber`

## Usage

### 1. Parse GEDCOM File

**Command Line:**
```bash
php schema/gedcom_parser.php input.ged output.json
```

**Example:**
```bash
php schema/gedcom_parser.php GEDs/Duncans.ged data/duncans.json
```

This reads the GEDCOM file and outputs a JSON file following the schema.

### 2. Export to CSV

**Lookup CSV (WikiTree ID + Name):**
```bash
php schema/csv_exporter.php data.json output.csv lookup
```

**Full CSV (All genealogical fields):**
```bash
php schema/csv_exporter.php data.json output.csv full
```

**Output Examples:**

*Lookup CSV:*
```
wikitree_id,name
England-1357,David Edward England
Duncan-166,Sarah Clark Duncan
```

*Full CSV:*
```
wikitree_id,name,given_name,surname,birth_date,birth_place,death_date,death_place,sex
England-1357,David Edward England,David Edward,England,5 MAY 1920,Alabama,12 JAN 2010,Alabama,M
Duncan-166,Sarah Clark Duncan,Sarah Clark,Duncan,ABT 1805,South Carolina,BEF 1865,Alabama,F
```

### 3. Web Interface

Open `schema/index.html` in a web browser:

1. **Parse GEDCOM Tab** - Instructions for parsing files via command line
2. **View Data Tab** - Load exported JSON files and view records
3. **Export CSV Tab** - Generate lookup or full CSV from loaded data
4. **Help Tab** - Documentation and examples

## GEDCOM to JSON Mapping

| GEDCOM Record | Schema Section | Notes |
|---|---|---|
| INDI (Individual) | people[] | Maps NAME, SEX, BIRT, DEAT, FAMS, FAMC, NOTE |
| FAM (Family) | families[] | Maps HUSB, WIFE, MARR, CHIL |
| SOUR (Source) | sources[] | Maps TITL, ABBR, AUTHOR |
| PLAC (Place) | places[] | Extracted from events, normalized |

## Workflow Example

1. **Parse your GEDCOM file:**
   ```bash
   php schema/gedcom_parser.php GEDs/grandpaTomGresham.ged data/gresham.json
   ```

2. **Export to lookup CSV:**
   ```bash
   php schema/csv_exporter.php data/gresham.json lookup.csv lookup
   ```

3. **View in browser:**
   - Open `schema/index.html`
   - Load `data/gresham.json` in "View Data" tab
   - Export to CSV in "Export CSV" tab

## WikiTree ID Format

Records use WikiTree naming convention:
- Format: `Surname-Number`
- Examples: `England-1357`, `Duncan-166`, `Gresham-188`
- Used for internal references throughout the schema

## Schema Compliance

The schema aligns with schema.org microdata standards:
- **Person** (https://schema.org/Person) - Individual records
- **Event** (https://schema.org/Event) - Birth, death, marriage
- **Place** (https://schema.org/Place) - Geographic locations
- **CreativeWork** (https://schema.org/CreativeWork) - Sources and references

## Date Formats

Dates can be:
- Exact: `YYYY-MM-DD` (ISO 8601)
- GEDCOM format: `5 MAY 1920`, `BEF 1865`, `ABT 1788`
- Partial: `YYYY`, `YYYY-MM`

## Notes

- The parser preserves GEDCOM IDs internally for reference
- WikiTree IDs are the primary lookup key
- Custom GEDCOM fields are stored in the notes field
- Places are automatically extracted and normalized
- Multiple marriages/families are supported via arrays

## Future Enhancements

- Web-based data editor
- Batch import of multiple GEDCOM files
- Duplicate detection and merging
- Interactive family tree visualization
- Photo and document attachment support
- Export to GEDCOM format

## Requirements

- PHP 7.4+
- Modern web browser (for index.html)
- No external dependencies

## License

This schema and tools are provided as-is for genealogical research purposes.
