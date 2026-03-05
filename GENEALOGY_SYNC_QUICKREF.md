# Genealogy Sync - Quick Reference Card

## Common Commands

### Parse FamilySearch File
```bash
php scripts/familysearch_parser.php \
  --input "FILENAME.md" \
  --output data/OUTPUT.json \
  --verbose
```

### Sync with WikiTree
```bash
php scripts/genealogy_sync.php \
  --wikitree WIKITREE-ID \
  --familysearch data/FAMILYSEARCH.json \
  --output data/sync-report.json \
  --verbose
```

### View Report (Text)
```bash
php scripts/view_sync_report.php data/sync-report.json
```

### View Report (Markdown)
```bash
php scripts/view_sync_report.php data/sync-report.json --format markdown > reports/report.md
```

### Complete Workflow (One Command)
```bash
php scripts/familysearch_parser.php --input "Family.md" --output data/family-fs.json && \
php scripts/genealogy_sync.php --wikitree ID --familysearch data/family-fs.json --output data/sync.json && \
php scripts/view_sync_report.php data/sync.json
```

## File Locations

- **Scripts:** `scripts/familysearch_parser.php`, `genealogy_sync.php`, `view_sync_report.php`
- **Data:** `data/*.json` (parsed data, sync reports)
- **Reports:** `reports/*.txt`, `reports/*.md` (readable reports)
- **Docs:** `scripts/GENEALOGY_SYNC_README.md`, `FAMILYSEARCH_WIKITREE_SYNC_SUMMARY.md`

## Quick Tips

- All scripts support `--help` flag
- Use `--verbose` for debugging
- JSON files can be edited manually
- Reports are regenerated from JSON data

## Example: Nicholas Welch Family

```bash
# Already completed - view results:
cat reports/welch-185-sync-summary.txt

# Or regenerate:
php scripts/genealogy_sync.php \
  --wikitree Welch-185 \
  --familysearch data/nicholas-welch-familysearch.json \
  --output data/welch-185-sync-report.json
```

## Help Commands

```bash
php scripts/familysearch_parser.php --help
php scripts/genealogy_sync.php --help
php scripts/view_sync_report.php --help
```

## Output Files

| File | Description |
|------|-------------|
| `data/*-familysearch.json` | Parsed FamilySearch data |
| `data/*-sync-report.json` | Complete sync report (JSON) |
| `reports/*-summary.txt` | Plain text report |
| `reports/*-report.md` | Markdown report |

---

**Full Documentation:** `scripts/GENEALOGY_SYNC_README.md`  
**Implementation Summary:** `FAMILYSEARCH_WIKITREE_SYNC_SUMMARY.md`
