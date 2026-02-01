#!/bin/bash
# ============================================
# Git Privacy Validation Script
# Checks for common privacy issues
# Created: 30 January 2026
# ============================================

echo "============================================"
echo "Git Privacy Validation Check"
echo "============================================"
echo ""

ISSUES_FOUND=0

# Check if .gitignore exists
if [ ! -f ".gitignore" ]; then
    echo "❌ ERROR: .gitignore file not found!"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo "✅ .gitignore exists"
fi

# Check if search-results/.gitignore exists
if [ ! -f "search-results/.gitignore" ]; then
    echo "❌ WARNING: search-results/.gitignore not found"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo "✅ search-results/.gitignore exists"
fi

# Check for tracked GEDCOM files
echo ""
echo "Checking for tracked GEDCOM files..."
GEDCOM_COUNT=$(git ls-files | grep -c '\.ged$')
if [ "$GEDCOM_COUNT" -gt 0 ]; then
    echo "❌ WARNING: $GEDCOM_COUNT GEDCOM files are still tracked!"
    echo "   Run: ./scripts/cleanup-git-tracking.sh"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    git ls-files | grep '\.ged$' | head -5
else
    echo "✅ No GEDCOM files are tracked"
fi

# Check for tracked PDF files
echo ""
echo "Checking for tracked PDF files..."
PDF_COUNT=$(git ls-files | grep -c '\.pdf$')
if [ "$PDF_COUNT" -gt 0 ]; then
    echo "❌ WARNING: $PDF_COUNT PDF files are still tracked!"
    echo "   Run: ./scripts/cleanup-git-tracking.sh"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
    git ls-files | grep '\.pdf$'
else
    echo "✅ No PDF files are tracked"
fi

# Check for tracked search results
echo ""
echo "Checking for tracked search results..."
SEARCH_COUNT=$(git ls-files | grep -c 'search-results.*\.csv')
if [ "$SEARCH_COUNT" -gt 0 ]; then
    echo "❌ WARNING: $SEARCH_COUNT search result files are still tracked!"
    echo "   Run: ./scripts/cleanup-git-tracking.sh"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo "✅ No search result CSV files are tracked"
fi

# Check for .DS_Store files
echo ""
echo "Checking for .DS_Store files..."
DS_COUNT=$(git ls-files | grep -c '\.DS_Store')
if [ "$DS_COUNT" -gt 0 ]; then
    echo "❌ WARNING: $DS_COUNT .DS_Store files are still tracked!"
    echo "   Run: ./scripts/cleanup-git-tracking.sh"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo "✅ No .DS_Store files are tracked"
fi

# Check for TODO/working files
echo ""
echo "Checking for TODO/working files..."
TODO_COUNT=$(git ls-files | grep -cE '(TODO\.md|WIKITREE_ID_EXTRACTION|IMPLEMENTATION_SUMMARY)')
if [ "$TODO_COUNT" -gt 0 ]; then
    echo "⚠️  NOTICE: $TODO_COUNT working files are still tracked"
    echo "   These will be ignored in future commits"
    git ls-files | grep -E '(TODO\.md|WIKITREE_ID_EXTRACTION|IMPLEMENTATION_SUMMARY)'
else
    echo "✅ No TODO/working files are tracked"
fi

# Check for Books folder
echo ""
echo "Checking for Books folder..."
BOOKS_COUNT=$(git ls-files | grep -c '^Books/')
if [ "$BOOKS_COUNT" -gt 0 ]; then
    echo "❌ WARNING: $BOOKS_COUNT files in Books/ are still tracked!"
    echo "   Run: ./scripts/cleanup-git-tracking.sh"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo "✅ Books folder not tracked"
fi

# Check for workspace files
echo ""
echo "Checking for VS Code workspace files..."
WORKSPACE_COUNT=$(git ls-files | grep -c '\.code-workspace$')
if [ "$WORKSPACE_COUNT" -gt 0 ]; then
    echo "⚠️  NOTICE: $WORKSPACE_COUNT workspace files are still tracked"
    echo "   Run: ./scripts/cleanup-git-tracking.sh"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
else
    echo "✅ No workspace files are tracked"
fi

# Check .gitignore patterns
echo ""
echo "Checking .gitignore patterns..."
if grep -q "\.ged" .gitignore; then
    echo "✅ GEDCOM files pattern found in .gitignore"
else
    echo "❌ ERROR: GEDCOM pattern missing from .gitignore!"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

if grep -q "bak/" .gitignore; then
    echo "✅ Backup folder pattern found in .gitignore"
else
    echo "❌ ERROR: Backup folder pattern missing from .gitignore!"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

if grep -q "search-results/\*\.csv" .gitignore; then
    echo "✅ Search results pattern found in .gitignore"
else
    echo "❌ ERROR: Search results pattern missing from .gitignore!"
    ISSUES_FOUND=$((ISSUES_FOUND + 1))
fi

# Summary
echo ""
echo "============================================"
if [ "$ISSUES_FOUND" -eq 0 ]; then
    echo "✅ Privacy Configuration: EXCELLENT"
    echo "   All checks passed!"
else
    echo "⚠️  Privacy Configuration: NEEDS ATTENTION"
    echo "   Issues found: $ISSUES_FOUND"
    echo ""
    echo "Recommended action:"
    echo "   ./scripts/cleanup-git-tracking.sh"
fi
echo "============================================"
echo ""

# Test specific files
echo "Testing specific file patterns:"
echo ""

TEST_FILES=(
    "bak/test.md"
    "test.ged"
    "search-results/test.csv"
    "TODO.md"
    ".DS_Store"
    "test.pdf"
)

for file in "${TEST_FILES[@]}"; do
    if git check-ignore -q "$file"; then
        echo "✅ $file - IGNORED"
    else
        echo "❌ $file - NOT IGNORED"
    fi
done

echo ""
echo "Validation complete!"
