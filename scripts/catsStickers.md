# GEDCOM to WikiTree Biography Converter - Enhanced with Categories & Stickers

## New Features in Enhanced Version

This enhanced version automatically generates WikiTree categories and stickers/templates based on the content of your GEDCOM file, matching the format shown in your example.

### Automatic Category Generation

The script detects and adds categories for:

- **Geographic Locations**: Kentucky Appalachians, specific counties, cities, cemeteries
- **Military Service**: Civil War (Union/Confederate), POW camps, regiments
- **Notable Events**: Famous feuds (Hatfield-McCoy), historical significance
- **Time Periods**: Based on birth/death dates
- **Special Designations**: Notables, Featured Connections

### Automatic Sticker/Template Generation

The script creates WikiTree templates for:

- **{{Appalachia}}** - For Appalachian region individuals
- **{{Notables Sticker}}** - For notable persons
- **{{US Civil War}}** - With service details (side, rank, unit, dates)
- **{{Roll of Honor}}** - For POWs and veterans

## Output Example

The enhanced script produces output like your example:

```markdown
[[Category: Appalachia, Featured Connections]]
[[Category: Camp Douglas, Chicago, Illinois]]
[[Category: Kentucky Appalachians]]
[[Category: Hatfield and McCoy Family Feud]]
[[Category: Dils Cemetery, Pikeville, Kentucky]]
[[Category: Famous Feuds]]
{{Appalachia}}

== Biography ==
{{Notables Sticker|Appalachia, Notables}}
{{US Civil War
|side = CSA
|enlisted = 1862
|mustered = 1865
|regiment name = Co C 45th Regiment Virginia Infantry
|rank= Private
|unit=45th Regiment, Virginia Infantry
}}
{{Roll of Honor
|category =Prisoners of War, Confederate States of America, United States Civil War
|image = POW Camps-7.jpg
|description = a Prisoner of War
|war = United States Civil War
}}
'''Randall McCoy''' was born on October 1825, in Pike County, Kentucky...
```

## Usage

### Basic Usage (with automatic category detection)

```bash
php gedcom_to_biography_enhanced.php \
  --input GEDs/Hargroves.ged \
  --person @I123@ \
  --out Hargroves.md
```

### With Custom Category Configuration

```bash
php gedcom_to_biography_enhanced.php \
  --input GEDs/Hargroves.ged \
  --out sources/Hargroves \
  --categories category-rules.json
```

### Batch Processing

```bash
php gedcom_to_biography_enhanced.php \
  --input GEDs/Hargroves.ged \
  --out sources/Hargroves
```

## Built-in Category Detection

### Geographic Categories

The script automatically detects:

**Kentucky Appalachian Counties:**

- Pike County, Kentucky → “Kentucky Appalachians”, “Appalachia”
- Floyd County, Kentucky → “Kentucky Appalachians”
- Perry, Letcher, Harlan, Bell Counties

**Cities and Towns:**

- Pikeville, Kentucky → “Pikeville, Kentucky”

**Cemeteries:**

- Any location containing “Cemetery” creates a category
- Example: “Dils Cemetery, Pikeville, Kentucky” → [[Category: Dils Cemetery, Pikeville, Kentucky]]

**Prison Camps:**

- Camp Douglas mentions → “Camp Douglas, Chicago, Illinois”
- Camp Chase mentions → “Camp Chase, Columbus, Ohio”

### Military Service Categories

The script parses GEDCOM notes to detect:

**Civil War Service:**

- Confederate service → “Confederate States of America, United States Civil War”
- Union service → “Union Army, United States Civil War”
- General category: “United States Civil War”

**Prisoner of War:**

- POW mentions → “Prisoners of War, [Side], United States Civil War”
- Specific camp categories for Camp Douglas and Camp Chase

### Notable Person Categories

Detects notable individuals through keywords:

- “notable”, “famous”, “prominent”, “patriarch”, “matriarch”
- “feud”, “historical”, “well-known”

If notable + Appalachian location → “Appalachia, Notables”

### Family Feud Categories

Detects famous feuds:

- Hatfield/McCoy mentions → “Hatfield and McCoy Family Feud”, “Famous Feuds”

## Built-in Sticker/Template Generation

### {{Appalachia}} Sticker

**When Generated:** Notable persons from Appalachian counties

**Output:**

```
{{Appalachia}}
```

### {{Notables Sticker}}

**When Generated:** Notable persons from specific regions

**Output:**

```
{{Notables Sticker|Appalachia, Notables}}
```

### {{US Civil War}} Template

**When Generated:** Civil War service detected in notes

**Output:**

```
{{US Civil War
|side = CSA
|enlisted = 1862
|mustered = 1865
|regiment name = Co C 45th Regiment Virginia Infantry
|rank= Private
|unit=45th Regiment, Virginia Infantry
}}
```

**Parameters Extracted:**

- `side`: CSA or USA (from “Confederate”, “Union”, “CSA”, “USA”)
- `enlisted`: Year from “enlisted: YYYY” or “enlisted YYYY”
- `mustered`: Year from “mustered: YYYY” or “mustered YYYY”
- `regiment name`: Full regiment name with company
- `rank`: Private, Sergeant, Lieutenant, Captain, etc.
- `unit`: Regiment name

### {{Roll of Honor}} Template

**When Generated:** Prisoner of War status detected

**Output:**

```
{{Roll of Honor
|category =Prisoners of War, Confederate States of America, United States Civil War
|image = POW Camps-7.jpg
|description = a Prisoner of War
|war = United States Civil War
}}
```

**Parameters:**

- `category`: Appropriate POW category
- `image`: Default POW image
- `description`: “a Prisoner of War”
- `war`: “United States Civil War”

## Custom Category Configuration

Create a JSON file to define additional category and sticker rules:

### Configuration File Format

```json
{
  "categories": [
    {
      "pattern": "/genealogist/i",
      "category": "Genealogists"
    },
    {
      "pattern": "/teacher|educator|professor/i",
      "category": "Teachers"
    },
    {
      "pattern": "/doctor|physician|surgeon/i",
      "category": "Medical Professionals"
    }
  ],
  "stickers": [
    {
      "pattern": "/adopted/i",
      "template": "Adoption",
      "params": {}
    }
  ]
}
```

### Pattern Syntax

Patterns use PHP regular expressions:

- `/.../i` - Case-insensitive matching
- `|` - OR operator (teacher|educator)
- `\s` - Whitespace
- `\d` - Digit
- `+` - One or more
- `*` - Zero or more

### Examples

**Occupation Categories:**

```json
{
  "pattern": "/farmer/i",
  "category": "Farmers"
}
```

**Location Categories:**

```json
{
  "pattern": "/Boston, Massachusetts/i",
  "category": "Boston, Massachusetts"
}
```

**Event Categories:**

```json
{
  "pattern": "/Revolutionary War/i",
  "category": "American Revolutionary War"
}
```

**Sticker Rules:**

```json
{
  "pattern": "/immigrant/i",
  "template": "Immigration",
  "params": {
    "year": "extract_year"
  }
}
```

## GEDCOM Note Format for Best Results

To ensure proper category and sticker generation, format your GEDCOM notes with clear information:

### Military Service Format

```
NOTE During the American Civil War, he served from 1862 to 1865
CONT as a Private in the 45th Virginia Battalion Infantry, Confederate States Army.
CONT Between 1863 and 1865, he was a Prisoner of War (POW).
CONT He was captured in Pike County, Kentucky, on 8 July 1863 and sent to Camp Chase,
CONT a Union prison camp in Columbus, Ohio. He arrived there on 20 July 1863
CONT and a month later, he was transferred to the large military prison at
CONT Camp Douglas in Chicago, Illinois where he remained a POW for the duration
CONT of the Civil War.
```

### Notable Person Format

```
NOTE Randolph McCoy was the patriarch of the McCoy clan involved in the infamous
CONT American Hatfield and McCoy Family Feud. He lost five of his children to
CONT the violence during the almost 30 year feud with the Hatfield clan under
CONT their patriarch William Anderson "Devil Anse" Hatfield.
```

### Geographic Information

Ensure place names follow standard format:

- Birth: `Pike County, Kentucky, United States`
- Death: `Pikeville, Pike County, Kentucky, United States`
- Burial: `Dils Cemetery, Pikeville, Kentucky`

## Troubleshooting Category Detection

### Categories Not Being Generated

**Problem:** Expected categories don’t appear

**Solutions:**

1. Check NOTE fields contain the relevant keywords
1. Verify spelling of location names
1. Ensure dates are in standard format
1. Add custom rules in configuration file

**Debug:** Add this to see what’s being parsed:

```php
// In generateCategories() function
error_log("Notes: " . implode(' ', $person['notes']));
error_log("Categories: " . implode(', ', $categories));
```

### Military Service Not Detected

**Required Keywords:**

- Side: “Confederate”, “Union”, “CSA”, “USA”
- Service: “served”, “enlisted”, “mustered”
- POW: “POW”, “Prisoner of War”, “captured”

**Example Working Format:**

```
served from 1862 to 1865 as a Private in Company C, 45th Regiment Virginia Infantry, Confederate States Army
```

### Stickers Not Appearing

**Checklist:**

1. Is person notable? (check notable keywords)
1. Is military service properly formatted?
1. Are locations in Appalachian counties?
1. Check the `generateStickers()` function conditions

## Advanced Customization

### Adding New Geographic Regions

Edit the `generateCategories()` function:

```php
// Add West Virginia counties
if (preg_match('/(?:Mingo|Logan|Wayne)\s+County,\s*West Virginia/i', $place)) {
    $categories[] = 'West Virginia Appalachians';
    $categories[] = 'Appalachia';
}
```

### Adding New War/Conflict Categories

```php
// World War I detection
if (preg_match('/World War I|WWI|Great War/i', $notes)) {
    $categories[] = 'World War I Veterans';
}
```

### Custom Sticker Templates

Add to `generateStickers()`:

```php
// DNA confirmed sticker
$notes = implode(' ', $person['notes'] ?? []);
if (preg_match('/DNA confirmed/i', $notes)) {
    $stickers[] = [
        'template' => 'DNA Confirmed',
        'params' => []
    ];
}
```

## JSON Output with Categories

When using `--json` flag, categories and stickers are included:

```json
{
  "wikitreeId": "McCoy-576",
  "name": {
    "full": "Randall McCoy",
    "given": "Randall",
    "surname": "McCoy"
  },
  "categories": [
    "Kentucky Appalachians",
    "Appalachia",
    "United States Civil War",
    "Confederate States of America, United States Civil War",
    "Prisoners of War, Confederate States of America, United States Civil War",
    "Camp Douglas, Chicago, Illinois",
    "Hatfield and McCoy Family Feud",
    "Famous Feuds",
    "Appalachia, Notables"
  ],
  "stickers": [
    {
      "template": "Appalachia",
      "params": []
    },
    {
      "template": "Notables Sticker",
      "params": ["Appalachia, Notables"]
    },
    {
      "template": "US Civil War",
      "params": {
        "side": "CSA",
        "enlisted": "1862",
        "mustered": "1865",
        "regiment name": "Co C 45th Regiment Virginia Infantry",
        "rank": "Private",
        "unit": "45th Regiment Virginia Infantry"
      }
    },
    {
      "template": "Roll of Honor",
      "params": {
        "category": "Prisoners of War, Confederate States of America, United States Civil War",
        "image": "POW Camps-7.jpg",
        "description": "a Prisoner of War",
        "war": "United States Civil War"
      }
    }
  ]
}
```

## Comparison: Original vs Enhanced

### Original Script Output:

```markdown
== Biography ==
'''Randall McCoy''' (1825 – 1914) was born on October 1825...
```

### Enhanced Script Output:

```markdown
[[Category: Appalachia, Featured Connections]]
[[Category: Camp Douglas, Chicago, Illinois]]
[[Category: Kentucky Appalachians]]
[[Category: Hatfield and McCoy Family Feud]]
[[Category: Famous Feuds]]
{{Appalachia}}

== Biography ==
{{Notables Sticker|Appalachia, Notables}}
{{US Civil War
|side = CSA
|enlisted = 1862
|mustered = 1865
|regiment name = Co C 45th Regiment Virginia Infantry
|rank= Private
|unit=45th Regiment, Virginia Infantry
}}
{{Roll of Honor
|category =Prisoners of War, Confederate States of America, United States Civil War
|image = POW Camps-7.jpg
|description = a Prisoner of War
|war = United States Civil War
}}
'''Randall McCoy''' (1825 – 1914) was born on October 1825...
```

## Best Practices

### 1. Prepare Your GEDCOM

Before conversion:

- Add comprehensive NOTE fields with military service details
- Include complete place names (City, County, State)
- Use standard terminology (Confederate, Union, POW)
- Add WikiTree IDs to WWW fields for cross-linking

### 2. Review Generated Output

After conversion:

- Check category assignments for accuracy
- Verify military service details in templates
- Confirm geographic categories match locations
- Ensure notable status is appropriate

### 3. Customize Configuration

Create category rules for:

- Family-specific patterns (surnames, locations)
- Occupations common in your tree
- Regional categories relevant to your research
- Special events or historical contexts

### 4. Batch Processing Workflow

```bash
# 1. Convert all individuals
php gedcom_to_biography_enhanced.php \
  --input family.ged \
  --out biographies/

# 2. Review a sample file
cat biographies/Smith-123.md

# 3. Adjust categories if needed
# Edit category-config.json

# 4. Re-run with custom config
php gedcom_to_biography_enhanced.php \
  --input family.ged \
  --out biographies/ \
  --categories category-config.json
```

## Command-Line Options

|Option             |Description           |Example                  |
|-------------------|----------------------|-------------------------|
|`--input FILE`     |GEDCOM file path      |`--input family.ged`     |
|`--person @ID@`    |Single person ID      |`--person @I123@`        |
|`--out PATH`       |Output file/directory |`--out biographies/`     |
|`--json`           |JSON output format    |`--json`                 |
|`--categories FILE`|Custom category config|`--categories rules.json`|
|`--help`           |Show help message     |`--help`                 |

## Category Types Reference

### Geographic Categories

- State/Province names
- County names
- City/Town names
- Cemetery names (with full location)
- Historical regions (Appalachia, etc.)

### Military Categories

- War/Conflict name
- Side/Allegiance
- Branch of service
- POW status
- Specific camps/prisons

### Social Categories

- Occupations
- Notable status
- Family connections
- Historical events

### Time Period Categories

- Century markers
- Generational designations
- Historical era names

## Template/Sticker Reference

### Regional Templates

- `{{Appalachia}}` - Appalachian region
- `{{New England}}` - New England region
- `{{Deep South}}` - Southern states

### Military Templates

- `{{US Civil War}}` - Civil War service
- `{{Roll of Honor}}` - Military honors
- `{{WWII}}` - World War II service
- `{{Korean War}}` - Korean War service
- `{{Vietnam War}}` - Vietnam War service

### Special Designation Templates

- `{{Notables Sticker}}` - Notable persons
- `{{Mayflower}}` - Mayflower descendants
- `{{Jamestowne}}` - Jamestown settlers
- `{{Gateway}}` - Gateway ancestors

## FAQ

**Q: Why aren’t my categories appearing?**
A: Check that keywords exist in NOTE fields and locations follow standard format.

**Q: Can I prevent certain categories from being added?**
A: Yes, edit the `generateCategories()` function to exclude specific patterns.

**Q: How do I add categories manually after generation?**
A: Edit the .md files and add `[[Category: Name]]` lines at the top.

**Q: Can I use this for non-US genealogy?**
A: Yes, but you’ll need to customize location patterns and military service detection for your region.

**Q: Will this work with FamilySearch GEDCOM exports?**
A: Yes, the script handles standard GEDCOM 5.5/5.5.1 format from any source.

**Q: How do I add Featured Connections categories?**
A: Add a custom rule in your configuration JSON or manually add to generated files.

## Future Enhancements

Planned features:

- Mayflower descendant detection
- Revolutionary War categories
- Immigration categories and templates
- DNA confirmation badges
- Photo/image references
- Source quality indicators
- Relationship calculator for famous ancestors

## Support & Contributing

To add new category detection:

1. Identify the pattern in GEDCOM notes
1. Add detection code to `generateCategories()`
1. Test with sample GEDCOM
1. Document the new category

To add new templates:

1. Define the template structure
1. Add generation code to `generateStickers()`
1. Test output format
1. Document parameters

## License

This enhanced script maintains compatibility with the original while adding powerful automatic categorization for WikiTree profiles.

-----

**Version:** 2.0 (Enhanced with Categories & Stickers)
**Date:** January 2026
**Purpose:** Automated WikiTree profile generation with categories and templates