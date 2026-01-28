#!/bin/bash
# Genealogy Data Batch Processor
# Converts multiple GEDCOM files to JSON and CSV

echo "🧬 Genealogy GEDCOM Batch Processor"
echo "===================================="
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="$SCRIPT_DIR/../data"
GED_DIR="$SCRIPT_DIR/../GEDs"

mkdir -p "$DATA_DIR"

echo "Processing GEDCOM files from: $GED_DIR"
echo "Output directory: $DATA_DIR"
echo ""

# Counter for processed files
count=0
success=0
failed=0

# Process all .ged files
for gedfile in "$GED_DIR"/*.ged; do
    if [ ! -f "$gedfile" ]; then
        continue
    fi
    
    filename=$(basename "$gedfile" .ged)
    json_file="$DATA_DIR/$filename.json"
    csv_lookup="$DATA_DIR/$filename-lookup.csv"
    csv_full="$DATA_DIR/$filename-full.csv"
    
    count=$((count + 1))
    echo "[$count] Processing: $filename"
    
    # Parse GEDCOM to JSON
    if php "$SCRIPT_DIR/gedcom_parser.php" "$gedfile" "$json_file" 2>/dev/null; then
        success=$((success + 1))
        
        # Count records
        people=$(grep -c '"wikitreeId"' "$json_file" 2>/dev/null || echo "0")
        families=$(grep -c '"familyId"' "$json_file" 2>/dev/null || echo "0")
        
        echo "    ✓ JSON: $people people, $families families"
        
        # Export lookup CSV
        if php "$SCRIPT_DIR/csv_exporter.php" "$json_file" "$csv_lookup" lookup 2>/dev/null; then
            echo "    ✓ CSV (lookup): $csv_lookup"
        else
            echo "    ✗ CSV (lookup) failed"
        fi
        
        # Export full CSV
        if php "$SCRIPT_DIR/csv_exporter.php" "$json_file" "$csv_full" full 2>/dev/null; then
            echo "    ✓ CSV (full): $csv_full"
        else
            echo "    ✗ CSV (full) failed"
        fi
    else
        failed=$((failed + 1))
        echo "    ✗ Failed to parse GEDCOM"
    fi
    
    echo ""
done

echo "===================================="
echo "Summary: $success succeeded, $failed failed out of $count files"
echo "Data files saved to: $DATA_DIR"
echo ""
echo "View results:"
echo "  1. Open schema/index.html in a web browser"
echo "  2. Load any .json file from the data/ directory"
echo "  3. View or export as CSV"
