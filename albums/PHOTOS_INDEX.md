# Family Heritage Album - Photo Index

## Photo Sources & Metadata

This file tracks photos for the family heritage album project. Include photos from:
1. **WikiTree photos** - extracted via WikiTree API
2. **Manually digitized** - scanned family photos
3. **Public domain/CC** - historical records (census, military records, newspapers)

### Format

```json
{
  "filename": "john_england_1900.jpg",
  "title": "John England, circa 1900",
  "wikitree_id": "England-1358",
  "date": "1900",
  "location": "Tennessee",
  "source": "family collection / WikiTree / public domain / archive",
  "description": "Portrait of John with..." ,
  "copyright": "public domain / CC0 / CC-BY / CC-BY-SA",
  "notes": "Any additional context"
}
```

## Album: England Pioneers (england-pioneers)

**Persons Included:**
- England-1357: David Edward England (birth name)
- England-1358: [to be determined from WikiTree]
- Gresham-182: [to be determined from WikiTree]
- Duncan-3524: [to be determined from WikiTree]

### Photos to Source

#### England-1357 Photos
- [ ] Portrait (any available)
- [ ] Family group photo
- [ ] Historical location photo

#### England-1358 Photos
- [ ] Portrait/photo
- [ ] Related family images

#### Gresham-182 Photos
- [ ] Photos from WikiTree
- [ ] Family collection photos

#### Duncan-3524 Photos
- [ ] Available historical photos

## Album: [Additional Albums]

Add more albums as needed. Use this format:

### [Album Name] ([album-code])

**Persons Included:**
- Person-ID: Name
- Person-ID: Name

### Photos to Source
- [ ] Photo 1
- [ ] Photo 2

## Photo File Organization

Store photos in `albums/photos/` with naming convention:
```
SURNAME-WIKITREEID-DESCRIPTION-YEAR.jpg
england-1357-portrait-1920.jpg
gresham-182-family-group-1880.jpg
```

## Creative Commons & Public Domain Notes

All photos in final album must be:
- **Public domain** (pre-1928 publications, government works, etc.)
- **Creative Commons licensed** (CC0, CC-BY, CC-BY-SA)
- **Original work** (family photos you digitized)

Document copyright/license status for each photo in metadata.

## Workflow

1. Identify needed photos for each person
2. Source from WikiTree, family archives, or public records
3. Digitize if needed
4. Add to `albums/photos/` with proper naming
5. Update metadata in this index
6. Reference in generated markdown files
7. Manual review and editing of markdown before PDF generation
