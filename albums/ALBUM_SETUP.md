# Family Heritage Album - Project Setup

**Project:** Family Heritage Album with WikiTree Integration  
**Creator:** David Edward England, PhD  
**ORCID:** https://orcid.org/0009-0001-2095-6646  
**Email:** DavidEngland@Hotmail.Com  
**License:** Creative Commons - Public Domain  
**Date Created:** February 4, 2026

## Overview

This project generates customizable family heritage albums that combine WikiTree biographical data, photos, family stories, and historical narratives into linked markdown documents. Output can be manually edited and converted to PDF publications.

## Project Structure

```
albums/
├── ALBUM_SETUP.md                (This file)
├── PHOTOS_INDEX.md               (Photo tracking and sources)
├── england-pioneers.csv          (Example: CSV input for batch generation)
├── photos/                        (Photo storage)
│   ├── england-1357-portrait-1920.jpg
│   └── ...
├── metadata/                     (JSON metadata for photos/people)
├── stories/                      (Narrative text, family stories)
├── builds/                        (Generated markdown ready for PDF)
│   ├── album-england-pioneers.md
│   ├── album-civil-war.md
│   └── ...
└── README.md                     (Album project documentation)
```

## Workflow

### 1. Plan Albums

Define which persons to include and what data to fetch:

| Album | Persons | Theme | Notes |
|-------|---------|-------|-------|
| england-pioneers | England-1357, England-1358, England-1359, ... | Early settlement, frontier | Includes parents & children |
| england-civil-war | England soldiers in 16th Regiment | Military service | Birth/death dates, locations |
| gresham-legacy | Gresham-182, Gresham-184, ... | Regional influence | Allied families |

### 2. Create Input CSV

Format: `id,bio,parents,spouses,children,photos`

```csv
id,bio,parents,spouses,children,photos
England-1357,1,1,1,1,1
England-1358,1,1,1,1,1
```

**Flags:**
- `1` = include in output
- `0` = exclude from output

### 3. Generate Markdown

```bash
# Single person
php scripts/generate_album.php --id England-1357 --bio --parents --photos

# Batch mode (from CSV)
php scripts/generate_album.php --input albums/england-pioneers.csv --album england-pioneers
```

**Output:** `albums/builds/album-england-pioneers.md`

### 4. Manual Editing

Edit the generated markdown:
- Add family stories in "Story & Heritage Notes" sections
- Add photo references in photo gallery sections
- Enhance biography sections with additional narrative
- Verify and correct data from WikiTree
- Add sources and citations
- Organize narrative flow across album

### 5. PDF Conversion

Convert markdown to PDF (manual tools):
- **Recommended:** Pandoc + wkhtmltopdf
- **Alternative:** VS Code markdown preview to print as PDF
- **Include:** Your ORCID and publication metadata

## Tools & Scripts

### wikitree_heritage_client.php
Fetches WikiTree profile data via `api.wikitree.com`:
- `getPerson($wikiTreeId)` - Get single profile
- `getPersonWithRelations($wikiTreeId)` - Get with parents, spouses, children
- `getPhotos($wikiTreeId)` - Get photo references
- Built-in caching to reduce API calls

### generate_album.php
Generates markdown from WikiTree data:
- Single person mode: `--id England-1357 --bio --parents --photos`
- Batch mode: `--input FILE.csv --album albumname`
- Customizable sections (bio, parents, spouses, children, photos)
- Includes ORCID and publication metadata in output

## Photo Management

### Sources
1. **WikiTree API** - Photos linked to profiles
2. **Manual digitization** - Scan family archives
3. **Public domain** - Census, military records, historical documents
4. **Creative Commons** - Licensed images with proper attribution

### Naming Convention
```
SURNAME-WIKITREEID-DESCRIPTION-YEAR.jpg
england-1357-portrait-1920.jpg
gresham-182-family-group-civil-war-1863.jpg
```

### Metadata Tracking
See `PHOTOS_INDEX.md` for complete photo inventory with:
- Source (WikiTree, family collection, archive, etc.)
- Date/location
- Copyright status (public domain, CC0, CC-BY, etc.)
- Description

## Album Examples

### England Pioneers Album
- **Scope:** Early England family settlement (Virginia → Kentucky → Tennessee → Alabama)
- **Persons:** William Valentine Hargrove's era, pioneer ancestors
- **Content:** Migration stories, frontier life, settlement locations
- **Photos:** Family portraits, historical locations, period documents

### Civil War Album  
- **Scope:** 16th Regiment military service (1861-1865)
- **Persons:** England and related family soldiers
- **Content:** Military records, unit history, battle participation
- **Photos:** Military uniforms, camp scenes, period photographs

## Customization

### Adding New Albums

1. Create new CSV file: `albums/my-album.csv`
2. List WikiTree IDs and flags
3. Run generator: `php scripts/generate_album.php --input albums/my-album.csv --album my-album`
4. Edit `albums/builds/album-my-album.md`
5. Add photos and stories
6. Convert to PDF

### Modifying Scripts

Both PHP scripts are fully documented and can be extended:
- Add new API fields in `wikitree_heritage_client.php`
- Modify markdown formatting in `generate_album.php`
- Add new command-line flags for custom output

## Publication & Distribution

### Final PDF Contains
- Album metadata (creator, ORCID, email, license)
- Biographical profiles with photos
- Family stories and historical narrative
- Source citations and references
- Creative Commons or public domain notices

### Sharing
- **Private:** Family-only distribution (email, USB, private cloud)
- **Public:** GitHub, genealogy forums, institutional repositories
- **License:** Creative Commons - clearly mark as public domain content

## Future Enhancements

- Automated PDF generation from markdown
- Photo gallery layouts with captions
- Timeline visualizations
- Interactive family trees (HTML output)
- Multi-language support
- Direct WikiTree profile links in PDF (clickable)

## Resources

- **WikiTree API:** https://www.wikitree.com/wiki/API
- **Genealogy Standards:** https://cgis.org/
- **Creative Commons:** https://creativecommons.org/
- **Pandoc:** https://pandoc.org/ (markdown → PDF)

---

**Last Updated:** February 4, 2026
