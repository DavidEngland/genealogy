# Adding Known Matches to 16th Regiment WikiTree Search

## Overview

The search script now supports "known matches" - soldiers whose WikiTree IDs have already been verified and don't need to be looked up via the API.

## Current Known Matches

The script currently has these pre-verified matches:

- **Valentine Hargrove** (adjutant) → `Hargrove-277`
- **James Hartgrove** (private) → `Hargrove-287`
  - Note: Spelled "Hartgrove" in the muster roll, not "Hargrove"

## How to Add More Known Matches

### Step 1: Find the KNOWN_MATCHES Dictionary

In `scripts/search_16th_regiment.py`, find this section (around line 45-50):

```python
# Known matches - soldiers already verified on WikiTree
# Format: (FirstName, LastName, Rank) -> WikiTree ID
KNOWN_MATCHES = {
    ('Valentine', 'Hargrove', 'adjutant'): 'Hargrove-277',
    ('James', 'Hartgrove', 'private'): 'Hargrove-287',
    # Add more verified matches here as needed
}
```

### Step 2: Add New Match

Add your verified match in the same format:

```python
KNOWN_MATCHES = {
    ('Valentine', 'Hargrove', 'adjutant'): 'Hargrove-277',
    ('James', 'Hartgrove', 'private'): 'Hargrove-287',
    ('FirstName', 'LastName', 'rank'): 'WikiTree-ID',
}
```

**Important notes:**

- **FirstName**: Must match exactly as in muster roll
- **LastName**: Must match exactly (including spelling variations like "Hartgrove" vs "Hargrove")
- **rank**: Must be lowercase (e.g., 'private', 'captain', 'adjutant')
- **WikiTree-ID**: Format is `Surname-Number` (e.g., `Adams-567`, `Lewis-8883`)

### Step 3: Example

If you verified that "John Smith, private" on the muster roll is WikiTree ID `Smith-1234`:

```python
KNOWN_MATCHES = {
    ('Valentine', 'Hargrove', 'adjutant'): 'Hargrove-277',
    ('James', 'Hartgrove', 'private'): 'Hargrove-287',
    ('John', 'Smith', 'private'): 'Smith-1234',
}
```

## Features of Known Matches

When a known match is found:

✓ **100% Confidence** - Shows as 1.0 confidence score
✓ **No API Call** - Skips WikiTree API search to save time
✓ **Labeled** - Shows "Known match (pre-verified)" in location_reason column
✓ **CSV Output** - Appears in the results CSV with full WikiTree ID

## Benefits

1. **Faster searches** - Known matches skip the 2-second API delay
2. **Guaranteed accuracy** - Verified IDs won't change between runs
3. **Easy updates** - Simply add verified matches to the dictionary
4. **Visible in output** - Easy to see which matches are pre-verified vs. API-found

## How to Get More Verified Matches

1. **Manual verification** - Search WikiTree manually and verify names/dates match
2. **Family groupings** - Use relatives you've already found
3. **Cross-reference** - Check against:
   - FamilySearch War of 1812 records
   - Pension application records
   - Land grants
   - Census records
   - Other genealogy sites

## Testing Your Additions

After adding a known match, run the script and check:

```bash
# Run test with known match included
python3 scripts/search_16th_regiment.py 300

# Search the results CSV
grep -i "YourLastName" search-results/16th_regiment_wikitree_search.csv
```

Expected output:
- WikiTree ID populated
- Confidence = 1.0
- Location reason = "Known match (pre-verified)"

## Current Examples

### Valentine Hargrove (Hargrove-277)
- Rank: Adjutant
- Role: Administrative officer
- Relationship: Father of James Hargrove

### James Hartgrove (Hargrove-287)  
- Rank: Private
- Role: Regular soldier
- Relationship: Son of Valentine Hargrove
- Note: Surname spelled differently in muster roll

## Tips for Future Matches

- **Document your sources** - Note where you verified each match
- **Look for family connections** - Multiple family members may be in the regiment
- **Check variations** - Some names have spelling variations (Hargrove/Hartgrove, McDonal/McDonald)
- **Use WikiTree links** - The `See Also` section may show relatives

## Questions?

Refer to `QUICKSTART_16TH_REGIMENT.md` for general usage or `IMPLEMENTATION_16TH_REGIMENT_COMPLETE.md` for technical details.
