#!/usr/bin/env python3
"""Final targeted push - comprehensive translations for remaining keys."""

import json

# High-value translations for remaining keys
af_translations = {
    "Filter by Type": "Filters Volgens Tipe",
    "Head Count": "Koptellingnomber",
    "Manage Check-ins": "Bestuur Inskakeling",
    "Members, Visitors, Children": "Lede, Besoekers, Kinders",
    "Monthly Averages": "Maandelikse Gemiddeldes",
    "Monthly on the": "Maandeliks op die",
    "No event types defined yet.": "Geen gelegentheidstipes gedefinieer nog.",
    "None (one-time event)": "Niks (eenmalige gelegentheid)",
    "Optional - Add sermon notes or additional event details": "Opsioneel - Voeg prediking notas of bykomende gelegentheidsbesonderhede",
    "Optional - leave blank if not tracking": "Opsioneel - laat leeg as jy nie spoor hou nie",
    "Optional notes about attendance...": "Opsioneel notas oor bywoning...",
    "People Checked In": "Mense Ingeskakeld",
    "People in Cart": "Mense in Mandjie",
    "Person Checking In": "Persoon Inskakeling",
    "Recurrence": "Herhaling",
    "Required fields": "Verpligte velde",
    "Search by name or email...": "Soek volgens naam of e-pos...",
    "Search for adult...": "Soek na volwassene...",
    "Search for supervisor...": "Soek na toesighouer...",
    "Select start and end date/time": "Kies begin en eind datum/tyd",
    "Adult": "Volwassene",
    "Adults": "Volwassenes",
    "Age": "Ouderdom",
    "Ages": "Ouderdomme",
    "Approval": "Goedkeuring",
    "Approvals": "Goedkeurings",
    "Approved": "Goedgekeurd",
    "Approve": "Keur Goed",
    "Application": "Toepassing",
    "Applications": "Toepassings",
    "Apply": "Pas Toe",
    "Applied": "Toegepas",
    "Applying": "Toepassing",
    "Attachment": "Aanhangsel",
    "Attachments": "Aanhangsels",
    "Attach": "Heg Aan",
    "Attached": "Geheg",
    "Attaching": "Hegting",
    "Attention": "Aandag",
    "Attend": "Bywoon",
    "Attendance": "Bywoning",
    "Attendances": "Bywonings",
    "Attendee": "Bywoner",
    "Attendees": "Bywoners",
    "Attended": "Bygewoon",
    "Attending": "Bywoning",
    "Attribute": "Kenmerk",
    "Attributes": "Kenmerke",
    "Audit": "Oudit",
    "Audits": "Oudits",
    "Audited": "Ouditgekeurd",
    "Auditing": "Ouditering",
    "Audio": "Geluid",
    "Audios": "Geluide",
    "August": "Augustus",
    "Authority": "Gesag",
    "Authorities": "Gesage",
    "Authorize": "Magtig",
    "Authorized": "Gemagtigd",
    "Authorizing": "Magtiging",
    "Authorization": "Magtiging",
    "Authorizations": "Magtiges",
    "Author": "Skrywer",
    "Authors": "Skrywers",
    "Auto": "Outomaties",
    "Automated": "Outomatiesed",
    "Automate": "Outomatiseer",
    "Automation": "Outomatisering",
    "Automations": "Outomatiserings",
    "Automatic": "Outomaties",
    "Automatically": "Outomatiesweg",
    "Availability": "Beskikbaarheid",
    "Available": "Beskikbaar",
    "Availability": "Beskikbaarheid",
    "Average": "Gemiddelde",
    "Averages": "Gemiddeldes",
    "Avoid": "Vermy",
    "Avoided": "Vermyd",
    "Avoiding": "Vermyding",
    "Avoidance": "Vermyding",
    "Avoidances": "Vermydings",
    "Award": "Prys",
    "Awards": "Pryse",
    "Awarded": "Toekenning",
    "Awarding": "Toekenning",
    "Awareness": "Bewustheid",
    "Aware": "Bewus",
    "Axe": "As",
    "Axes": "Asse",
    "Axis": "As",
}

el_translations = {
    "Filter by Type": "Φίλτρο κατά τύπο",
    "Head Count": "Σύνολο ατόμων",
    "Manage Check-ins": "Διαχείριση Εισόδων",
    "Members, Visitors, Children": "Μέλη, Επισκέπτες, Παιδιά",
    "Monthly Averages": "Μηνιαία Μέσα",
    "Monthly on the": "Μηνιαία στο",
    "No event types defined yet.": "Δεν έχουν οριστεί τύποι εκδήλωσης ακόμη.",
    "None (one-time event)": "Κανένα (εκδήλωση μιας φοράς)",
    "Optional - Add sermon notes or additional event details": "Προαιρετικό - Προσθέστε σημειώσεις κηρύγματος ή πρόσθετες λεπτομέρειες εκδήλωσης",
    "Optional - leave blank if not tracking": "Προαιρετικό - αφήστε κενό εάν δεν παρακολουθείτε",
    "Optional notes about attendance...": "Προαιρετικές σημειώσεις σχετικά με τη συμμετοχή...",
    "People Checked In": "Άτομα που Κάνουν Είσοδο",
    "People in Cart": "Άτομα στο Καλάθι",
    "Person Checking In": "Άτομο Κάνει Είσοδο",
    "Recurrence": "Επανάληψη",
    "Required fields": "Απαιτούμενα πεδία",
    "Search by name or email...": "Αναζήτηση κατά όνομα ή ηλ. ταχυδρομείο...",
    "Search for adult...": "Αναζητήστε για ενήλικο...",
    "Search for supervisor...": "Αναζητήστε για επόπτη...",
    "Select start and end date/time": "Επιλέξτε ημερομηνία / ώρα έναρξης και λήξης",
    "Adult": "Ενήλικος",
    "Adults": "Ενήλικες",
    "Age": "Ηλικία",
    "Ages": "Ηλικίες",
    "Approval": "Έγκριση",
    "Approvals": "Εγκρίσεις",
    "Approved": "Εγκρίθηκε",
    "Approve": "Εγκρίνω",
    "Application": "Εφαρμογή",
    "Applications": "Εφαρμογές",
    "Apply": "Εφαρμόζω",
    "Applied": "Εφαρμόθηκε",
    "Applying": "Εφαρμογή",
    "Attachment": "Επισύναψη",
    "Attachments": "Επισυνάψεις",
    "Attach": "Επισυνάπτω",
    "Attached": "Επισυναφθεί",
    "Attaching": "Επίσυναψη",
    "Attention": "Προσοχή",
    "Attend": "Παρακολουθώ",
    "Attendance": "Παρουσία",
    "Attendances": "Παρουσίες",
    "Attendee": "Συμμετέχων",
    "Attendees": "Συμμετέχοντες",
    "Attended": "Παρακολουθήθηκε",
    "Attending": "Παρακολούθηση",
    "Attribute": "Χαρακτηριστικό",
    "Attributes": "Χαρακτηριστικά",
    "Audit": "Έλεγχος",
    "Audits": "Έλεγχοι",
    "Audited": "Ελεγχόμενο",
    "Auditing": "Ελεγχόμενο",
    "Audio": "Ήχος",
    "Audios": "Ήχοι",
    "August": "Αύγουστος",
    "Authority": "Αρχή",
    "Authorities": "Αρχές",
    "Authorize": "Εξουσιοδοτώ",
    "Authorized": "Εξουσιοδοτημένο",
    "Authorizing": "Εξουσιοδότηση",
    "Authorization": "Εξουσιοδότηση",
    "Authorizations": "Εξουσιοδοτήσεις",
    "Author": "Συγγραφέας",
    "Authors": "Συγγραφείς",
    "Auto": "Αυτό",
    "Automated": "Αυτοματοποιημένο",
    "Automate": "Αυτοματοποιώ",
    "Automation": "Αυτοματοποίηση",
    "Automations": "Αυτοματοποιήσεις",
    "Automatic": "Αυτόματος",
    "Automatically": "Αυτόματα",
    "Availability": "Διαθεσιμότητα",
    "Available": "Διαθέσιμο",
    "Average": "Μέσος",
    "Averages": "Μέσα",
    "Avoid": "Αποφεύγω",
    "Avoided": "Αποφεύχθηκε",
    "Avoiding": "Αποφυγή",
    "Avoidance": "Αποφυγή",
    "Avoidances": "Αποφυγές",
    "Award": "Βραβείο",
    "Awards": "Βραβεία",
    "Awarded": "Βραβεύτηκε",
    "Awarding": "Απονομή",
    "Awareness": "Ευαισθητοποίηση",
    "Aware": "Συνειδητός",
    "Axe": "Τσεκούρι",
    "Axes": "Τσεκούρια",
    "Axis": "Άξονας",
}

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
