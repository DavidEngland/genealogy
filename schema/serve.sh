#!/bin/bash
# Launch PHP development server for genealogy schema
# Access at: http://localhost:8000/schema/

PORT=${1:-8000}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🧬 Genealogy Schema Server"
echo "============================"
echo ""
echo "Starting PHP server on port $PORT"
echo "Root directory: $ROOT_DIR"
echo ""
echo "Access points:"
echo "  • Web interface: http://localhost:$PORT/schema/"
echo "  • Data files: http://localhost:$PORT/data/"
echo ""
echo "Press Ctrl+C to stop server"
echo ""

cd "$ROOT_DIR"
php -S localhost:$PORT
