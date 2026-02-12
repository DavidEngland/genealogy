#!/bin/bash
# Author: David Edward England, PhD
# ORCID: https://orcid.org/0009-0001-2095-6646
# Repo: https://github.com/DavidEngland/genealogy
# ============================================
# Git Cleanup Script for Genealogy Repository
# Removes sensitive files from git tracking
# Created: 30 January 2026
# ============================================

echo "============================================"
echo "Git Repository Cleanup - Sensitive Files"
echo "============================================"
echo ""
echo "This script will remove sensitive files from git tracking"
echo "while keeping them in your local working directory."
echo ""
read -p "Do you want to continue? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    echo "Cleanup cancelled."
    exit 1
fi

echo ""
echo "Starting cleanup..."
echo ""

# ============================================
# Remove GEDCOM files from tracking
# ============================================
echo "1. Removing GEDCOM files from git tracking..."
git rm --cached -r GEDs/*.ged 2>/dev/null || true
git rm --cached *.ged 2>/dev/null || true

# ============================================
# Remove PDF files from tracking
# ============================================
echo "2. Removing PDF files from git tracking..."
git rm --cached *.pdf 2>/dev/null || true
git rm --cached "Ancestry of Jabez Perkins.pdf" 2>/dev/null || true
git rm --cached "Solomon Whitley Senior.pdf" 2>/dev/null || true

# ============================================
# Remove Books folder from tracking
# ============================================
echo "3. Removing Books folder from git tracking..."
git rm --cached -r Books/ 2>/dev/null || true

# ============================================
# Remove .DS_Store files (macOS)
# ============================================
echo "4. Removing .DS_Store files from git tracking..."
find . -name .DS_Store -print0 | xargs -0 git rm --cached 2>/dev/null || true

# ============================================
# Remove TODO and working files
# ============================================
echo "5. Removing working files from git tracking..."
git rm --cached TODO.md 2>/dev/null || true
git rm --cached WIKITREE_ID_EXTRACTION.txt 2>/dev/null || true
git rm --cached START_HERE.txt 2>/dev/null || true
git rm --cached COMPLETION_SUMMARY.txt 2>/dev/null || true
git rm --cached IMPLEMENTATION_COMPLETE.md 2>/dev/null || true
git rm --cached IMPLEMENTATION_SUMMARY.md 2>/dev/null || true
git rm --cached IMPLEMENTATION_SUMMARY_ENHANCED.md 2>/dev/null || true

# ============================================
# Remove search results CSV files
# ============================================
echo "6. Removing search results from git tracking..."
git rm --cached search-results/*.csv 2>/dev/null || true
git rm --cached *-search-results.csv 2>/dev/null || true
git rm --cached "GWBrewer-search-results.csv" 2>/dev/null || true

# ============================================
# Remove large CSV files
# ============================================
echo "7. Removing large CSV files from git tracking..."
git rm --cached "England-1055.csv" 2>/dev/null || true
git rm --cached "Genealogical Records and Historical Personalities of Early America - Table 1.csv" 2>/dev/null || true

# ============================================
# Remove database files
# ============================================
echo "8. Removing database files from git tracking..."
git rm --cached ancestors/reference-database.json 2>/dev/null || true
git rm --cached ancestors/reference-database.csv 2>/dev/null || true

# ============================================
# Remove personal correspondence
# ============================================
echo "9. Removing personal correspondence from git tracking..."
git rm --cached *.eml 2>/dev/null || true
git rm --cached *.eml.txt 2>/dev/null || true
git rm --cached LewisandHughMcDonald.eml.txt 2>/dev/null || true

# ============================================
# Remove workspace files
# ============================================
echo "10. Removing VS Code workspace files from git tracking..."
git rm --cached *.code-workspace 2>/dev/null || true
git rm --cached genealogy.code-workspace 2>/dev/null || true

echo ""
echo "============================================"
echo "Cleanup Complete!"
echo "============================================"
echo ""
echo "Next steps:"
echo "1. Review the changes with: git status"
echo "2. Commit the changes with: git commit -m 'Remove sensitive files from tracking'"
echo "3. Files remain in your working directory but won't be pushed to GitHub"
echo ""
echo "IMPORTANT: If you've already pushed these files to GitHub:"
echo "  - They will still exist in git history"
echo "  - Consider using 'git filter-branch' or 'BFG Repo-Cleaner' to remove them"
echo "  - Or create a fresh repository if history isn't critical"
echo ""
