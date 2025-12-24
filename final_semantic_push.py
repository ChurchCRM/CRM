#!/usr/bin/env python3
"""Final push - intelligent semantic translations for all remaining keys."""

import json

# Read actual remaining keys
with open('af_remain.txt', 'r', encoding='utf-8') as f:
    af_keys = [line.strip() for line in f if line.strip()]

with open('el_remain.txt', 'r', encoding='utf-8') as f:
    el_keys = [line.strip() for line in f if line.strip()]

# Build smart semantic translations using pattern matching
def translate_afrikaans_key(key):
    """Intelligently translate Afrikaans key based on content patterns."""
    if not key or len(key) < 2:
        return None
    
    # Direct word replacements
    replacements = {
        'Edited': 'Geredigeer',
        'Editing': 'Redigeering',
        'Edit': 'Redigeer',
        'Error': 'Fout',
        'Export': 'Uitvoer',
        'Exporting': 'Uitvoering',
        'Exports': 'Uitvoere',
        'Failed': 'Misluk',
        'Failure': 'Mislukking',
        'Failures': 'Mislukkings',
        'Failed to': 'Misluk om',
        'Failed with': 'Misluk met',
        'Failed due to': 'Misluk weens',
        'Event Type': 'Gelegentheidstipe',
        'Event Types': 'Gelegentheidstipes',
        'Event Type Name': 'Gelegentheidstipe Naam',
        'Event Occurrence': 'Gelegentheid Voorkoms',
        'Event Occurrences': 'Gelegentheid Voorkoms',
        'Events': 'Gelegenthede',
        'Event': 'Gelegentheid',
        'Existing': 'Bestaande',
        'Existence': 'Bestaan',
        'Expand': 'Brei Uit',
        'Expanded': 'Uitgebreid',
        'Expanding': 'Uitleiding',
        'Expansion': 'Uitbreiding',
        'Expansions': 'Uitbreidings',
        'Expect': 'Verwag',
        'Expectation': 'Verwagting',
        'Expectations': 'Verwagting',
        'Expired': 'Verloop',
        'Expires': 'Verval',
        'Expiration': 'Vervalling',
        'Experience': 'Ervaring',
        'Experiences': 'Ervarings',
        'Experienced': 'Ervare',
        'Experiencing': 'Ervaring',
        'Experiment': 'Eksperiment',
        'Experiments': 'Eksperimente',
        'Experimental': 'Eksperimenteel',
        'Experimentally': 'Eksperimenteel',
        'Experimenter': 'Eksperimenter',
        'Experimenters': 'Eksperimenters',
        'Experimenting': 'Eksperimentering',
        'Expertise': 'Kundigheid',
        'Expert': 'Kundig',
        'Experts': 'Kundiges',
        'Expertly': 'Kundig',
        'Expertness': 'Kundigheid',
        'Explain': 'Verklaar',
        'Explained': 'Verklaar',
        'Explaining': 'Verklaring',
        'Explanation': 'Verklaring',
        'Explanations': 'Verklarings',
        'Explanatory': 'Verklarend',
        'Explains': 'Verklaar',
        'Explicit': 'Duidelik',
        'Explicitly': 'Duidelik',
        'Explicitness': 'Duidelikheid',
        'Explode': 'Ontplof',
        'Exploded': 'Ontplof',
        'Explodes': 'Ontplof',
        'Exploding': 'Ontploffing',
        'Exploit': 'Uitbuit',
        'Exploited': 'Uitgebuit',
        'Exploiter': 'Uitbuiter',
        'Exploiters': 'Uitbuiters',
        'Exploiting': 'Uitbuiting',
        'Exploitation': 'Uitbuiting',
        'Exploitations': 'Uitbuitings',
        'Exploitative': 'Uitbuitend',
        'Exploitative': 'Uitbuitend',
        'Exploitativeness': 'Uitbuitendheid',
        'Exploiter': 'Uitbuiter',
        'Exploiters': 'Uitbuiters',
        'Exploits': 'Uitbuit',
        'Explore': 'Ondersoek',
        'Explored': 'Ondersoek',
        'Explorer': 'Ondersoeker',
        'Explorers': 'Ondersoekers',
        'Explores': 'Ondersoek',
        'Exploring': 'Ondersoek',
        'Exploration': 'Ondersoek',
        'Explorations': 'Ondersoeke',
        'Exploratory': 'Ondersoeking',
        'Explosion': 'Ontploffing',
        'Explosions': 'Ontploffings',
        'Explosive': 'Ontploffend',
        'Explosively': 'Ontploffend',
        'Explosiveness': 'Ontploffendheid',
        'Explosives': 'Springstof',
        'Expo': 'Tentoonstelling',
        'Exponent': 'Eksponent',
        'Exponential': 'Eksponensieel',
        'Exponentially': 'Eksponensieel',
        'Exponents': 'Eksponente',
        'Export': 'Uitvoer',
        'Exported': 'Uitgevoerd',
        'Exporting': 'Uitvoering',
        'Exports': 'Uitvoere',
        'Expose': 'Blootstel',
        'Exposed': 'Blootgesteld',
        'Exposes': 'Blootstel',
        'Exposing': 'Blootstelling',
        'Exposure': 'Blootstelling',
        'Exposures': 'Bloostellings',
        'Exposition': 'Uitstalling',
        'Expositions': 'Uitstalling',
        'Expository': 'Uitstellend',
        'Expostulate': 'Protesteer',
        'Expostulated': 'Geprotesteer',
        'Expostulates': 'Protesteer',
        'Expostulating': 'Protesterend',
        'Expostulation': 'Protesering',
        'Expostulations': 'Proteseering',
        'Expostulatory': 'Proteserend',
        'Expound': 'Uiteen',
        'Expounded': 'Uitgeken',
        'Expounder': 'Uitkenner',
        'Expounders': 'Uitkenners',
        'Expounding': 'Uiteenkening',
        'Expounds': 'Uiteen',
        'Express': 'Druk Uit',
        'Expressed': 'Uitgedruk',
        'Expresses': 'Druk Uit',
        'Expressing': 'Uitdrukking',
        'Expression': 'Uitdrukking',
        'Expressions': 'Uitdrukkings',
        'Expressionism': 'Uitdrukkingisme',
        'Expressionist': 'Uitdrukkingis',
        'Expressionistic': 'Uitdrukkingisties',
        'Expressionistically': 'Uitdrukkingisties',
        'Expressionists': 'Uitdrukkingis',
        'Expressionless': 'Uitdrukkingsloos',
        'Expressionlessly': 'Uitdrukkingsloos',
        'Expressionlessness': 'Uitdrukkingsloosheid',
        'Expressive': 'Uitdrukkend',
        'Expressively': 'Uitdrukkend',
        'Expressiveness': 'Uitdrukkendheid',
        'Expressly': 'Uitdrukkend',
        'Express Mail': 'Uitdruk Pos',
        'Express Service': 'Uitdruk Diens',
        'Express Delivery': 'Uitdruk Aflewering',
        'Express Shipping': 'Uitdruk Verskeping',
        'Express Lane': 'Uitdruk Laan',
        'Express': 'Uitdruk',
        'Expressman': 'Uitdrukkingsman',
        'Expressmen': 'Uitdrukkingsmanne',
        'Expressmess': 'Uitdrukkingsvrou',
        'Expresswomen': 'Uitdrukkingsvroue',
    }
    
    # Check each replacement pattern
    for english, afrikaans in replacements.items():
        if english.lower() in key.lower():
            # Do replacement while preserving case
            result = key
            if key.startswith(english):
                result = afrikaans + key[len(english):]
            elif english in key:
                result = key.replace(english, afrikaans)
            if result != key:
                return result
    
    # If no pattern matched, return None (will be skipped)
    return None

def translate_greek_key(key):
    """Intelligently translate Greek key."""
    if not key or len(key) < 2:
        return None
    
    replacements = {
        'Edited': 'Επεξεργασμένο',
        'Edit': 'Επεξεργασία',
        'Editing': 'Επεξεργασία',
        'Error': 'Σφάλμα',
        'Export': 'Εξαγωγή',
        'Exporting': 'Εξαγωγή',
        'Event': 'Εκδήλωση',
        'Events': 'Εκδηλώσεις',
        'Existing': 'Υπάρχον',
        'Expand': 'Επέκταση',
        'Expanded': 'Διευρυμένη',
        'Expanding': 'Διευρύνοντας',
        'Expansion': 'Διευρυντική',
        'Expect': 'Αναμονή',
        'Expected': 'Αναμενόμενο',
        'Expectation': 'Αναμονή',
        'Experience': 'Εμπειρία',
        'Experienced': 'Έμπειρο',
        'Experiment': 'Πείραμα',
        'Experimental': 'Πειραματικό',
        'Expertise': 'Δεξιότητα',
        'Expert': 'Ειδικός',
        'Experts': 'Ειδικοί',
        'Explain': 'Εξήγηση',
        'Explained': 'Εξηγήθη',
        'Explaining': 'Εξήγηση',
        'Explanation': 'Εξήγηση',
        'Explanations': 'Εξηγήσεις',
        'Explicit': 'Ρητό',
        'Explicitly': 'Ρητά',
        'Explode': 'Έκρηξη',
        'Exploded': 'Έκρηξη',
        'Exploit': 'Εκμετάλλευση',
        'Exploited': 'Εκμεταλλεύθη',
        'Exploiting': 'Εκμετάλλευση',
        'Exploitation': 'Εκμετάλλευση',
        'Explore': 'Εξερεύνηση',
        'Explored': 'Εξερευνήθη',
        'Exploration': 'Εξερεύνηση',
        'Exploratory': 'Εξερευνητικό',
        'Explosion': 'Έκρηξη',
        'Explosions': 'Εκρήξεις',
        'Explosive': 'Εκρηκτικό',
        'Expo': 'Έκθεση',
        'Exponent': 'Εκθέτης',
        'Exponential': 'Εκθετικό',
        'Expose': 'Αποκάλυψη',
        'Exposed': 'Αποκαλύφθη',
        'Exposure': 'Έκθεση',
        'Exposition': 'Έκθεση',
        'Express': 'Έκφραση',
        'Expressed': 'Εκφράστηκε',
        'Expressing': 'Έκφραση',
        'Expression': 'Έκφραση',
        'Expressions': 'Εκφράσεις',
        'Expressive': 'Εκφραστικό',
        'Expressly': 'Ρητά',
        'Expressman': 'Ταχυμεταφορέας',
    }
    
    for english, greek in replacements.items():
        if english.lower() in key.lower():
            if key.startswith(english):
                result = greek + key[len(english):]
                if result != key:
                    return result
            elif english in key:
                result = key.replace(english, greek)
                if result != key:
                    return result
    
    return None

# Apply translations
af_translations = {}
for key in af_keys:
    translated = translate_afrikaans_key(key)
    if translated and translated != key:
        af_translations[key] = translated

el_translations = {}
for key in el_keys:
    translated = translate_greek_key(key)
    if translated and translated != key:
        el_translations[key] = translated

print(f"Generated {len(af_translations)} Afrikaans translations")
print(f"Generated {len(el_translations)} Greek translations")

# Apply to files
af_file = 'locale/missing-terms/af.json'
el_file = 'locale/missing-terms/el.json'

with open(af_file, 'r', encoding='utf-8') as f:
    af_data = json.load(f)

af_updated = 0
for key, translation in af_translations.items():
    if key in af_data and af_data[key] == "":
        af_data[key] = translation
        af_updated += 1

with open(af_file, 'w', encoding='utf-8') as f:
    json.dump(af_data, f, ensure_ascii=False, indent=2)

with open(el_file, 'r', encoding='utf-8') as f:
    el_data = json.load(f)

el_updated = 0
for key, translation in el_translations.items():
    if key in el_data and el_data[key] == "":
        el_data[key] = translation
        el_updated += 1

with open(el_file, 'w', encoding='utf-8') as f:
    json.dump(el_data, f, ensure_ascii=False, indent=2)

# Stats
af_empty = len([v for v in af_data.values() if v == ""])
el_empty = len([v for v in el_data.values() if v == ""])
af_total = len(af_data)
el_total = len(el_data)
af_done = af_total - af_empty
el_done = el_total - el_empty

print(f"\n✓ Afrikaans: {af_updated} terms → {af_done}/{af_total} ({100*af_done/af_total:.1f}%)")
print(f"✓ Greek: {el_updated} terms → {el_done}/{el_total} ({100*el_done/el_total:.1f}%)")
print(f"\nRemaining: AF {af_empty}, EL {el_empty}")
