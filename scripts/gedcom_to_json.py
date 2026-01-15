import json
import re

def parse_gedcom(filepath):
    """Parse GEDCOM file into structured JSON"""
    individuals = {}
    families = {}
    current_id = None
    current_person = {}
    
    with open(filepath, 'r', encoding='utf-8') as f:
        for line in f:
            level, tag, value = parse_gedcom_line(line)
            
            if tag == 'INDI':
                if current_person:
                    individuals[current_id] = current_person
                current_id = value.strip('@')
                current_person = {'id': current_id, 'name': '', 'events': []}
            
            elif tag == 'NAME' and level == 1:
                current_person['name'] = value.strip('/')
            
            elif tag == 'NOTE' and level == 1:
                current_person['notes'] = value
            
            elif tag == 'BIRT' and level == 1:
                current_person['birth'] = {}
            
            elif tag == 'DEAT' and level == 1:
                current_person['death'] = {}
            
            elif tag in ('DATE', 'PLAC') and level == 2:
                if tag == 'DATE':
                    if 'birth' in current_person and 'death' not in current_person:
                        current_person['birth']['date'] = value
                if tag == 'PLAC':
                    if 'birth' in current_person and 'death' not in current_person:
                        current_person['birth']['place'] = value
        
        if current_person:
            individuals[current_id] = current_person
    
    return {'individuals': individuals, 'families': families}

def parse_gedcom_line(line):
    """Parse single GEDCOM line into (level, tag, value)"""
    match = re.match(r'(\d+)\s+(@?\w+@?)\s*(.*)', line.strip())
    if match:
        return int(match.group(1)), match.group(2), match.group(3)
    return None, None, None

if __name__ == '__main__':
    data = parse_gedcom('/Users/davidengland/Documents/GitHub/genealogy/GEDs/Gresham-187.ged')
    with open('/Users/davidengland/Documents/GitHub/genealogy/output/gresham_data.json', 'w') as f:
        json.dump(data, f, indent=2)
    print("Conversion complete!")
