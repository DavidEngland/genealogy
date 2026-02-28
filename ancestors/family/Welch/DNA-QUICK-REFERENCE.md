# Nicholas Welch Marriage Mysteries - Quick Reference

**Last Updated:** February 27, 2026  
**Status:** Research in Progress - DNA Testing Required

---

## The Core Questions

### 1. Two Wives Both Named Elizabeth Moore?

**Evidence FOR:**
- Early children (John b. 1758, William b. 1758-1760) vs. later children timing
- Two John Welches (b. 1758 and b. 1769) - naming pattern suggests different mothers
- Multiple Elizabeth Moores documented in Lincoln County, NC Moore families
- Connie Voss mentions "first wife unknown" separate from Elizabeth E. Moore

**Evidence AGAINST:**
- Only one marriage record (1757) to Elizabeth Moore found
- St. Johns Town testimony (1782) mentions "widower with six children" - but which six?
- No documentary evidence of first Elizabeth's death or burial

**DNA Will Show:**
If descendants of early children (1758-1762) match **different Moore family DNA** than descendants of later children (1765-1780), this proves two Elizabeths.

### 2. Sarah Farren vs. Sarah Welch (daughter)?

**The Problem:**
- Philadelphia marriage record: Nicholas Welsh m. Sarah Farren, 26 Dec 1794
- Giles County probate (1814): "Sarah WELCH, widow" administering estate
- Nicholas had daughter Sarah Welch (b. 1766) - could she have administered?

**Possible Scenarios:**
1. **Same person:** Sarah Farren married Nicholas, became Sarah Welch, served as widow
2. **Different people:** Daughter Sarah administered because stepmother Sarah Farren was elderly/incapacitated
3. **Name variant:** Sarah Farren's full name was "Sarah Elizabeth Farren" - used Sarah or Elizabeth interchangeably

**DNA Will Show:**
If any children born 1795-1814 exist, they would be half-siblings to earlier children (different mother). Autosomal DNA would show reduced shared cM (~1,700 vs ~2,600).

### 3. Philadelphia "Wife-Shopping" Pattern?

**Observation:** Nicholas documented in Philadelphia for Sarah Farren marriage (1794)
**Question:** Did he also travel to Philadelphia for earlier marriages?

**Why This Matters:**
- Elizabeth Moore traditionally identified as Virginia/NC native
- But was she from Philadelphia area instead?
- Would explain Philadelphia connection and repeated trips

---

## DNA Testing Priority List

### Immediate Priority: Y-DNA (Male Line)

**Who to Test:**
1. Male descendants of **John Welch** (b. 1758) - eldest son
2. Male descendants of **Lewis Welch** (b. 1780) - youngest son
3. Compare Y-DNA markers - should match if same father

**Cost:** ~$169-449 depending on test level  
**Timeline:** 6-8 weeks for results

### Secondary Priority: Autosomal DNA

**Who to Test:**
1. Descendants of children born 1758-1762 (potential first Elizabeth)
2. Descendants of children born 1765-1780 (Elizabeth E. Moore confirmed)
3. Compare shared cM values and chromosome segments

**Cost:** ~$79-99 per test  
**Timeline:** 4-6 weeks for results

### Tertiary Priority: mtDNA (Maternal Line)

**Who to Test:**
Direct maternal-line descendants through daughters:
- Through Mary Welch (b. 1762)
- Through Sarah Welch (b. 1766)
- Through Hester Welch (b. 1768)

If mtDNA matches = same mother  
If mtDNA differs = different mothers

**Cost:** ~$199 for full sequence  
**Timeline:** 6-8 weeks for results

---

## How to Run WikiTree DNA Inventory

```bash
cd /Users/davidengland/Documents/GitHub/genealogy

# Fetch DNA data for Nicholas Welch and all descendants
php scripts/wikitree_fetch_dna.php --profile Welch-185 --descendants --depth 4 --verbose

# Output will be saved to: Welch-185_dna_data.json
```

**What This Does:**
- Fetches WikiTree profile data for Nicholas Welch
- Recursively fetches 4 generations of descendants
- Extracts DNA-related information from profiles
- Identifies which descendants have taken DNA tests
- Generates contact list for DNA testing recruitment

**Next Steps After Running:**
1. Review `Welch-185_dna_data.json` for existing DNA test-takers
2. Contact WikiTree profile managers
3. Post recruitment message on WikiTree G2G forum
4. Organize DNA testing campaign

---

## WikiTree Resources

### DNA Information
- **WikiTree DNA Descendants Tree:** https://www.wikitree.com/treewidget/Welch-185/890
- **WikiTree DNA Project:** https://www.wikitree.com/wiki/Space:DNA
- **DNA Confirmation Guide:** https://www.wikitree.com/wiki/Help:DNA_Confirmation

### API Documentation
- **WikiTree API Docs:** https://github.com/wikitree/wikitree-api
- **Your API Client:** `/scripts/wikitree_api_client.php`
- **DNA Fetch Script:** `/scripts/wikitree_fetch_dna.php`

### Forum for Questions
- **G2G Forum DNA Section:** https://www.wikitree.com/g2g/dna
- Post title suggestion: "Seeking DNA testers to resolve Nicholas Welch (1738-1814) marriage records - Two Elizabeths theory"

---

## Known DNA Test-Takers (from NicohlosWelch.md)

At least 9 DNA test-takers on WikiTree are descendants of Nicholas Welch:
- Char Hubbard
- K Hagerman
- Mark Colomb
- Van Vandiver
- (Others listed on profile)

**Action:** Contact these individuals through WikiTree messaging to:
1. Confirm their DNA test type (Y-DNA, autosomal, mtDNA)
2. Request permission to compare results
3. Ask which Nicholas Welch child they descend through
4. Coordinate GEDmatch uploads for comparison

---

## Expected Timeline

| Phase | Duration | Activities |
|-------|----------|------------|
| **Phase 1: Inventory** | 2-4 weeks | Run scripts, contact existing testers, recruit new testers |
| **Phase 2: Testing** | 2-3 months | DNA test kits ordered, samples collected, labs process |
| **Phase 3: Analysis** | 1-2 months | Upload to GEDmatch, compare results, analyze patterns |
| **Phase 4: Documentation** | Ongoing | Update WikiTree profiles, publish findings |

**Total Timeline:** 4-8 months for conclusive results

---

## Cost Estimate

### Conservative Approach (Minimum)
- 2 Y-DNA tests (Y-37 level): $338
- 3 Autosomal tests: $237
- **Total:** ~$575

### Comprehensive Approach (Recommended)
- 3 Y-DNA tests (Y-111 level): $1,077
- 5 Autosomal tests: $395
- 2 mtDNA tests (full sequence): $398
- **Total:** ~$1,870

### Budget-Friendly Start
- 1 Y-DNA test (Y-37): $169
- 2 Autosomal tests: $158
- **Total:** ~$327

---

## Key Documents

1. **DNA-RESEARCH-STRATEGY.md** - Complete 5,000+ word guide
2. **Welch-185.md** - Nicholas Welch WikiTree profile (comprehensive narrative)
3. **Welch-185.md** (root) - Nicholas Welch WikiTree profile (short version)
4. **Moore-51757.md** - Elizabeth E. Moore profile with parentage theories
5. **NicohlosWelch.md** - Historical narrative by Connie Voss (mentions 3 wives)
6. **Hesters Bluff.md** - St. Johns Town source (widower evidence)

---

## Quick Answer to Common Questions

**Q: How many times was Nicholas married?**  
A: At least 2 (Elizabeth, Sarah), possibly 3 or 4 if two Elizabeths theory is correct.

**Q: When did Elizabeth Moore die?**  
A: Between June 1780 (Battle of Ramsour's Mill) and 1782 (St. Johns Town, FL arrival). NOT 1799 as FindAGrave states.

**Q: Which Sarah administered the estate in 1814?**  
A: Unknown - could be wife Sarah Farren OR daughter Sarah Welch (b. 1766). Probate says "widow" but ambiguous.

**Q: Can DNA solve this?**  
A: YES. Autosomal DNA will show if children cluster into different maternal groups. Y-DNA confirms paternal line. mtDNA confirms maternal line.

**Q: How accurate is DNA for genealogy?**  
A: Very accurate for 4-5 generations. Can definitively prove/disprove sibling relationships and identify maternal vs. paternal ancestry.

**Q: What if living descendants don't want to test?**  
A: Focus on genealogy hobbyists already active on WikiTree/Ancestry/23andMe. Many are eager to contribute to family history research.

---

## Contact

**Research Lead:** David England  
**WikiTree:** [[England-1357|David England]]  
**GitHub Repo:** https://github.com/DavidEngland/genealogy  
**ORCID:** https://orcid.org/0009-0001-2095-6646

---

*Updated as new DNA results become available*
