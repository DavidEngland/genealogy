#!/usr/bin/env bash
# Author: David Edward England, PhD
# ORCID: https://orcid.org/0009-0001-2095-6646
# Repo: https://github.com/DavidEngland/genealogy
# Script stub.

# Single person to Markdown
php scripts/gedcom_to_biography.php --input GEDs/file.ged --person @I123@ --out person.md

# Batch to Markdown with reference databases
php scripts/gedcom_to_biography.php --input GEDs/file.ged --out output/

# Export as JSON
php scripts/gedcom_to_biography.php --input GEDs/file.ged --json --out data.json

# Batch to Markdown with reference databases
php scripts/gedcom_to_biography.php --input GEDs/England-1357.ged --out ancestors/