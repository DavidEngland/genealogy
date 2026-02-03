#!/usr/bin/env python3

filepath = "16th-Regiment-Mississippi-Militia-War-of-1812.md"
with open(filepath, 'r') as f:
    lines = f.readlines()

# Find the start of muster roll
in_muster_roll = False
soldiers = []

for i, line in enumerate(lines):
    if "=== Muster Roll ===" in line:
        in_muster_roll = True
        print(f"✓ Found muster roll section at line {i}")
        continue
    
    # Stop if we hit next major section (3 equals)
    if in_muster_roll and line.startswith("===") and "Muster Roll" not in line and not line.startswith("===="):
        print(f"✓ End of muster roll at line {i}")
        break
    
    # Extract soldier entries (must have exactly 3 comma-separated parts)
    if in_muster_roll and line.strip().startswith("* ") and not line.strip().startswith("=="):
        entry = line.strip()[2:].strip()
        parts = [p.strip() for p in entry.split(',')]
        
        if len(parts) == 3:
            last_name, first_name, rank = parts
            soldiers.append({
                'first_name': first_name,
                'last_name': last_name,
                'rank': rank
            })

print(f"\n✓ Found {len(soldiers)} soldiers")
if soldiers:
    print("\nFirst 10 soldiers:")
    for s in soldiers[:10]:
        print(f"  {s['first_name']} {s['last_name']}, {s['rank']}")


