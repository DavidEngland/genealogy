# Git Privacy - Quick Reference

## ✅ Implementation Complete

### What's Protected Now

```
✓ GEDCOM files (*.ged)
✓ Search results (search-results/*.csv)
✓ Backup folder (bak/)
✓ PDF documents (*.pdf)
✓ Books folder (Books/)
✓ Working files (TODO.md, IMPLEMENTATION_*.md)
✓ Database files (*.db, reference-database.json)
✓ System files (.DS_Store, .vscode/)
✓ Personal correspondence (*.eml.txt)
```

## 🚀 Next Step: Run Cleanup

Remove already-tracked sensitive files:

```bash
cd /Users/davidengland/Documents/GitHub/genealogy
./scripts/cleanup-git-tracking.sh
```

Then commit:
```bash
git add .gitignore search-results/.gitignore GIT_PRIVACY_SETUP.md scripts/cleanup-git-tracking.sh
git commit -m "Add privacy protections to git repository"
```

## ✅ Before Each Commit Checklist

```bash
# 1. Check what you're committing
git status

# 2. Test if sensitive files are ignored
git check-ignore -v filename.ged

# 3. Review changes
git diff --staged

# 4. Verify no private data
grep -r "living" staged-files.md
```

## 📋 Files That Are Safe

- ✅ Markdown biographies (deceased persons 100+ years)
- ✅ Historical source citations
- ✅ Scripts and tools
- ✅ Schemas
- ✅ Census records (1940 and earlier)

## ❌ Files That Are Private

- ❌ GEDCOM exports
- ❌ FamilySearch search results
- ❌ Personal notes
- ❌ Living relatives data
- ❌ Working files (TODO, IMPLEMENTATION)

## 🔍 Quick Audit Commands

```bash
# See all tracked files
git ls-files | less

# Find tracked GEDCOMs (should be empty after cleanup)
git ls-files | grep '\.ged$'

# Find tracked PDFs (should be empty after cleanup)
git ls-files | grep '\.pdf$'

# Check if file is ignored
git check-ignore -v filename.csv
```

## 📂 Current Status

| Item | Status |
|------|--------|
| .gitignore | ✅ Created & configured |
| search-results/.gitignore | ✅ Created |
| cleanup-git-tracking.sh | ✅ Created & executable |
| GIT_PRIVACY_SETUP.md | ✅ Complete guide created |
| Cleanup executed | ⏳ **Run script next** |

## 🆘 If You Accidentally Commit Private Data

```bash
# If you haven't pushed yet
git reset HEAD~1
git add .gitignore  # Only add safe files
git commit -m "Add privacy protections"

# If you already pushed
# See GIT_PRIVACY_SETUP.md for BFG Repo-Cleaner instructions
```

## 📖 Full Documentation

See [GIT_PRIVACY_SETUP.md](GIT_PRIVACY_SETUP.md) for complete details.
