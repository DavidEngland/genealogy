# WikiTree ID Lookup Tool

A PHP command-line tool that automatically searches WikiTree for genealogy records mentioned in markdown biography files.

## Overview

This tool parses genealogy markdown files (in WikiTree biography format) to extract person information (names, birth dates, death dates), then queries WikiTree's API to find potential matches. Results are saved to an `unknowns/` directory for manual verification.

## Usage

```bash
php wikitree-lookup.php <input-file.md>
```

### Example

```bash
php wikitree-lookup.php Hargrove-286.md
```

This will:
1. Parse `Hargrove-286.md` for person data
2. Query WikiTree API for each person
3. Generate results at `unknowns/Hargrove-286.md`

## How It Works

### Data Extraction

The tool extracts the following person information from markdown files:

- **Main person**: From the biography opening (bold name with birth date)
- **Children**: From the `=== Children ===` section
- **Parents**: Father and mother references with `[[WikiTreeID|Name]]` format

### Search Strategy

For each person found, the tool searches WikiTree using progressive fallback:

1. **First Name + Last Name + Birth Date** (with 5-year spread for fuzzy matching)
2. **First Name + Last Name + Birth Year** (if specific date fails)
3. **First Name + Last Name + Death Date** (if birth fails)
4. **First Name + Last Name only** (as last resort)

This helps reduce false positives while still finding matches for people with incomplete or uncertain dates.

### Results Limitation

- Returns maximum **3 results per person** to avoid overwhelming output
- API queries include 1-second delays to respect rate limits

## Output Format

The output is an unformatted markdown list with:
- **Person name** and extracted birth/death dates
- **Top matching results** with direct WikiTree links
- Format: `https://wikitree.com/wiki/LastName-ID`

Each link is clickable for easy verification.

### Example Output

```
Name: John Stanley Hargrove
Birth: 1840-00-00
Death Year: 1925

Matches Found:
  • Jonathan Hargrove (1844-00-00 - 1889-05-04)
    https://wikitree.com/wiki/Hargrove-945
  • John Hargrove (1837-00-00 - 1870-00-00)
    https://wikitree.com/wiki/Hargrove-1170
  • John Hargrove (1840-00-00 - 1920-00-00)
    https://wikitree.com/wiki/Hargrove-1138
```

## Workflow

1. **Run the tool** on a markdown biography file
2. **Review results** in `unknowns/[filename].md`
3. **Click each WikiTree link** to verify it's the correct person
4. **Update the original markdown** with confirmed WikiTree IDs
   - Replace `[[Hargrove-## | Name]]` with the correct ID like `[[Hargrove-277 | Name]]`

## API Details

The tool uses WikiTree's `searchPerson` API action:
- **Endpoint**: `https://api.wikitree.com/api.php`
- **Parameters**: FirstName, LastName, BirthDate, DeathDate, dateSpread, fields
- **Rate Limit**: 1-second delay between API calls

## Requirements

- PHP 8.0+ with cURL extension enabled
- Network access to WikiTree API
- Input markdown file in WikiTree biography format

## File Structure

```
genealogy/
├── wikitree-lookup.php          # Main tool script
├── Hargrove-286.md              # Input biography file
├── unknowns/
│   ├── Hargrove-286.md          # Output results
│   ├── Hargrove-287.md
│   └── ...
└── WIKITREE_LOOKUP_README.md    # This file
```

## Notes

- One input file at a time to manage API limits
- Simple search focused on names and key dates
- Designed for manual review - no automatic updates
- All matches must be verified before updating original markdown files

## Troubleshooting

**No results found for a person?**
- Try manual search on wikitree.com using the person's name
- Check markdown format is correct (names in bold with `'''Name'''`)
- Some people may not be indexed in WikiTree yet

**API rate limit errors?**
- Wait and try again later
- The tool includes built-in 1-second delays between queries

**Incorrect file path?**
- Use absolute path or filename relative to current directory
- Example: `php wikitree-lookup.php /path/to/Hargrove-286.md`
