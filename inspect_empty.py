#!/usr/bin/env python3
import json
import os

# Get list of empty keys from both files to translate them comprehensively
af_file = '/Users/gdawoud/Development/ChurchCRM/CRM/locale/missing-terms/af.json'
el_file = '/Users/gdawoud/Development/ChurchCRM/CRM/locale/missing-terms/el.json'

# Load files
with open(af_file, 'r', encoding='utf-8') as f:
    af_data = json.load(f)
with open(el_file, 'r', encoding='utf-8') as f:
    el_data = json.load(f)

# Get empty keys
af_empty_keys = [k for k, v in af_data.items() if v == ""]
el_empty_keys = [k for k, v in el_data.items() if v == ""]

print(f"Afrikaans empty keys: {len(af_empty_keys)}")
print(f"Greek empty keys: {len(el_empty_keys)}")
print(f"\nFirst 10 Afrikaans empty keys:")
for key in af_empty_keys[:10]:
    print(f"  {key}")
print(f"\nFirst 10 Greek empty keys:")
for key in el_empty_keys[:10]:
    print(f"  {key}")
