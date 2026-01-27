from gedcom.parser import Parser
from gedcom.element.individual import Individual
import json

parser = Parser()
parser.parse_file('/Users/davidengland/Documents/GitHub/genealogy/GEDs/Gresham-187.ged')

root = parser.get_root_child_elements()
output = []

for element in root:
    if isinstance(element, Individual):
        output.append({
            'id': element.get_pointer(),
            'name': ' '.join(element.get_name()),
            'birth_date': element.get_birth_data()[0] if element.get_birth_data() else None,
            'birth_place': element.get_birth_data()[1] if element.get_birth_data() else None,
            'death_date': element.get_death_data()[0] if element.get_death_data() else None,
            'notes': element.get_notes(),
        })

with open('output.json', 'w') as f:
    json.dump(output, f, indent=2)
