#!/usr/bin/env python3
# Author: David Edward England, PhD
# ORCID: https://orcid.org/0009-0001-2095-6646
# Repo: https://github.com/DavidEngland/genealogy
# Quick debug to see what soldier names are extracted

filepath = "16th-Regiment-Mississippi-Militia-War-of-1812.md"
soldiers = []

with open(filepath, 'r') as f:
    lines = f.readlines()

in_muster_roll = False
for line in lines:
    if "=== Muster Roll ===" in line:
        in_muster_roll = True
        continue
    if in_muster_roll and line.startswith("===") and "Muster Roll" not in line and not line.startswith("===="):
        break
    if in_muster_roll and line.strip().startswith("* ") and not line.strip().startswith("=="):
        entry = line.strip()[2:].strip()
        # Remove markdown bold markers if present
        entry = entry.replace("'''", "")
        parts = [p.strip() for p in entry.split(',')]
        if len(parts) == 3:
            last_name = parts[0]
            first_name = parts[1]
            rank = parts[2]
            soldiers.append({
                'first_name': first_name,
                'last_name': last_name,
                'rank': rank
            })

# Find Hargrove entries
print("Hargrove/Hartgrove entries:")
for s in soldiers:
    if 'Har' in s['last_name']:
        print(f"  {s['first_name']} {s['last_name']}, {s['rank']}")
        # Check matching logic
        match_key = (s['first_name'], s['last_name'], s['rank'].lower())
        print(f"    Match key: {match_key}")
