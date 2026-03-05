# Genealogy Data Sync System

**Author:** David Edward England, PhD  
**ORCID:** https://orcid.org/0009-0001-2095-6646  
**Date:** March 4, 2026  
**Version:** 1.0

## Overview

A comprehensive PHP-based toolkit for synchronizing genealogy data between FamilySearch, WikiTree, and FindAGrave. Follows KISS principles (Keep It Simple Smartie) with JSON-based data exchange and API-first design.

## Features

- ✅ Parse FamilySearch family group sheets (handles OCR errors)
- ✅ Fetch WikiTree data via official API
- ✅ Create cross-references between systems
- ✅ Identify discrepancies (missing people, date mismatches)
- ✅ Generate actionable recommendations
- ✅ JSON-based data format for easy integration
- ✅ Fuzzy matching for name variations
- 🔄 FindAGrave integration (planned)

## Quick Start

### 1. Parse FamilySearch Data

```bash
php scripts/familysearch_parser.php \
  --input "Major Nicholas Welch 1740-1814 • L6ZC-3Q.md" \
  --output data/nicholas-welch-familysearch.json \
  --verbose
```

**Output:**
- `data/nicholas-welch-familysearch.json` - Structured family data

### 2. Sync with WikiTree

```bash
php scripts/genealogy_sync.php \
  --wikitree Welch-185 \
  --familysearch data/nicholas-welch-familysearch.json \
  --output data/welch-185-sync-report.json \
  --verbose
```

**Output:**
- `data/welch-185-sync-report.json` - Complete sync report with discrepancies and recommendations

### 3. View Report

```bash
# Plain text format
php scripts/view_sync_report.php data/welch-185-sync-report.json

# Markdown format (for documentation)
php scripts/view_sync_report.php data/welch-185-sync-report.json --format markdown > reports/welch-185-report.md
```

## Components

### 1. FamilySearch Parser (`familysearch_parser.php`)

Parses FamilySearch family group sheets exported as text/Markdown.

**Features:**
- Handles multi-line records (name on one line, dates on next)
- Fixes common OCR errors (Kichard → Richard, weld → Welch)
- Extracts FamilySearch IDs (e.g., L6ZC-3QY)
- Identifies principal, spouse, and children
- Outputs structured JSON

**Usage:**
```bash
php scripts/familysearch_parser.php --input FILE [--output FILE] [--verbose]
```

**Input Format:**
```
Major Nicholas Welch 1740-1814 • L6ZC-3QY
Elizabeth E Moore
1740-1799 • L75M-7D6
Osias Welch
1760-1833 • L84F-S9W
...
```

**Output Format (JSON):**
```json
{
  "metadata": {
    "source": "FamilySearch",
    "parsed": "2026-03-04T19:52:52+00:00"
  },
  "principal": {
    "name": "Major Nicholas Welch",
    "familysearch_id": "L6ZC-3QY",
    "birth_year": 1740,
    "death_year": 1814
  },
  "spouse": {...},
  "children": [...]
}
```

### 2. Genealogy Sync Tool (`genealogy_sync.php`)

Main sync engine that compares WikiTree and FamilySearch data.

**Features:**
- Fetches WikiTree data via API (using `wikitree_api_client.php`)
- Fuzzy matching for person records (name + date similarity)
- Identifies missing people in either system
- Detects date discrepancies
- Generates actionable recommendations

**Usage:**
```bash
php scripts/genealogy_sync.php --wikitree ID [--familysearch FILE] [OPTIONS]
```

**Options:**
- `--wikitree ID` - WikiTree profile ID (required)
- `--familysearch FILE` - FamilySearch data (.md or .json)
- `--output FILE` - Output report file (default: auto-generated)
- `--verbose` - Show detailed processing

**Matching Algorithm:**
- Name similarity: 50% weight (Levenshtein distance)
- Birth year: 30% weight (±10 year tolerance)
- Death year: 20% weight (±10 year tolerance)
- Confidence levels: high (>85%), medium (70-85%), low (60-70%)

### 3. Report Viewer (`view_sync_report.php`)

Displays sync reports in human-readable format.

**Usage:**
```bash
php scripts/view_sync_report.php REPORT_FILE [--format text|markdown]
```

**Formats:**
- `text` - Plain text with ASCII formatting (default)
- `markdown` - Markdown with tables

### 4. WikiTree API Client (`wikitree_api_client.php`)

Reusable API client for WikiTree integration (already existing).

## Data Flow

```
FamilySearch                  WikiTree
    ↓                            ↓
    ↓ (parser)              (API fetch)
    ↓                            ↓
    ↓                            ↓
    └────→ JSON ←────────────────┘
              ↓
        (genealogy_sync)
              ↓
         Sync Report
              ↓
        (view_report)
              ↓
      Recommendations
```

## JSON Schema

### Sync Report Structure

```json
{
  "metadata": {
    "principal_wikitree_id": "Welch-185",
    "sync_date": "2026-03-04T19:53:48+00:00",
    "version": "1.0"
  },
  "wikitree": {
    "principal": {...},
    "spouse": {...},
    "children": [...]
  },
  "familysearch": {
    "principal": {...},
    "spouse": {...},
    "children": [...]
  },
  "cross_references": [
    {
      "type": "child",
      "wikitree_id": "Welch-1890",
      "familysearch_id": "LRC4-1FR",
      "wikitree_name": "Lewis Welch",
      "familysearch_name": "Lewis Welch",
      "confidence": "high",
      "match_score": 0.95
    }
  ],
  "discrepancies": [
    {
      "type": "missing_in_wikitree",
      "person": {...},
      "severity": "medium",
      "recommendation": "Add this person to WikiTree"
    }
  ],
  "recommendations": [
    {
      "priority": "high",
      "action": "add_to_wikitree",
      "count": 22,
      "description": "Add 22 missing children to WikiTree",
      "items": [...]
    }
  ]
}
```

## Common Workflows

### Complete Family Sync

```bash
# 1. Parse FamilySearch data
php scripts/familysearch_parser.php \
  --input "Nicholas-Welch-Family.md" \
  --output data/nicholas-welch-fs.json

# 2. Sync with WikiTree
php scripts/genealogy_sync.php \
  --wikitree Welch-185 \
  --familysearch data/nicholas-welch-fs.json \
  --output data/sync-report.json

# 3. View recommendations
php scripts/view_sync_report.php data/sync-report.json

# 4. Export markdown report
php scripts/view_sync_report.php data/sync-report.json --format markdown > reports/welch-family-sync.md
```

### WikiTree-Only Fetch

```bash
# Fetch WikiTree family data
php scripts/genealogy_sync.php --wikitree Welch-185 --output data/welch-185-wt.json
```

### Update Existing Report

```bash
# Re-sync with fresh WikiTree data
php scripts/genealogy_sync.php \
  --wikitree Welch-185 \
  --familysearch data/nicholas-welch-fs.json \
  --output data/sync-report-updated.json
```

## Discrepancy Types

| Type | Description | Severity | Action |
|------|-------------|----------|--------|
| `missing_in_wikitree` | Person in FamilySearch but not WikiTree | Medium | Add to WikiTree |
| `missing_in_familysearch` | Person in WikiTree but not FamilySearch | Low | Verify FamilySearch |
| `birth_year_mismatch` | Birth years differ by >2 years | Medium | Review sources |
| `death_year_mismatch` | Death years differ by >2 years | Medium | Review sources |
| `name_mismatch` | Same person, different names | Low | Note variant |

## OCR Error Handling

The FamilySearch parser automatically fixes common OCR errors:

| OCR Error | Correction | Context |
|-----------|------------|---------|
| `Kichard` | `Richard` | Character misread |
| `Hesta` | `Hester` | Missing letter |
| `saran` | `Sarah` | Lowercase misread |
| `weld` | `Welch` | Surname variant |
| `welsh` | `Welch` | Surname variant |
| `GDF\|-70W` | `GDF1-70W` | Pipe to 1 |
| `L6ZC-3Q.md` | `L6ZC-3QY` | Filename typo |

## API Usage

### WikiTree API

Uses the official WikiTree API v1:
- Endpoint: `https://api.wikitree.com/api.php`
- Methods: `getProfile`, `getRelatives`
- App ID: `genealogy_parser_v1`
- Rate limits: Respectful delays built-in

**Fields fetched:**
- Basic: Name, Birth/Death dates/locations, Gender
- Relationships: Parents, Children, Siblings, Spouses
- Metadata: Privacy, Manager, Created/Touched dates

### FamilySearch API (Planned)

Future integration:
- FamilySearch Family Tree API
- Authentication via OAuth
- Person record updates
- Source attachment

### FindAGrave API (Planned)

Future integration:
- Memorial search by name/dates
- Cross-reference with WikiTree/FamilySearch
- Photo/GPS data extraction

## File Structure

```
scripts/
├── familysearch_parser.php      # Parse FamilySearch exports
├── genealogy_sync.php            # Main sync engine
├── view_sync_report.php          # Report viewer
├── wikitree_api_client.php       # WikiTree API wrapper (existing)
└── wikitree_fetch.php            # WikiTree data fetcher (existing)

data/
├── nicholas-welch-familysearch.json  # Parsed FamilySearch data
├── welch-185-sync-report.json        # Sync report
└── [other family JSON files]

reports/
└── [generated markdown reports]
```

## Configuration

No configuration files needed! All scripts use command-line arguments.

Optional environment variables:
- `WIKITREE_API_KEY` - For authenticated requests (future)
- `FAMILYSEARCH_API_KEY` - For API access (future)

## Testing

### Test with Nicholas Welch Family

```bash
# Test parser
php scripts/familysearch_parser.php \
  --input "Major Nicholas Welch 1740-1814 • L6ZC-3Q.md" \
  --output data/test-parse.json \
  --verbose

# Test sync
php scripts/genealogy_sync.php \
  --wikitree Welch-185 \
  --familysearch data/test-parse.json \
  --output data/test-sync.json \
  --verbose

# View results
php scripts/view_sync_report.php data/test-sync.json
```

### Expected Results

- Principal: Nicholas Welch (Welch-185 ↔ L6ZC-3QY)
- Spouse: Elizabeth Moore (Moore-51757 ↔ L75M-7D6)
- Children: ~18 matched, ~22 in FamilySearch total
- Discrepancies: Missing children in WikiTree, some date variations

## Troubleshooting

### Issue: Parser doesn't find FamilySearch IDs

**Problem:** Format doesn't match expected pattern  
**Solution:** Check for bullet character (•) and ID format (XXXX-XXX)

### Issue: Low match confidence scores

**Problem:** Name variations or date differences  
**Solution:** Check for nicknames, maiden names, OCR errors

### Issue: WikiTree API timeout

**Problem:** Network issues or rate limiting  
**Solution:** Retry with `--verbose` to see API response

### Issue: Missing children not detected

**Problem:** Children linked to different parent profiles  
**Solution:** Verify WikiTree family relationships

## Roadmap

### Version 1.1 (Planned)
- [ ] FindAGrave API integration
- [ ] Automatic ID addition to WikiTree bio
- [ ] Batch processing for multiple families
- [ ] CSV export for spreadsheet analysis

### Version 1.2 (Planned)
- [ ] FamilySearch API authentication
- [ ] Direct person record updates
- [ ] Source citation sync
- [ ] Photo/document sync

### Version 2.0 (Future)
- [ ] Web interface
- [ ] Database storage (SQLite)
- [ ] Multi-user collaboration
- [ ] Automated conflict resolution

## Contributing

Contributions welcome! Please follow:
- KISS principles
- PHP 8.1+ compatibility
- JSON for data exchange
- Comprehensive error handling
- Verbose logging option

## License

Part of the genealogy repository by David England.  
See main repository for license information.

## Support

For issues or questions:
- Check existing scripts in `scripts/` directory
- Review error logs in `logs/` directory
- Test with `--verbose` flag for detailed output

## References

- WikiTree API Documentation: https://github.com/wikitree/wikitree-api
- FamilySearch Developer Portal: https://www.familysearch.org/developers/
- GEDCOM Standard: https://gedcom.io/
- Schema.org Person: https://schema.org/Person

---

**Last Updated:** March 4, 2026  
**Tested With:** PHP 8.1, WikiTree API v1, FamilySearch exports v2026
