# Git Privacy Configuration - Summary

**Updated:** 30 January 2026

## What Was Implemented

### 1. Enhanced .gitignore File ✅

Created comprehensive `.gitignore` with the following protections:

#### Backup Files
- `bak/` folder and all `.bak`, `.backup` files
- Old/archive folders (`*.old/`, `Hartgrove-97-bios.old/`)
- Temporary files (`*.tmp`, swap files)

#### Privacy-Sensitive Data
- **GEDCOM files**: All `.ged` files excluded (may contain living persons)
- **Search results**: All CSV/JSON files in `search-results/`
- **Personal correspondence**: `.eml`, `.eml.txt` files
- **Working documents**: TODO.md, implementation summaries, extraction lists

#### Large Binary Files
- **PDFs**: All `.pdf` files (can enable exceptions for specific docs)
- **Books folder**: Entire `Books/` directory
- **Database files**: `.db`, `.sqlite` files

#### Data Files
- **Large CSVs**: Search results, reference databases
- **JSON databases**: `ancestors/reference-database.json`
- **Private data folders**: `data/private/`, `data/raw/`, `data/exports/`

#### System Files
- **macOS**: `.DS_Store`, `._*` files
- **VS Code**: `.vscode/`, `*.code-workspace` files
- **Python**: `__pycache__/`, virtual environments, `.env`
- **PHP**: `vendor/`, `composer.lock`, cache files

#### Explicitly Tracked (Exceptions)
- ✅ Markdown documentation (`*.md`)
- ✅ Source citations (`sources/*.md`)
- ✅ Ancestor profiles (`ancestors/*.md`) - historical figures only
- ✅ Schemas (`schema/*.json`, `schema/*.sql`)
- ✅ Scripts (`scripts/*.py`, `scripts/*.php`, `scripts/*.sh`)

### 2. Search Results Protection ✅

Created `search-results/.gitignore` to protect:
- All CSV files with FamilySearch queries
- JSON exports
- Excel files
- Personal information about living individuals

### 3. Cleanup Script ✅

Created `scripts/cleanup-git-tracking.sh` to remove already-tracked sensitive files:
- GEDCOM files
- PDF documents
- Books folder
- .DS_Store files
- Working/TODO files
- Search results
- Large CSV files
- Database files
- Personal correspondence
- Workspace files

## Already Tracked Files That Need Removal

The following sensitive files are currently tracked by git:

```
.DS_Store
Ancestry of Jabez Perkins.pdf
Solomon Whitley Senior.pdf
Books/ (entire directory)
GEDs/*.ged (53 GEDCOM files)
TODO.md
WIKITREE_ID_EXTRACTION.txt
IMPLEMENTATION_SUMMARY.txt
search-results/*.csv (27+ files)
England-1055.csv
GWBrewer-search-results.csv
LewisandHughMcDonald.eml.txt
genealogy.code-workspace
```

## How to Use the Cleanup Script

```bash
# Run from repository root
cd /Users/davidengland/Documents/GitHub/genealogy

# Execute the cleanup script
./scripts/cleanup-git-tracking.sh

# Review what will be removed
git status

# Commit the changes
git commit -m "Remove sensitive files from git tracking per privacy policy"

# Files remain in your working directory but won't be pushed to GitHub
```

## Privacy Guidelines

### ✅ Safe to Share (Public GitHub)
- Biographical information about historical figures (deceased 100+ years)
- Census records from 1940 and earlier
- Documented family histories with no living persons
- Source citations (without personal contact info)
- Scripts and tools (no embedded credentials)

### ❌ Never Share (Keep Private)
- GEDCOM files with living persons
- Recent death records (less than 50 years old)
- FamilySearch queries with personal data
- Living relatives' information
- Social Security numbers
- GPS coordinates of family graves
- Personal correspondence
- Working notes with private research

### ⚠️ Review Before Sharing
- CSV exports (check for living persons)
- Search results (may contain PII)
- PDF scans (check for sensitive annotations)
- JSON databases (verify no living persons)

## GEDCOM Privacy Settings

When downloading from WikiTree:
1. Use "Ancestors only" export
2. Set privacy level to "Public" (100+ years deceased)
3. Exclude living persons
4. Exclude parents of living persons
5. Remove notes with personal information

## If Files Already Pushed to GitHub

If sensitive files are already in GitHub history:

### Option 1: BFG Repo-Cleaner (Recommended)
```bash
# Install BFG
brew install bfg

# Clone a fresh copy
git clone --mirror https://github.com/yourusername/genealogy.git

# Remove files
bfg --delete-files '*.ged' genealogy.git
bfg --delete-files '*.pdf' genealogy.git
bfg --delete-folders Books genealogy.git

# Clean up
cd genealogy.git
git reflog expire --expire=now --all
git gc --prune=now --aggressive

# Force push
git push --force
```

### Option 2: Fresh Repository
1. Create new repository
2. Copy only public files
3. Initialize new git history
4. Push to new repository

## Ongoing Maintenance

### Before Each Commit
```bash
# Check what you're about to commit
git status

# Review specific files
git diff filename.md

# Verify .gitignore is working
git check-ignore -v filename.ged
```

### Periodic Audits
```bash
# List all tracked files
git ls-files > tracked-files.txt

# Review for sensitive data
grep -E '\.ged|\.pdf|living|search-results' tracked-files.txt
```

## Questions to Ask Before Committing

1. ✅ Does this file contain only deceased persons (100+ years)?
2. ✅ Are all dates and places from historical records?
3. ✅ Are there no living relatives mentioned?
4. ✅ Are there no private research notes?
5. ✅ Are credentials/API keys removed?

If you answer "No" to any question, don't commit the file.

## Additional Protections

Consider creating separate repositories:

```
genealogy-public/     # Historical documentation (GitHub)
genealogy-private/    # Working files with living persons (local only)
genealogy-research/   # GEDCOM files and searches (private Git server or local)
```

## Support

If you accidentally commit sensitive data:
1. **Don't panic** - it can be removed
2. **Don't push** - if you haven't pushed yet, you can amend the commit
3. **Contact GitHub** - they can help purge sensitive data if needed
4. Use the cleanup script immediately

## Status

- ✅ `.gitignore` created and configured
- ✅ `search-results/.gitignore` created
- ✅ Cleanup script created and executable
- ⏳ Cleanup script needs to be run
- ⏳ Changes need to be committed

## Next Steps

1. **Run cleanup script**: `./scripts/cleanup-git-tracking.sh`
2. **Review changes**: `git status`
3. **Commit changes**: `git commit -m "Remove sensitive files from tracking"`
4. **Verify**: Check that no sensitive files appear in `git ls-files`
5. **Push safely**: Only public historical data will be uploaded

---

**Remember:** Once you run the cleanup script, files remain in your working directory but won't be tracked by git or pushed to GitHub. This protects privacy while allowing you to work with all your research data locally.
