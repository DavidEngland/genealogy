# WikiTree API Integration Notes

## WikiTree API Access

The Heritage Album generator fetches profile data from WikiTree's public API at `https://api.wikitree.com/api.php`.

### Authentication

WikiTree's API has different access levels:

1. **Public API** (no authentication)
   - Limited data fields
   - Rate-limited (~2 requests/second)
   - No photo data in most cases
   - Good for: Basic name, dates, relationships

2. **Authenticated API** (requires WikiTree account)
   - More detailed biography, photos
   - Higher rate limits
   - More complete family data
   - Requires: OAuth token or API key

### Current Implementation

The `wikitree_heritage_client.php` uses the **public API**:
- No authentication required
- Returns: Name, BirthDate, DeathDate, locations, basic relationships
- Rate limiting: 2-second delay between requests
- Built-in retry logic with exponential backoff
- Local caching to reduce API calls

### To Improve WikiTree Data Fetching

**Option 1: Use WikiTree Credentials**
Update `wikitree_heritage_client.php` to authenticate:
```php
// Add OAuth token or API credentials
private $token = 'your_wikitree_token_here';

// Modify makeRequest() to include authentication header
```

**Option 2: Export GEDCOM from WikiTree**
1. Go to your WikiTree profile
2. Download GEDCOM file(s)
3. Use existing `schema/gedcom_parser.php` to convert to JSON
4. Use JSON data instead of API

**Option 3: Manual Data Entry**
1. Copy profile text from WikiTree
2. Paste into generated markdown
3. Edit and enhance manually

## Fallback: Manual WikiTree Data Entry

If API returns incomplete data:

1. Visit the person's WikiTree profile
   - Format: `https://www.wikitree.com/wiki/WikiTree-ID`
   - Example: `https://www.wikitree.com/wiki/England-1357`

2. Copy profile sections:
   - Biography
   - Life dates
   - Family information
   - Photo references

3. Paste into generated markdown in `albums/builds/`

4. Edit as needed for album formatting

## WikiTree API Reference

### Available Fields

```
Id              - WikiTree ID (e.g., "England-1357")
Name            - Full name
FirstName       - Given name
LastName        - Surname
BirthDate       - Birth date string
BirthLocation   - Birth location
DeathDate       - Death date string
DeathLocation   - Death location
Gender          - M or F
Father          - Father's WikiTree ID
Mother          - Mother's WikiTree ID
Spouses         - Array of spouse WikiTree IDs
Children        - Array of child WikiTree IDs
Photo           - Photo identifier
Biography       - Full biography text (when authenticated)
```

### Example API Call

```
https://api.wikitree.com/api.php?action=getProfile&key=England-1357&fields=Id,Name,BirthDate,DeathDate,Father,Mother,Spouses,Children&appId=HeritageAlbum
```

### API Rate Limits

- Public API: ~2 requests per second (per IP)
- Authenticated: Higher limits
- Respect rate limits to avoid IP blocking

## For Better Results

**Recommended workflow:**

1. Use script for automated data fetching (names, dates, relationships)
2. Visit WikiTree directly for:
   - Full biography text
   - Photo sourcing
   - Detailed relationships
   - Source citations
3. Paste WikiTree data into generated markdown
4. Enhance with family stories and additional context
5. Add photos from your collection

## WikiTree Photo Sourcing

WikiTree maintains a photo library. For each person:

1. Go to WikiTree profile
2. Look for photo thumbnails
3. Click to view full-size images
4. Check copyright/license information
5. Download or reference URL in your album

All photos on WikiTree are user-submitted with their own copyright/license information clearly marked.

## Future Enhancement

When you've identified your WikiTree authentication method, we can update the scripts to:
- Fetch biography text automatically
- Pull photo data with URLs
- Get complete relationship data
- Reduce need for manual WikiTree visits

For now, use the generated markdown as a **template** that you'll populate by visiting WikiTree directly.

---

**Note:** The album markdown generator creates a well-formatted structure. Your primary task is:
1. Run the generator
2. Visit WikiTree manually to get the detailed data
3. Fill in the generated markdown template
4. Add photos and stories
5. Convert to PDF

This hybrid approach gives you full control over content while automating the structural layout.

- Draft bios should be flagged “sources pending” until citations are added.
- Draft summaries should be explicitly labeled “unsourced” until citations are attached.
