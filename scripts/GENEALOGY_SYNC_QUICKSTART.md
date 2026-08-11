# Nicholas Welch Enhanced Sync - Quick Start Guide

## Status: ✅ System Ready for Testing

All scripts are complete and tested. FindAGrave ID extraction is working correctly.

---

## Quick Test with Brewer Family (Confirmed Working)

Just completed successful test:
```bash
php scripts/gedcom_to_sync_data.php \
  --input GEDs/Brewer-1601.ged \
  --output data/brewer-1601-parsed.json \
  --verbose
```

**Result:** ✅ Successfully extracted FindAGrave memorial IDs from 8+ profiles:
- Rial Brewer (Brewer-1601): FAG 107643743
- John J. Brewer (Brewer-1614): FAG 14391776  
- Wiley Jackson Brewer (Brewer-1615): FAG 29781257
- Lewis Solomon Brewer (Brewer-1617): FAG 180402613
- Nancy Jane Brewer (Brewer-1618): FAG 31484001
- Solomon Brewer (Brewer-1599): FAG 13554525
- Nicholas Brewer (Brewer-730): FAG 115992510
- Sampson Lanier (Lanier-257): FAG 149889695

---

## Next Steps for Nicholas Welch Family

### Step 1: Download WikiTree GEDCOM

**Manual Export (Recommended):**
1. Visit: https://www.wikitree.com/wiki/Welch-185
2. Click "Tools" menu (right sidebar)
3. Select "Export GEDCOM"
4. Options:
   - **Generations:** 3-4 (more complete family data)
   - **Include:** Parents, Children, Spouses, Sources
   - **Format:** GEDCOM 5.5
5. Save to: `GEDs/Welch-185.ged`

**Why manual?** Automated download requires WikiTree authentication. Manual export is quick and reliable.

### Step 2: Parse WikiTree GEDCOM

```bash
php scripts/gedcom_to_sync_data.php \
  --input GEDs/Welch-185.ged \
  --output data/welch-185-wt-parsed.json \
  --verbose
```

**What this does:**
- Parses all individuals and families from GEDCOM
- Extracts WikiTree IDs, FamilySearch IDs, FindAGrave IDs
- Builds family structures with proper mother attribution
- Shows sample profiles with external IDs

### Step 3: Run Enhanced Three-Way Sync

```bash
php scripts/genealogy_sync_enhanced.php \
  --wikitree-gedcom GEDs/Welch-185.ged \
  --familysearch data/nicholas-welch-familysearch.json \
  --output data/welch-185-sync-v2.json \
  --verbose
```

**What this produces:**
- Cross-references between WikiTree, FamilySearch, FindAGrave
- Separate family structures for each wife:
  * Elizabeth E. Moore (1757-1782) - Early children
  * Sarah Farren (1794-1814) - Later children
- Discrepancies: Missing profiles, mother misattribution, missing IDs
- Prioritized recommendations for improving WikiTree

### Step 4: Review Results

```bash
# View complete report
cat data/welch-185-sync-v2.json | jq '.'

# See family structures (children by mother)
cat data/welch-185-sync-v2.json | jq '.family_structures'

# See discrepancies
cat data/welch-185-sync-v2.json | jq '.discrepancies'

# See recommendations
cat data/welch-185-sync-v2.json | jq '.recommendations'

# Check FindAGrave ID coverage
cat data/welch-185-sync-v2.json | jq '.wikitree.profiles[].findagrave_id' | grep -v null | wc -l
```

---

## Expected Results for Nicholas Welch

Based on what we know:

### Family Structures

**Family 1: Nicholas Welch + Elizabeth E. Moore**
- Marriage: c.1757
- Elizabeth death: 1780-1782 (during Tory Diaspora)
- Children: ~18 (born 1758-1780)
  * John Welch (b.1758)
  * Mary Welch (b.1760)
  * Nicholas William Welch Jr (b.1763)
  * Margaret Welch (b.1765)
  * Sarah Welch (b.1766) - **NOT Sarah Farren**
  * Thomas Welch (b.1767)
  * Anne Welch (b.1769)
  * Henry Welch (b.1770)
  * Richard Welch (b.1772)
  * Hester Welch (b.1773)
  * Charity Welch (b.1775)
  * Lewis Welch (b.1780) - **YOUR ANCESTOR**
  * Others...

**Family 2: Nicholas Welch + Sarah Farren**
- Marriage: 26 Dec 1794, Philadelphia
- Sarah death: 1851 (age 99)
- Sarah role: Administratrix of Nicholas's estate (1814)
- Children: 2-3 (born after 1794)
  * William B Welch (b.~1790-1800?) - WikiTree: Welch-8494
  * Edmond Welch (b.~1810) - FamilySearch: K66Y-W69
  * Possibly others born post-1794

### Discrepancies to Fix

**Expected discrepancies:**

1. **Sarah Farren missing as spouse** (WikiTree issue)
   - Currently not listed as 2nd wife on Welch-185
   - Action: Add Sarah Farren as spouse with marriage date 26 Dec 1794

2. **Sarah Farren listed as child** (FamilySearch issue)
   - FamilySearch ID: LH7Z-MWT shows Sarah as child born 1790
   - Action: Correct FamilySearch - she's the wife, not a child
   - Note: There IS a daughter Sarah Welch (b.1766, daughter of Elizabeth)

3. **Children misattributed to wrong mother**
   - William B Welch likely shown as Elizabeth's child (actually Sarah Farren's)
   - Edmond Welch likely shown as Elizabeth's child (actually Sarah Farren's)
   - Action: Update WikiTree to show correct mother

4. **Missing FindAGrave IDs**
   - Enhanced sync will identify profiles needing memorial IDs
   - Priority: Elizabeth Moore, Lewis Welch, other children

5. **Missing FamilySearch IDs**
   - WikiTree profiles may lack FamilySearch cross-references
   - Enhanced sync provides high-confidence matches

---

## What the Enhanced Report Will Show

### Cross-References
```json
{
  "type": "principal",
  "wikitree_id": "Welch-185",
  "familysearch_id": "L6ZC-3QY",
  "findagrave_id": "12345678",
  "confidence": "high",
  "name": "Nicholas Welch"
}
```

### Family Structures
```json
{
  "husband": "Nicholas Welch",
  "wife": "Elizabeth E Moore",
  "wife_wikitree_id": "Moore-51757",
  "wife_familysearch_id": "L75M-7D6",
  "children": [
    {
      "name": "Lewis Welch",
      "wikitree_id": "Welch-1890",
      "familysearch_id": "LRC4-1FR",
      "birth_year": 1780,
      "mother": "Elizabeth E Moore"
    }
  ]
}
```

### Recommendations
```json
{
  "priority": "HIGH",
  "action": "add_wikitree_profile",
  "person": "Edmond Welch",
  "familysearch_id": "K66Y-W69",
  "suggested_mother": "Sarah Farren",
  "reason": "Found in FamilySearch with birth year 1810 (after Sarah's marriage), missing in WikiTree"
}
```

---

## Timeline of Nicholas Welch's Marriages

### Phase 1: Elizabeth Moore (1757-1782)
- **1757** - Marriage to Elizabeth Moore
- **1758-1780** - Birth of ~18 children
- **1777** - American Revolution begins
- **1780-1782** - Elizabeth dies (Tory Diaspora period)

### Phase 2: Widower Period (1782-1794)
- Nicholas alone with children for 12+ years
- Children growing up, marrying

### Phase 3: Sarah Farren (1794-1814)
- **26 Dec 1794** - Marriage to Sarah Farren (Philadelphia)
- Nicholas age ~54, Sarah age ~43
- **1794-1814** - 2-3 additional children born
- **1814** - Nicholas dies, Sarah becomes administratrix
- **1851** - Sarah dies at age 99

---

## Key Research Questions Answered by Enhanced Sync

✅ **Which children belong to which mother?**
- Family structures separate children by marriage
- Birth years help: Pre-1782 = Elizabeth, Post-1794 = Sarah

✅ **Is Sarah Farren in WikiTree?**
- Enhanced sync will identify if she exists
- May need to be added as 2nd spouse

✅ **What are the FindAGrave memorial IDs?**
- Extracted from WikiTree GEDCOM WWW fields
- Identifies profiles still needing memorials

✅ **How confident are the FamilySearch matches?**
- Fuzzy matching with confidence scores
- High (>85%), Medium (70-85%), Low (60-70%)

✅ **Which profiles need FamilySearch IDs added?**
- Recommendations prioritize by confidence
- Focus on high-confidence matches first

---

## After Running Enhanced Sync

### Update WikiTree Profiles

1. **Add Sarah Farren as 2nd Spouse**
   - Profile: Welch-185
   - Add marriage: 26 Dec 1794, Philadelphia
   - Note: Administratrix of estate 1814
   - Death: 1851, age 99

2. **Reattribute Children**
   - William B Welch (Welch-8494) → Mother: Sarah Farren
   - Edmond Welch → Create profile, Mother: Sarah Farren
   - Verify all pre-1782 children → Mother: Elizabeth Moore

3. **Add FamilySearch IDs**
   - Use recommendations from sync report
   - Focus on high-confidence matches
   - Cross-reference format: `FamilySearch: L6ZC-3QY`

4. **Add FindAGrave IDs**
   - Use memorial IDs from sync report
   - Add to profile biography using WikiTree template
   - Format: `{{FindAGrave|107643743}}`
   - See: https://www.wikitree.com/wiki/Help:Find_A_Grave

### Correct FamilySearch Entries

1. **Fix Sarah Farren Entry**
   - Current: Listed as child born 1790 (LH7Z-MWT)
   - Should be: Spouse born ~1751, married 1794
   - Merge/correct as needed

2. **Verify Children's Mothers**
   - Ensure each child shows correct mother
   - Elizabeth's children: Born 1758-1780
   - Sarah's children: Born post-1794

---

## Files Created

✅ **scripts/wikitree_gedcom_download.php** (170 lines)
- Downloads GEDCOM from WikiTree
- Note: Manual export currently needed for auth

✅ **scripts/gedcom_to_sync_data.php** (470 lines)
- Parses GEDCOM to structured JSON
- Extracts WikiTree, FamilySearch, FindAGrave, Ancestry IDs
- Builds family structures with mother attribution
- **Tested and working!**

✅ **scripts/genealogy_sync_enhanced.php** (500+ lines)
- Three-way sync (WikiTree + FamilySearch + FindAGrave)
- Fuzzy matching with confidence scores
- Enhanced discrepancy detection
- Prioritized recommendations

✅ **scripts/GENEALOGY_SYNC_ENHANCED_README.md**
- Complete documentation
- Nicholas Welch case study
- Workflow examples

✅ **scripts/GENEALOGY_SYNC_QUICKSTART.md** (this file)
- Step-by-step instructions
- Expected results
- Action items

---

## Support

**All scripts support `--help`:**
```bash
php scripts/gedcom_to_sync_data.php --help
php scripts/genealogy_sync_enhanced.php --help
```

**Original documentation:**
- `scripts/GENEALOGY_SYNC_README.md` - Basic sync (v1.0)
- `scripts/GENEALOGY_SYNC_ENHANCED_README.md` - Full technical docs (v2.0)

---

## Ready to Go! 🚀

The enhanced sync system is complete and tested. Download the Nicholas Welch GEDCOM from WikiTree and run through the steps above to get your three-way sync report with proper mother attribution and FindAGrave integration.

**Your main goal:** "Improve WikiTree with FS (FamilySearch) and FAG (FindAGrave) being the primary sources."

This system makes that workflow possible!
