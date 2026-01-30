# WikiTree Profile Parser

A PHP script that fetches WikiTree profile pages and extracts family relationships (parents, siblings, spouses, children) formatted as markdown with `[[WikiTree-ID|Person Name]]` syntax.

## Features

- **Fetches WikiTree profiles** via HTTP from `https://www.wikitree.com/wiki/{PROFILE_ID}`
- **Parses HTML** using DOMDocument/DOMXPath to extract schema.org-structured family data
- **Extracts relationships**:
  - Parents (with father/mother detection)
  - Siblings
  - Spouses (with marriage date and location)
  - Children
- **Outputs markdown** with WikiTree ID links in the format `[[WikiTree-ID|Person Name]]`
- **Selective output** - choose specific sections or all family information

## Requirements

- PHP 7.4+ (tested with PHP 8.x)
- cURL extension enabled
- DOM extension enabled (standard in most PHP installations)

## Usage

### Basic Usage

```bash
php wikitree_profile_parser.php --profile Lewis-8883
```

### Output to File

```bash
php wikitree_profile_parser.php --profile Lewis-8883 --output Lewis-8883-family.md
```

### Specific Sections

```bash
# Get only parents
php wikitree_profile_parser.php --profile Lewis-8883 --section parents

# Get only spouses and marriage info
php wikitree_profile_parser.php --profile Hargrove-277 --section spouses

# Get only children
php wikitree_profile_parser.php --profile Hargrove-277 --section children

# Get only siblings
php wikitree_profile_parser.php --profile Lewis-8883 --section siblings
```

### Help

```bash
php wikitree_profile_parser.php --help
```

## Command Line Options

| Option | Description | Required |
|--------|-------------|----------|
| `--profile ID` | WikiTree profile ID (e.g., Lewis-8883) | Yes |
| `--output FILE` | Output file path (default: stdout) | No |
| `--section NAME` | Section to output: `parents`, `siblings`, `spouses`, `children`, `all` (default: `all`) | No |
| `--help`, `-h` | Show help message | No |

## Example Output

For profile `Lewis-8883`:

```markdown
# Family Information for Lewis-8883

**Person**: [[Lewis-8883|Ruth Whitley Lewis]]

=== Parents ===
Child of [[Lewis-9841|Joseph Francis Lewis]] and [[Whitley-753|Sarah (Whitley) Lewis]]

=== Siblings ===
Sibling of [[Lewis-9847|Samuel Lewis]], [[Lewis-9843|Elizabeth Lewis]], [[Lewis-9848|Sarah Norman Lewis]], [[Lewis-9844|Isabella Lewis]], [[Lewis-9849|William W. Lewis]], [[Lewis-9842|Benjamin J. Lewis]], [[Lewis-9845|Mary Ann Lewis]]

=== Marriage ===
Married [[Hargrove-277|Valentine Hargrove]] on '''9 Oct 1799''' in ''',Lincoln,Kentucky'''

=== Children ===
* [[Hargrove-292|Mary Hargrove]]
* [[Hargrove-289|Ruth Hargrove]]
* [[Hargrove-284|Elizabeth Louisiana Hargrove]]
* [[Hargrove-285|Samuel Ball Hargrove]]
* [[Hargrove-293|Sarah Hargrove]]
* [[Hargrove-286|William Wesley Hargrove]]
* [[Hargrove-287|James Monroe Hargrove]]
* [[Hargrove-288|Omar Hassen Hargrove]]
* [[Hargrove-290|John Lindsey Hargrove]]
* [[Hargrove-291|Jackson Hargrove]]
```

## How It Works

1. **Fetches HTML** from WikiTree using cURL
2. **Parses HTML** using PHP's DOMDocument and DOMXPath
3. **Extracts data** from schema.org microdata attributes:
   - `itemprop="parent"` for parents
   - `itemprop="sibling"` for siblings
   - `itemprop="spouse"` for spouses
   - `itemprop="children"` for children
4. **Extracts WikiTree IDs** from href attributes using regex `/\/wiki\/([A-Z][a-z]+-\d+)/`
5. **Formats output** as markdown with `[[WikiTree-ID|Person Name]]` links

## Integration with Existing Workflow

This script complements the existing GEDCOM-based workflow:

- **GEDCOM parsing** ([gedcom_to_biography.php](gedcom_to_biography.php)) extracts data from GEDCOM files
- **WikiTree parser** (this script) fetches current data directly from WikiTree profiles
- Both produce markdown with consistent `[[WikiTree-ID|Name]]` formatting

## Limitations

- **HTML scraping** - relies on WikiTree's HTML structure (schema.org markup)
- **No authentication** - only accesses public profile data
- **Single profile** - processes one profile at a time (no batch mode yet)
- **Marriage location** - may include leading comma from WikiTree HTML

## Future Enhancements

- [ ] WikiTree API integration (more stable than HTML scraping)
- [ ] Batch processing multiple profiles
- [ ] Cache fetched profiles to reduce requests
- [ ] Rate limiting for respectful scraping
- [ ] Extract birth/death dates and locations
- [ ] Handle multiple marriages
- [ ] Clean up marriage location formatting

## Notes

- **Respectful usage**: Please don't hammer WikiTree's servers with rapid requests
- **Public data only**: This script only accesses publicly available profile information
- **Schema.org markup**: Relies on WikiTree's use of schema.org microdata in their HTML

## See Also

- [WikiTree API Documentation](https://github.com/wikitree/wikitree-api)
- [Schema.org Person](https://schema.org/Person)
- [gedcom_to_biography.php](gedcom_to_biography.php) - GEDCOM to markdown converter
