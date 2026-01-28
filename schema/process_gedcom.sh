#!/bin/bash
# Flexible GEDCOM Processor - Single or Multiple Files
# Extracts WikiTree IDs, FamilySearch IDs, and generates CSV mapping

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARSER="$SCRIPT_DIR/enhanced_gedcom_parser.php"
EXPORTER="$SCRIPT_DIR/csv_exporter.php"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
OUTPUT_DIR=""
VERBOSE=0
GENERATE_CSV=1
INPUT_FILES=()

print_usage() {
    cat << EOF
Genealogy GEDCOM Processor - Single or Multiple Files

USAGE:
  $(basename "$0") [OPTIONS] [file1.ged] [file2.ged] ...

OPTIONS:
  -h, --help              Show this help message
  -o, --output DIR        Output directory (default: data/)
  -v, --verbose           Verbose output
  --no-csv                Skip CSV export
  --all                   Process all .ged files in GEDs/ folder

EXAMPLES:
  # Process single file
  $(basename "$0") GEDs/myfile.ged

  # Process multiple files
  $(basename "$0") GEDs/file1.ged GEDs/file2.ged GEDs/file3.ged

  # Process all files
  $(basename "$0") --all

  # Custom output directory
  $(basename "$0") -o /custom/path GEDs/file1.ged

  # Verbose output
  $(basename "$0") -v GEDs/myfile.ged

EOF
}

# Parse arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                print_usage
                exit 0
                ;;
            -o|--output)
                OUTPUT_DIR="$2"
                shift 2
                ;;
            -v|--verbose)
                VERBOSE=1
                shift
                ;;
            --no-csv)
                GENERATE_CSV=0
                shift
                ;;
            --all)
                # Process all GEDCOM files in GEDs/ folder
                for file in "$SCRIPT_DIR"/../GEDs/*.ged; do
                    if [ -f "$file" ]; then
                        INPUT_FILES+=("$file")
                    fi
                done
                shift
                ;;
            *)
                if [[ "$1" == *.ged ]]; then
                    INPUT_FILES+=("$1")
                else
                    echo "Error: Unknown option or invalid file: $1"
                    print_usage
                    exit 1
                fi
                shift
                ;;
        esac
    done
}

# Setup
setup() {
    # Default output directory
    if [ -z "$OUTPUT_DIR" ]; then
        OUTPUT_DIR="$SCRIPT_DIR/../data"
    fi
    
    mkdir -p "$OUTPUT_DIR"
    
    # Validate input files
    if [ ${#INPUT_FILES[@]} -eq 0 ]; then
        echo "Error: No GEDCOM files specified"
        print_usage
        exit 1
    fi
    
    for file in "${INPUT_FILES[@]}"; do
        if [ ! -f "$file" ]; then
            echo "Error: File not found: $file"
            exit 1
        fi
    done
}

# Process single GEDCOM file
process_file() {
    local gedcom_file="$1"
    local base_name=$(basename "$gedcom_file" .ged)
    
    local json_file="$OUTPUT_DIR/$base_name.json"
    local ids_csv="$OUTPUT_DIR/$base_name-ids.csv"
    local lookup_csv="$OUTPUT_DIR/$base_name-lookup.csv"
    local full_csv="$OUTPUT_DIR/$base_name-full.csv"
    
    echo -e "${BLUE}Processing:${NC} $base_name"
    
    # Parse GEDCOM
    if [ $VERBOSE -eq 1 ]; then
        php "$PARSER" "$gedcom_file" "$json_file" "$ids_csv" -v
    else
        php "$PARSER" "$gedcom_file" "$json_file" "$ids_csv" 2>/dev/null
    fi
    
    # Count extracted IDs
    if [ -f "$ids_csv" ]; then
        local id_count=$(($(wc -l < "$ids_csv") - 1))
        local wikitree_count=$(grep -c 'wikitree' "$ids_csv" 2>/dev/null || echo 0)
        local familysearch_count=$(grep -c 'familysearch' "$ids_csv" 2>/dev/null || echo 0)
        
        echo -e "  ${GREEN}✓${NC} JSON: $json_file"
        echo "    IDs mapped: $id_count people, $wikitree_count WikiTree, $familysearch_count FamilySearch"
        echo -e "  ${GREEN}✓${NC} ID mapping: $ids_csv"
    else
        echo -e "  ${GREEN}✓${NC} JSON: $json_file"
    fi
    
    # Export CSVs if requested
    if [ $GENERATE_CSV -eq 1 ]; then
        php "$EXPORTER" "$json_file" "$lookup_csv" lookup 2>/dev/null
        echo -e "  ${GREEN}✓${NC} CSV (lookup): $lookup_csv"
        
        php "$EXPORTER" "$json_file" "$full_csv" full 2>/dev/null
        echo -e "  ${GREEN}✓${NC} CSV (full): $full_csv"
    fi
    
    echo ""
}

# Main
main() {
    parse_args "$@"
    setup
    
    echo -e "${YELLOW}╔════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║         GENEALOGY GEDCOM PROCESSOR - WikiTree ID Extraction     ║${NC}"
    echo -e "${YELLOW}╚════════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo "Output directory: $OUTPUT_DIR"
    echo "Files to process: ${#INPUT_FILES[@]}"
    echo ""
    
    local processed=0
    local failed=0
    
    for file in "${INPUT_FILES[@]}"; do
        if process_file "$file"; then
            ((processed++))
        else
            ((failed++))
            echo -e "  ${YELLOW}✗${NC} Failed to process: $file"
        fi
    done
    
    echo -e "${YELLOW}════════════════════════════════════════════════════════════════${NC}"
    echo -e "Summary: ${GREEN}$processed succeeded${NC}"
    if [ $failed -gt 0 ]; then
        echo -e "Failed: ${YELLOW}$failed${NC}"
    fi
    echo -e ""
    echo "ID mapping files generated (CSV format):"
    ls -1 "$OUTPUT_DIR"/*-ids.csv 2>/dev/null | head -10 || echo "  (none)"
    echo ""
    echo "View ID mappings with:"
    echo "  head -20 $OUTPUT_DIR/*-ids.csv"
}

# Run
main "$@"
