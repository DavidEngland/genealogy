# Family Heritage Albums

Family heritage albums generated from WikiTree profiles, photos, and family narratives.

## Quick Start

### Generate Album from CSV

```bash
php scripts/generate_album.php --input albums/england-pioneers.csv --album england-pioneers
```

This creates: `albums/builds/album-england-pioneers.md`

### Generate Single Person

```bash
php scripts/generate_album.php --id England-1357 --bio --parents --photos --output albums/my-person.md
```

## Files

- **ALBUM_SETUP.md** — Project setup, workflow, and usage guide
- **PHOTOS_INDEX.md** — Photo tracking and source management
- **england-pioneers.csv** — Example CSV input file (customize for your albums)
- **photos/** — Photo storage directory
- **metadata/** — JSON metadata for photos and people (optional)
- **stories/** — Narrative text and family stories
- **builds/** — Generated markdown ready for manual editing and PDF conversion

## Workflow

1. **Create CSV** with list of WikiTree IDs and desired fields (bio, parents, photos, etc.)
2. **Run generator** to fetch WikiTree data and create markdown
3. **Manual edit** markdown: add stories, enhance content, verify data
4. **Add photos** during editing (see PHOTOS_INDEX.md for sourcing)
5. **Convert to PDF** using Pandoc or VS Code preview

## Scripts

### wikitree_heritage_client.php
Fetches WikiTree profile data via API:
```php
$api = new WikiTreeAPIClient();
$person = $api->getPersonWithRelations('England-1357');
```

**Features:**
- Built-in caching (reduces API calls)
- Fetches relations (parents, spouses, children)
- Returns structured data
- Retry logic with rate limiting

### generate_album.php
Generates customizable markdown:
```bash
php generate_album.php --id WIKITREE-ID --bio --parents --photos [--output FILE]
php generate_album.php --input FILE.csv --album ALBUMNAME
```

**Options:**
- `--bio` — Include biography text
- `--parents` — Include parents information
- `--spouses` — Include spouse information  
- `--children` — Include children information
- `--photos` — Include photo references

**Output:**
- Includes your ORCID and publication metadata
- Placeholder sections for stories and photo galleries
- Formatted for manual editing

## Photo Management

### Naming Convention
```
SURNAME-WIKITREEID-DESCRIPTION-YEAR.jpg
england-1357-portrait-1920.jpg
gresham-182-family-group-1880.jpg
```

### Sources
- **WikiTree** — Extracted via API
- **Family archives** — Digitized scans
- **Public domain** — Historical records, pre-1928 works
- **Creative Commons** — CC0, CC-BY, CC-BY-SA licensed

Track all photos in `PHOTOS_INDEX.md` with source and copyright info.

## CSV Format

```csv
id,bio,parents,spouses,children,photos
England-1357,1,1,1,1,1
England-1358,1,1,1,1,0
Gresham-182,1,0,0,1,1
```

Flags: `1` = include, `0` = exclude

## Example Albums

Create separate CSVs for different themes:

- **england-pioneers.csv** — Early settlement and frontier
- **england-civil-war.csv** — Military service (16th Regiment)
- **gresham-legacy.csv** — Regional influence and connections
- **family-group.csv** — Immediate family across generations

## PDF Conversion

### Using Pandoc + wkhtmltopdf
```bash
pandoc albums/builds/album-england-pioneers.md -o album-england-pioneers.pdf
```

### Using VS Code
1. Open markdown in VS Code
2. Preview (Cmd+Shift+V)
3. Print to PDF (Cmd+P → "Print")

### Includes in Output
- Creator: David Edward England, PhD
- ORCID: https://orcid.org/0009-0001-2095-6646
- Email: DavidEngland@Hotmail.Com
- License: Creative Commons - Public Domain
- Generated date

## Customization

### Add Custom Sections to Generated Markdown
Edit `generate_album.php` to add:
- Custom introduction paragraphs
- Timeline sections
- Migration maps
- Family tree diagrams
- Additional narrative sections

### Modify WikiTree API Fields
Edit `wikitree_heritage_client.php` `getProfile()` method:
```php
'fields' => 'Id,Name,BirthDate,BirthLocation,...'
```

Available WikiTree fields: See https://www.wikitree.com/wiki/API

## License

All albums use **Creative Commons - Public Domain** by default.
Update metadata in generated markdown if different license applies.

## Resources

- **WikiTree API:** https://www.wikitree.com/wiki/API
- **Pandoc:** https://pandoc.org/ (markdown → PDF, DOCX, etc.)
- **Creative Commons:** https://creativecommons.org/
- **FamilySearch:** https://familysearch.org/ (additional source records)

---

**Project Owner:** David Edward England, PhD  
**ORCID:** https://orcid.org/0009-0001-2095-6646  
**Email:** DavidEngland@Hotmail.Com  
**Last Updated:** February 4, 2026
