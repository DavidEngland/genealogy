#!/usr/bin/env bash
# Script stub.

# Single person to Markdown
php scripts/gedcom_to_biography.php --input GEDs/file.ged --person @I123@ --out person.md

# Batch to Markdown with reference databases
php scripts/gedcom_to_biography.php --input GEDs/file.ged --out output/

# Export as JSON
php scripts/gedcom_to_biography.php --input GEDs/file.ged --json --out data.json

# Batch to Markdown with reference databases
php scripts/gedcom_to_biography.php --input GEDs/England-1357.ged --out ancestors/