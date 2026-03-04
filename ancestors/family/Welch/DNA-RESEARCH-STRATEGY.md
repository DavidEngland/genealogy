# DNA Research Strategy for Nicholas Welch Marriage Resolution

**Author:** David England  
**Date:** February 27, 2026  
**Purpose:** Resolve conflicting marriage records and identify which wives were named Elizabeth Moore

---

## The Research Problem

### Current Hypotheses

**Hypothesis 1: Two Wives Named Elizabeth, Both Moores**
- **Wife #1:** Elizabeth Moore (unknown-c. 1757?) - Unknown first wife mentioned by Connie Voss
- **Wife #2:** Elizabeth E. Moore (c. 1740-c. 1780-1782) - Married 1757, died during exile
- **Wife #3:** Sarah Farren (m. 1794) OR Sarah Welch (daughter, b. 1766)?

**Hypothesis 2: Philadelphia Wife-Shopping Pattern**
- Nicholas documented in Philadelphia for marriage to Sarah Farren (1794)
- Did he travel to Philadelphia for other marriages too?
- Was "Elizabeth Moore" from Philadelphia area, not Virginia/NC?

**Hypothesis 3: The Sarah Confusion**
- **Sarah Farren:** Married Nicholas Welsh (alternate spelling) 26 Dec 1794 in Philadelphia
- **Sarah Welch:** Daughter (b. 1766) who administered estate in 1814
- **Question:** Same person? Was Sarah Farren's full name "Sarah Elizabeth Farren"?
- **Question:** Did daughter Sarah administer estate because stepmother Sarah was elderly/incapable?

---

## DNA Testing Strategies

### 1. Y-DNA Analysis (Male Welch Line)

**Purpose:** Confirm direct paternal lineage and identify which children belong to which mother

**Who to Test:**
- Male descendants of **Lewis Welch** (b. 1780) - youngest documented son, likely Elizabeth E. Moore's child
- Male descendants of **John Welch** (b. 1758) - oldest son, possibly from first Elizabeth?
- Male descendants of **William C. Welch** (b. c. 1758-1760) - similar age to John
- Male descendants of **Nicholas William Welch** (b. 1762)
- Male descendants of **Richard Welch** (b. 1770 Maryland)

**Y-DNA Test Level Recommendations:**
- **Y-37 or Y-67:** Distinguish between close relatives and confirm same paternal line
- **Y-111 or Big Y-700:** Identify unique genetic signatures (SNPs) for each wife's children
- **Expected Outcome:** If all children share same Y-DNA, confirms Nicholas Welch is father of all. Different haplogroup branches might indicate children from different time periods (different mothers).

**WikiTree DNA Features:**
- Check WikiTree profiles for existing Y-DNA haplogroup data
- Use WikiTree's "DNA Tests" feature to identify who has tested
- Compare Y-DNA results through WikiTree's DNA matching tools

### 2. Autosomal DNA Analysis (All Descendants)

**Purpose:** Identify maternal lineage differences and Moore family connections

**Who to Test:**
- Descendants of children born 1758-1762 (potential first Elizabeth's children)
- Descendants of children born 1762-1780 (Elizabeth E. Moore's confirmed children)
- Descendants of any child born post-1794 (Sarah Farren's children, if any)

**Autosomal DNA Strategy:**

**A. Moore Family Matching**
Compare Welch descendants' autosomal DNA to:
- Descendants of **Lt. Col. John William Moore (1706-1777)** and Mary Susannah Jouett
- Descendants of **Captain Moses Moore (1718-1797)** and Hester Winston
- Other Moore families in Lincoln County, NC area

**Expected Outcomes:**
- **If Elizabeth E. Moore = daughter of John William Moore:** Welch descendants will match Jouett family DNA
- **If Elizabeth E. Moore = daughter of Moses Moore:** Welch descendants will match Winston family DNA
- **If TWO Elizabeth Moores married Nicholas:** Some Welch descendants will match one Moore line, others will match different Moore line

**B. Shared cM Analysis**
- Children of same mother should share more DNA than children of different mothers
- Expected shared cM between half-siblings: ~1,700 cM average
- Expected shared cM between full siblings: ~2,600 cM average
- Test multiple descendants and compare shared cM values

**C. X-DNA Analysis** (Especially powerful for maternal lineage)
- X-DNA passes differently through maternal vs paternal lines
- Female descendants inherit one X from mother, one from father
- Compare X-DNA matches to identify which children share same mother

### 3. mtDNA Analysis (Maternal Line Only)

**Purpose:** Definitively prove which children share same mother

**Who to Test:**
- Direct maternal-line descendants (mother → daughter → daughter → daughter)
- Test descendants through **different daughters** of Nicholas Welch to see if mtDNA matches

**Expected Outcomes:**
- **Mary Welch** (b. 1762), **Sarah Welch** (b. 1766), **Hester Welch** (b. 1768) - if all share same mtDNA, same mother
- If mtDNA differs, indicates different mothers

**WikiTree mtDNA Data:**
- Check for existing mtDNA haplogroup data on WikiTree profiles
- Maternal haplogroup differences would definitively prove different mothers

---

## WikiTree DNA Tools and API

### Available WikiTree Features

**1. DNA Tests Field**
- WikiTree profiles can include DNA test information
- Fields: `HasDNATest`, `mtDNA`, `yDNA`, `TestsTaken`
- API endpoint: Include in `fields` parameter when fetching profiles

**2. DNA Confirmations**
- WikiTree has "DNA Confirmed" ancestor stickers
- Shows documented DNA confirmation of relationship
- Visible in profile badges

**3. DNA Descendants Tool**
- WikiTree provides DNA descendant tree viewer
- Example: `https://www.wikitree.com/treewidget/Welch-185/890` (DNA descendants)
- Shows tested descendants and their connections

**4. Connecting DNA Tests**
- WikiTree users can connect GEDmatch, FTDNA, Ancestry DNA kit numbers
- Enables matching between WikiTree members

### API Implementation

**Current Status:**
Your existing `wikitree_api_client.php` has:
- `fetchProfile()` - Can request DNA fields
- `fetchRelatives()` - Can pull family with DNA data
- `fetchAncestors()` - For checking ancestral DNA confirmations

**Recommended API Enhancement:**

```php
/**
 * Fetch DNA information for a profile
 * 
 * @param string $id WikiTree ID
 * @return array|false DNA data or false on failure
 */
public function fetchDNA(string $id) {
    $dnaFields = [
        'Id', 'Name', 'FirstName', 'LastNameAtBirth',
        'BirthDate', 'DeathDate',
        'HasDNATest', 'mtDNA', 'yDNA', 'TestsTaken',
        'DataStatus'  // Check if DNA confirmed
    ];
    
    $profile = $this->fetchProfile($id, $dnaFields);
    
    if ($profile === false) {
        return false;
    }
    
    // Extract DNA-specific data
    return [
        'profile_id' => $profile['Name'] ?? null,
        'has_test' => $profile['HasDNATest'] ?? false,
        'mtdna_haplogroup' => $profile['mtDNA'] ?? null,
        'ydna_haplogroup' => $profile['yDNA'] ?? null,
        'tests_taken' => $profile['TestsTaken'] ?? null,
        'dna_status' => $profile['DataStatus']['DNA'] ?? null
    ];
}

/**
 * Compare DNA data across multiple profiles
 * 
 * @param array $profileIds Array of WikiTree IDs
 * @return array Comparison matrix
 */
public function compareDNA(array $profileIds): array {
    $results = [];
    foreach ($profileIds as $id) {
        $results[$id] = $this->fetchDNA($id);
    }
    return $results;
}
```

**Usage Example:**

```bash
# Fetch DNA data for Nicholas Welch's children
php scripts/wikitree_dna_check.php \
  --profiles "Welch-8499,Welch-3467,Welch-11475,Welch-8473,Welch-1890" \
  --output welch_children_dna.json
```

---

## Research Action Plan

### Phase 1: Inventory Existing DNA Tests (Immediate)

**Tasks:**
1. Check WikiTree profiles for all Nicholas Welch children
2. Note which descendants have taken DNA tests
3. Document haplogroups (Y-DNA, mtDNA) where available
4. Contact WikiTree DNA test-takers listed in NicohlosWelch.md:
   - Char Hubbard
   - K Hagerman
   - Mark Colomb
   - Van Vandiver

**Script to Create:**
```bash
php scripts/wikitree_fetch_dna.php --profile Welch-185 --descendants
```

### Phase 2: Targeted DNA Testing Recruitment (1-3 months)

**Objectives:**
1. Find male descendants of **John Welch (b. 1758)** for Y-DNA
2. Find male descendants of **Lewis Welch (b. 1780)** for Y-DNA comparison
3. Find female maternal-line descendants for mtDNA testing

**Outreach Strategy:**
- Post on WikiTree G2G (Genealogist-to-Genealogist) forum
- Contact profile managers of Welch descendants
- Post on relevant Facebook genealogy groups:
  - Welch/Welsh Family Genealogy
  - Lincoln County NC Genealogy
  - Tennessee Genealogy

### Phase 3: DNA Analysis and Comparison (3-6 months)

**Analysis Steps:**
1. Upload all DNA tests to GEDmatch for cross-comparison
2. Compare shared cM values between descendants
3. Analyze X-DNA inheritance patterns
4. Compare Y-DNA STR values and haplogroups
5. Compare mtDNA haplogroups between children

**Expected Timeline:**
- **Y-DNA:** Results in 6-8 weeks
- **Autosomal DNA:** Results in 4-6 weeks
- **mtDNA:** Results in 6-8 weeks

### Phase 4: Documentation and Conclusion (Ongoing)

**Deliverables:**
1. **DNA Evidence Report** documenting findings
2. **Updated WikiTree profiles** with DNA-confirmed relationships
3. **Resolution of Marriage Records** - definitively identify:
   - How many times Nicholas married
   - Which children belong to which mother
   - Identity of "unknown first wife" Elizabeth Moore
   - Clarify Sarah Farren vs. Sarah Welch (daughter) confusion

---

## Specific Questions DNA Can Resolve

### Question 1: Were there two Elizabeths, both Moores?

**DNA Strategy:**
- Test descendants of **early children (1758-1762)** vs **later children (1765-1780)**
- If both groups match **DIFFERENT Moore family lines**, confirms two Elizabeths
- If both match **SAME Moore line**, likely one Elizabeth with variant birth date

**Outcome Scenarios:**
- **Scenario A:** Early children match John William Moore line, later children match Moses Moore line → TWO different Elizabeths
- **Scenario B:** All children match same Moore line → ONE Elizabeth, conflicting records
- **Scenario C:** Children don't match any Moore line → Elizabeth NOT a Moore (error in records)

### Question 2: Sarah Farren = Sarah Welch (daughter)?

**DNA Strategy:**
- Check for children born **after 1794** who might be Sarah Farren's children
- If Sarah Farren had children, they would be **half-siblings** to Nicholas's earlier children
- Autosomal DNA would show reduced shared cM with Sarah Farren's children

**Documentary Evidence to Cross-Reference:**
- 1814 probate: "Sarah WELCH, widow" vs "Sarah Welch, daughter administratrix"
- Age estimate: Sarah Welch (daughter) would be 48 in 1814 - old enough to administer
- Philadelphia marriage record: Does it give age for Sarah Farren?

### Question 3: Which children belong to which mother?

**DNA Strategy:**
- **Full Siblings Test:** Full siblings share ~50% DNA (2,600 cM average)
- **Half Siblings Test:** Half siblings share ~25% DNA (1,700 cM average)
- Test multiple descendants and create **relationship matrix**

**Expected Pattern if Three Mothers:**
```
Mother 1 (Elizabeth Moore #1, pre-1757):
- John Welch (b. 1758)?
- William C. Welch (b. 1758-1760)?

Mother 2 (Elizabeth E. Moore, 1757-1782):
- Judith (b. 1761)
- Nicholas William (b. 1762)
- Mary (b. 1762)
- Margaret (b. c. 1765)
- Thomas (b. 1765)
- Sarah (b. 1766)
- Hester (b. 1768)
- John (b. 1769)
- Richard (b. 1770)
- Lewis (b. 1780)

Mother 3 (Sarah Farren, 1794-1814):
- Unknown children born 1795-1814?
```

---

## DNA Resources and Tools

### Testing Companies
- **FamilyTreeDNA** - Best for Y-DNA and mtDNA, good autosomal
- **23andMe** - Good autosomal, can transfer to GEDmatch
- **AncestryDNA** - Largest database, good for finding cousins
- **MyHeritage DNA** - Good international coverage

### Analysis Tools
- **GEDmatch** - Free DNA comparison across testing companies
- **DNA Painter** - Chromosome mapping and relationship tools
- **Genetic Affairs** - Cluster analysis for complex relationships
- **WikiTree DNA Tools** - Built-in matching within WikiTree community

### WikiTree DNA Resources
- **WikiTree DNA Project:** https://www.wikitree.com/wiki/Space:DNA
- **DNA Confirmation Guide:** https://www.wikitree.com/wiki/Help:DNA_Confirmation
- **G2G DNA Questions Forum:** https://www.wikitree.com/g2g/dna

---

## Next Steps

1. **Run DNA inventory script** on all Nicholas Welch descendants
2. **Contact existing DNA test-takers** in WikiTree Welch line
3. **Create recruitment post** for G2G forum requesting Y-DNA testers
4. **Document DNA testing plan** on Nicholas Welch WikiTree profile
5. **Set up GEDmatch project** for Welch DNA comparisons

---

## Cost Estimates

| Test Type | Company | Cost | Purpose |
|-----------|---------|------|---------|
| Y-DNA 37 | FamilyTreeDNA | $169 | Basic paternal lineage |
| Y-DNA 111 | FamilyTreeDNA | $359 | Detailed paternal analysis |
| Big Y-700 | FamilyTreeDNA | $449 | SNP discovery, deep ancestry |
| Autosomal | AncestryDNA | $79 | Find cousins, maternal lineage |
| Autosomal | 23andMe | $99 | Maternal/paternal haplogroups |
| mtDNA Full Sequence | FamilyTreeDNA | $199 | Maternal lineage confirmation |

**Recommended Minimum Investment:** ~$500-700 for 2-3 strategic tests

---

## Timeline

- **Month 1:** Inventory and outreach
- **Month 2-3:** DNA test ordering and sample collection
- **Month 4-5:** Results arrival and initial analysis
- **Month 6+:** Comparative analysis and documentation

---

## Contact for DNA Collaboration

**Profile Manager:** David England  
**WikiTree Profile:** [[England-1357|David England]]  
**Research Focus:** Nicholas Welch lineage, War of 1812 connections, Loyalist migrations

---

*This document should be updated as DNA results become available and new discoveries are made.*
