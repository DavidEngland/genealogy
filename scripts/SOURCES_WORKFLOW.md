# Genealogy Sources Workflow

Streamlined process for converting FamilySearch CSV search results to WikiTree-formatted markdown source citations.

## Overview

**Input:** CSV export from FamilySearch search results (stored in `search-results/` folder)
**Output:** Markdown source citations in `sources/` folder (e.g., `JamesHartgrave-sources.md`)

## Quick Start

### Single File Conversion
```bash
cd genealogy/

# Convert a CSV to markdown (output to stdout)
php scripts/csv_to_wikitree_sources.php search-results/JamesHartgrave.csv

# Write to specific output file
php scripts/csv_to_wikitree_sources.php \
  --in search-results/JamesHartgrave.csv \
  --out sources/JamesHartgrave-sources.md
```

### Batch Process All Files
```bash
# Process all search-results/*.csv files (skips if output already exists)
php scripts/batch-sources.php

# Preview without writing files
php scripts/batch-sources.php --dry-run

# Show detailed progress
php scripts/batch-sources.php --verbose
```

## Filtering Options

Most CSVs contain multiple entries, but typically only the first entries are relevant to the target person. Use filters to skip irrelevant records:

### Single File Filtering

```bash
# Only include records where roleInRecord=Principal
php scripts/csv_to_wikitree_sources.php search-results/JamesHartgrave.csv --only-principal

# Skip records matching keywords
php scripts/csv_to_wikitree_sources.php search-results/JamesHartgrave.csv \
  --skip-keywords "Find a Grave,Index,Newspaper"

# Only include records for specific person (case-insensitive)
php scripts/csv_to_wikitree_sources.php search-results/JamesHartgrave.csv \
  --filter-name "James Hartgrave"

# Skip records with family relationships (where relationshipToHead is set)
php scripts/csv_to_wikitree_sources.php search-results/JamesHartgrave.csv \
  --exclude-families

# Combine filters
php scripts/csv_to_wikitree_sources.php search-results/JamesHartgrave.csv \
  --only-principal \
  --skip-keywords "Find a Grave"

## Ranking Sources (New)

Use the ranking script to score sources by role, keywords, and name similarity. It outputs JSON and/or Markdown for quick review.

```bash
# Rank sources with target name and write JSON
php scripts/rank-sources.php \
  --in search-results/JamesHartgrave.csv \
  --out sources/JamesHartgrave-rank.json \
  --target-name "James Hartgrave"

# Create a markdown-ranked list (top 20)
php scripts/rank-sources.php \
  --in search-results/JamesHartgrave.csv \
  --out-md sources/JamesHartgrave-rank.md \
  --target-name "James Hartgrave" \
  --top 20
```
```

### Batch Filtering

Apply filters to all batch operations:

```bash
# Apply filters to all files in batch
php scripts/batch-sources.php --only-principal --skip-keywords "Find a Grave,Index"

# Process without principal-only filter
php scripts/batch-sources.php --include-all

# Exclude family member records
php scripts/batch-sources.php --exclude-families
```

## Configuration File

Create a `sources-config.json` file for per-file filter rules (overrides batch defaults):

```json
{
  "JamesHartgrave.csv": {
    "only_principal": true,
    "skip_keywords": ["Find a Grave", "Index"],
    "exclude_families": false
  },
  
  "WilliamHarrisGresham.csv": {
    "only_principal": true,
    "skip_keywords": [],
    "exclude_families": false
  },
  
  "moses-duncan.csv": {
    "only_principal": false,
    "skip_keywords": ["Newspaper"],
    "exclude_families": true
  }
}
```

Then run batch with config:

```bash
php scripts/batch-sources.php --config scripts/sources-config.json
```

## CSV Data Structure

FamilySearch exports include 34 columns. Key fields for filtering:

- **fullName** - Person's name (used for filtering)
- **roleInRecord** - Role in record (Principal, Groom, Bride, Witness, etc.)
- **relationshipToHead** - Family relationship (blank for principal, "Spouse", "Child", etc.)
- **collectionName** - Collection name (used for skip-keywords)
- **birthLikeDate, residenceDate, marriageLikeDate, deathLikeDate** - Event dates
- **birthLikePlaceText, residencePlaceText, etc.** - Event places
- **arkId** - FamilySearch hyperlink

## Output Format

Generated markdown files follow this structure:

```markdown
== Sources ==
* "[COLLECTION_NAME — SUBCOLLECTION_NAME]," database, [HYPERLINK FamilySearch], (accessed DATE), entry for ROLE for NAME, DATE, PLACE, Household: FAMILY_MEMBERS.
* "[Another Collection]," database, [HYPERLINK FamilySearch], (accessed DATE), entry for Principal for NAME, DATE, PLACE.
```

Each bullet is one source record, formatted for WikiTree.

## Workflow Examples

### Example 1: Process One Person

```bash
# 1. Export search results from FamilySearch → search-results/JamesHartgrave.csv
# 2. Verify the file exists
ls search-results/JamesHartgrave.csv

# 3. Convert with filters
php scripts/csv_to_wikitree_sources.php \
  --in search-results/JamesHartgrave.csv \
  --out sources/JamesHartgrave-sources.md \
  --only-principal \
  --skip-keywords "Find a Grave"

# 4. Review sources/JamesHartgrave-sources.md
# 5. Copy relevant citations into biography markdown or delete file if no matches
```

### Example 2: Batch Process Multiple People

```bash
# 1. Export multiple search results into search-results/ folder
# 2. Create sources-config.json with filter rules per person
# 3. Run batch with dry-run first
php scripts/batch-sources.php --config scripts/sources-config.json --dry-run

# 4. Review the preview output
# 5. Run without dry-run to generate files
php scripts/batch-sources.php --config scripts/sources-config.json

# 6. Review all generated markdown files in sources/
# 7. Move relevant ones to biography folders, delete irrelevant ones
```

### Example 3: Review and Cleanup

```bash
# Generate all sources with strict filters
php scripts/batch-sources.php --only-principal --verbose

# See what was generated
ls -lah sources/*-sources.md | tail -20

# Review one
cat sources/JamesHartgrave-sources.md

# Move to biography folder if relevant
mv sources/JamesHartgrave-sources.md biography/JamesHartgrave/

# Delete if not relevant
rm sources/UnrelatedPerson-sources.md
```

## Notes

- **Naming Convention:** Output files follow pattern `{CSVBASENAME}-sources.md` (e.g., `JamesHartgrave.csv` → `JamesHartgrave-sources.md`)
- **Duplicate Prevention:** Batch processing skips output files that already exist
- **Dry Run:** Use `--dry-run` to preview batch operations before executing
- **Config Format:** Configuration uses JSON with file basenames (with `.csv`) as keys
- **Filter Priority:** Config file rules override command-line defaults
- **Date Access:** All citations include current date as access date

## Troubleshooting

**CSV not found:**
```
Error: Input file not found: search-results/myfile.csv
```
Check the file path. The script looks in current working directory, repo root, and scripts directory.

**No CSVs in batch:**
```
No CSV files found matching pattern: search-results/*.csv
```
Verify search-results folder exists and contains .csv files. Check folder paths.

**Invalid JSON config:**
```
Warning: Invalid JSON in config file: sources-config.json
```
Validate JSON syntax using `jq` or online validator.

**Output file already exists:**
```
⊘ JamesHartgrave.csv → JamesHartgrave-sources.md (already exists)
```
Delete or rename the output file before running batch again, or use config to override the file.

Sources workflow stub.
