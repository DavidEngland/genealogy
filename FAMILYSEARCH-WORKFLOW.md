## FamilySearch to WikiTree Workflow Guide

### Step 1: Targeted FamilySearch Searches

**Before searching, verify in WikiTree profiles:**
- Known birth/death dates and locations
- Known spouse and children names  
- Known parent names
- Time period (century) to avoid modern records

**Search Strategy:**
```
Target: Lewis Welch (Welch-1890)
- Birth: 1780, North Carolina
- Father: Nicholas Welch
- Mother: Elizabeth Moore  
- Child: Elijah C. Welch (b. 1810, Hopkins Co, KY)
- Location: Hopkins County, Kentucky by 1810
```

**Recommended FamilySearch Collections for Lewis Welch:**
1. Kentucky land records (1800-1820) - Hopkins County
2. Kentucky tax lists (1800-1820) - Hopkins County
3. Kentucky vital records - marriages, deaths
4. North Carolina emigration records (1790-1810)
5. Tennessee records (if migrated like father Nicholas)

**Collections to AVOID for ancestors born pre-1800:**
- ❌ North Carolina Deaths and Burials, 1898-1994
- ❌ North Carolina Birth Index, 1800-2000 (too broad, modern focus)
- ❌ NC Department of Archives Index to Vital Records, 1800-2000 (mostly 1900s)
- ❌ Davidson County Vital Records, 1867-2006

### Step 2: Export and Save Search Results

1. Perform FamilySearch search
2. Export results to CSV
3. Save to `search-results/` folder with descriptive name:
   - Format: `FS-PersonName-WikiTreeID.csv`
   - Example: `FS-LewisWelch-1890.csv`

### Step 3: Process CSV to Markdown Sources

**Basic command:**
```bash
php scripts/csv_to_wikitree_sources.php \
  --in "search-results/FS-LewisWelch-1890.csv" \
  --out "sources/FS-LewisWelch-1890-sources.md"
```

**With filters (recommended):**
```bash
# Filter modern records by excluding common modern collections
# Filter by role to focus on principal person
# Filter by date ranges if needed
```

### Step 4: Manual Review and Analysis

Create analysis file in `sources/` folder:
- Review each record for relevance
- Verify person identity by cross-checking dates, locations, family members
- Mark records as: ✅ Verified Match, ⚠️ Needs Investigation, ❌ Not a Match
- Document reasoning for each determination

**Key verification points:**
1. **Dates match** - Birth/death within expected range
2. **Locations match** - Documented residence areas
3. **Family members match** - Known spouse, children, parents, siblings
4. **Name variants acceptable** - Lewis/Louis, Welch/Welsh/Wellch
5. **Time period appropriate** - Not 100+ years off

### Step 5: Update WikiTree Profile Markdown

Only add verified sources to profile:

**For verified sources:**
```markdown
=== Life in Hopkins County, Kentucky ===

Lewis Welch appears in Hopkins County land records...<ref>"Kentucky Land Records, 1780-1850," database, [https://www.familysearch.org/ark:/61903/1:1:XXXX FamilySearch], (accessed 31 Jan 2026), entry for Lewis Welch, Hopkins County, Kentucky.</ref>
```

**For unverified records:**
- Keep in analysis file only
- Do NOT add to WikiTree profile
- Note as research lead for future investigation

### Step 6: Document Research Progress

Add to profile's Research Notes section:

```markdown
== Research Notes ==

* FamilySearch search conducted 31 Jan 2026 for Lewis Welch in NC/KY/TN collections
* Search results: 20 records found, 0 verified matches (see sources/FS-NickWelch-filtered-analysis.md)
* Recommendation: Focus on Hopkins County, KY land and court records 1800-1820
* Mother's identity remains unknown - requires further research in Hopkins County vital records
```

---

## Research Notes CRUD (Per-Person Files)

**Location rule:** store active notes in `ancestors/family/<Surname>/<Surname>-<WikiTreeID>-notes.md`.

**Example:** `ancestors/family/White/White-16150-notes.md`

### Required Metadata (Evidence in Front Matter)
Use YAML front matter at the top of each notes file:

```yaml
---
person: Full Name (WikiTreeID)
wikitree_id: WikiTreeID
file_role: research-notes
status: active
last_updated: YYYY-MM-DD
evidence:
  - "Short citation or record description"
  - "Short citation or record description"
---
```

### CRUD Workflow

**Create**
1. Create notes file at the path above.
2. Add metadata and the standard sections: Summary, Research Questions, Findings, Conflicts & Hypotheses, Next Actions, Sources.

**Read**
- The profile file should contain a short Research Notes section that links to the notes file for full details.

**Update**
1. Add new evidence entries to the metadata list.
2. Update Findings and Next Actions with dates and outcomes.
3. Record resolved conflicts and move them to Findings.

**Close/Archive**
- When research is complete, set `status: archived` and add a final resolution note in the Summary.

### Standard Section Template

```markdown
# Research Notes — Full Name (WikiTreeID)

## Summary

## Research Questions

## Findings

## Conflicts & Hypotheses

## Next Actions

## Sources
```

### Common Pitfalls to Avoid

1. **Don't assume name match = person match**
   - Verify dates, locations, family members
   - Common names like Lewis Welch appear in many unrelated families

2. **Don't add unverified sources to WikiTree**
   - Keep analysis separate from final profile
   - Only add sources you can reasonably verify

3. **Don't ignore time period mismatches**
   - A person born in 1780 won't appear in 1898-1994 death records
   - Filter out collections focused on wrong century

4. **Don't overlook negative results**
   - Document searches that found nothing
   - Prevents repeating same searches
   - Guides future research directions

### Batch Processing Multiple Searches

For processing multiple CSV files:
```bash
# Process all search-results/*.csv files
php scripts/batch-sources.php --dry-run --verbose

# Review dry-run output, then execute
php scripts/batch-sources.php
```

### Configuration File Template

Create `scripts/batch-sources-config.json`:
```json
{
  "FS-LewisWelch-1890.csv": {
    "filters": {
      "skip_collections": [
        "1898-1994",
        "1867-2006", 
        "1931-1994"
      ],
      "only_principal": true
    }
  }
}
```

### Research Planning Template

Before starting new FamilySearch research:

**Person:** [Name] ([WikiTree ID])  
**Birth:** [Date] in [Location]  
**Death:** [Date] in [Location]  
**Parents:** [Names]  
**Spouse:** [Name]  
**Children:** [Names]  
**Known Locations:** [List counties/states where person lived]  
**Time Period:** [Decade range, e.g., 1780-1850]  

**Target FamilySearch Collections:**
1. [Collection name and date range]
2. [Collection name and date range]

**Expected Record Types:**
- Land deeds
- Tax lists
- Court records
- Marriage records
- Death records

**Search Terms:**
- Primary: [Full name]
- Variants: [Name spellings]
- Associated: [Spouse, children, parent names]

**Success Criteria:**
- Find birth record with parents named
- Find marriage record with spouse
- Find land transactions in known county
- Find court records with known associates
