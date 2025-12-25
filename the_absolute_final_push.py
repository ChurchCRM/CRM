#!/usr/bin/env python3
"""Absolute Final Push - Reach 100% on both AF and EL"""

import json

af_final = {
    "Unclassified": "Ongelassifiseerde",
    "Upgrade database now": "Oppgradering databasis nou",
    "Your ChurchCRM installation needs a database upgrade to match the installed software version. This operation will\n apply schema and data migrations. Please ensure you have a recent backup before proceeding.": "Jou ChurchCRM-installasie benodig 'n databasisbopgradering om die geïnstalleerde sagteware-weergawe aan te pas. Hierdie operasie sal\n skema- en datamigrasiebeheer toepas. Maak seker dat jy 'n onlangse rugsteun het voordat jy voortgaan.",
    "Orphaned files are PHP or JavaScript files that exist on your server but are not part of the official ChurchCRM release. These files may be leftover from previous versions and could pose security risks if they contain outdated code with vulnerabilities.": "Weeslêers is PHP- of JavaScript-lêers wat op jou bediener bestaan maar nie deel van die amptelike ChurchCRM-vrylating is nie. Hierdie lêers mag oorblyfsel van vorige weergawes wees en kan sekuriteitsrisiko's pose as hulle verouderde kode met gapings bevat.",
    "The following files exist on your server but are NOT part of the official ChurchCRM release. These may be leftover from previous versions and could pose security risks.": "Die volgende lêers bestaan op jou bediener maar is NIE deel van die amptelike ChurchCRM-vrylating nie. Dit mag oorblyfsel van vorige weergawes wees en kan sekuriteitsrisiko's pose.",
    "The page you tried to visit requires special permissions that your account does not currently have.": "Die bladsy wat jy probeer het, vereis spesiale toestemmings wat jou rekening tans nie het nie.",
    "These files are not part of the official release and may pose security risks.": "Hierdie lêers is nie deel van die amptelike vrylating nie en mag sekuriteitsrisiko's pose.",
    "These files were likely part of an older ChurchCRM version and were not cleaned up during a previous upgrade. Deleting them will improve your system security.": "Hierdie lêers was waarskynlik deel van 'n ouer ChurchCRM-weergawe en is nie skoongemaak tydens 'n vorige opgradering nie. Die uitwissing daarvan sal jou stelselsekuriteit verbeter.",
    "These files were likely part of an older ChurchCRM version and were not cleaned up during a previous upgrade. They may contain outdated code with security vulnerabilities.": "Hierdie lêers was waarskynlik deel van 'n ouer ChurchCRM-weergawe en is nie skoongemaak tydens 'n vorige opgradering nie. Dit mag verouderde kode met sekuriteitsleemtes bevat.",
    "This action cannot be undone. Make sure you have a backup of the current data if needed.": "Hierdie aksie kan nie ongedaan gemaak word nie. Maak seker dat jy 'n rugsteun van die huidge gegewens het as nodig.",
    "This may take several minutes for large databases. Do not close this page.": "Dit kan verskeie minute vat vir groot databases. Moenie hierdie bladsy sluit nie.",
    "This will re-download and re-apply the current version. This can fix corrupted or modified files. Continue?": "Dit sal die huidge weergawe opnuut aflaai en toepas. Dit kan korrupte of gewysigde lêers regmaak. Gaan voort?",
    "Upgrade Summary": "Opgradering Opsomming",
    "Use external tools (GPG, 7-Zip) to encrypt backups before storing off-site.": "Gebruik eksterne gereedskap (GPG, 7-Zip) om rugsteune te enkripteer voordat jy af-ter-plekke stoor.",
    "User authentication": "Gebruiker Verifikasie",
    "User must have Menu Options permission": "Gebruiker moet Spyskaartopsies-toestemming hê",
    "What are Orphaned Files?": "Wat is Weeslêers?",
    "You will be logged out and redirected to the login page.": "Jy sal afgemel word en herlei na die aanmeldingsbladsy.",
    "Your ChurchCRM installation is clean. All files on the server match the official release.": "Jou ChurchCRM-installasie is skoon. Alle lêers op die bediener stem ooreen met die amptelike vrylating.",
    "Import sample families, people, and groups to explore ChurchCRM with realistic data.": "Importeer voorbeeldgesinne, mense en groepe om ChurchCRM met realistiese gegewens te verken.",
    "Most reports can be exported as PDF for printing or CSV for spreadsheet analysis.": "Meeste verslae kan as PDF vir druk of CSV vir sigbladanalise uitgevoer word.",
    "Reports related to member voting eligibility and organization governance.": "Verslae wat verband hou met lidmaatskapstem-geskiktheid en organisasiegover.",
    "Track pledges and payment progress for campaigns and fiscal year budgeting.": "Spoor beloftes en betalingsvordering vir kampanjes en fiskaale jaarbudgetting.",
    "Type:": "Tipe:",
    "Unmet Prerequisites": "Onvoldane Vereistes",
    "Update Church Name": "Opdatering Kerknaam",
    "Use classification and family filters to generate reports for specific groups of donors.": "Gebruik klassifikasie- en gesinfilters om verslae vir spesifieke schenkergroepe te genereer.",
    "User accounts and roles": "Gebruikerrekeninge en rolle",
    "User must be an Admin or have Finance permission": "Gebruiker moet 'n Admin wees of Finansietoestemming hê",
    "Your fiscal year starts in month": "Jou fiskaale jaar begin in maand",
    "Starts with <strong>http://</strong> or <strong>https://</strong>": "Begin met <strong>http://</strong> of <strong>https://</strong>",
    "Unable to detect system locales": "Kan stemsellokale nie opspoor nie",
    "Unable to determine available locales": "Kan beskikbare lokale nie bepaal nie",
    "Unable to load locale information": "Kan lokale inligting nie laai nie",
    "Unable to load state list. Please check your network connection or try again later.": "Kan staatslys nie laai nie. Kontroleer asseblief jou netwerkverbinding of probeer later weer.",
    "Update it to a valid URL that:": "Opdatering dit na 'n geldige URL wat:",
    "View Logs": "Bekyk Logs",
    "View and manage system log files for debugging.": "Bekyk en bestuur stelselloglêers vir ontfout.",
    "You must enter a Last Name.": "Jy moet 'n Laaste Naam invoer.",
    "Your privacy is important. We never share your information with third parties.": "Jou privaatheid is belangrik. Ons deel jou inligting nooit met derde partye.",
    "Visit Our Website": "Besoek Ons Webwerf",
    "Your verification request has been received. Thank you for keeping your information up to date.": "Jou verifikasie-aanvraag is ontvang. Dankie dat jy jou inligting tydsaktueel hou.",
    "must be configured with an encryption key": "moet opgestel word met 'n enkripsietoetsl",
    "There are people assigned to this Volunteer Opportunity. Deletion will unassign:": "Daar is mense aan hierdie Vrywilliger-geleentheid toegeken. Verwydering sal ontoeken:",
    "Volunteer Opportunity": "Vrywilliger-geleentheid",
}

el_final = {
    "Closed": "Κλειστό",
    "Delete this person?": "Διαgrafe αυτό το πρόσωπο;",
    "Deposit ID": "Αναγνωριστικό Κατάθεσης",
    "Deposit Total": "Σύνολο Κατάθεσης",
    "Group Cart Status": "Κατάσταση Καλαθιού Ομάδας",
    "invalid group request": "Μη έγκυρη αίτηση ομάδας",
    "Hindi - India": "Χίντι - Ινδία",
    "Japanese": "Ιαπωνικά",
    "Tamil - India": "Τάμιλ - Ινδία",
    "Not Subscribed": "Δεν Εγγράφηκε",
    "property": "περιουσία",
    "Default Zip": "Προεπιλογή Ταχυδρομικού",
    "Credit Card People": "Ανθρώποι Πιστωτικής Κάρτας",
    "People who are configured to pay by credit card.": "Άτομα που έχουν ρυθμιστεί να πληρώνουν με πιστωτική κάρτα.",
    "As part of the restore, external backups have been disabled.  If you w": "Στο πλαίσιο της επαναφοράς, τα εξωτερικά αντίγραφα ασφαλείας έχουν απενεργοποιηθεί.",
    "Comma separated list of classifications that should appear as inactive": "Λίστα διαχωρισμένη με κόμματα κατάταξης που θα εμφανίζεται ως ανενεργη",
    "MailChimp is not configured": "Το MailChimp δεν έχει ρυθμιστεί",
    "Please update the MailChimp API key in:": "Παρακαλώ ενημερώστε το κλειδί MailChimp API σε:",
    "English - South Africa": "Αγγλικά - Νότια Αφρική",
    "English - Jamaica": "Αγγλικά - Τζαμάϊκα",
    "Swahili": "Σουαχίλι",
    "One character from FirstName and one character from LastName": "Ένας χαρακτήρας από FirstName και ένας χαρακτήρας από LastName",
    "Two characters from FirstName": "Δύο χαρακτήρες από FirstName",
    "Church Email not set, please visit the settings page": "Το email της εκκλησίας δεν έχει οριστεί, επισκεφθείτε τη σελίδα ρυθμίσεων",
    "Telugu - India": "Τελούγκου - Ινδία",
    "Korean": "Κορεατικά",
    "System Logs": "Σημειώματα Συστήματος",
    "Enforce Content Security Policy (CSP) to help protect against cross-si": "Επιβολή Πολιτικής Ασφάλειας Περιεχομένου (CSP) για προστασία κατά του cross-si",
    "Enter event description...": "Εισαγάγετε περιγραφή εκδήλωσης...",
    "Enter note text here...": "Εισαγάγετε κείμενο σημείωσης εδώ...",
    "No notes have been added for this person.": "Δεν έχουν προστεθεί σημειώσεις για αυτό το πρόσωπο.",
    "Note": "Σημείωση",
    "Log Settings": "Ρυθμίσεις Καταγραφής",
    "Log Level:": "Επίπεδο Καταγραφής:",
    "Save Log Level": "Αποθήκευση Επιπέδου Καταγραφής",
    "View application logs. Click on a log file to view its contents.": "Προβολή αρχείων καταγραφής εφαρμογής. Κάντε κλικ σε ένα αρχείο καταγραφής για προβολή του περιεχομένου του.",
    "Unclassified": "Ανταξιόλογο",
    "Upgrade database now": "Αναβάθμιση βάσης δεδομένων τώρα",
    "Upgrade Summary": "Περίληψη Αναβάθμισης",
    "Use external tools (GPG, 7-Zip) to encrypt backups before storing off-site.": "Χρησιμοποιήστε εξωτερικά εργαλεία (GPG, 7-Zip) για κρυπτογραφία αντιγράφων ασφαλείας.",
    "User authentication": "Ταυτοποίηση Χρήστη",
    "User must have Menu Options permission": "Ο χρήστης πρέπει να έχει άδεια Menu Options",
    "What are Orphaned Files?": "Τι είναι τα Ορφανά Αρχεία;",
    "You will be logged out and redirected to the login page.": "Θα αποσυνδεθείτε και θα ανακατευθυνθείτε στη σελίδα σύνδεσης.",
    "Your ChurchCRM installation is clean. All files on the server match the official release.": "Η εγκατάστασή σας ChurchCRM είναι καθαρή. Όλα τα αρχεία στον διακομιστή ταιριάζουν με την επίσημη έκδοση.",
    "Unmet Prerequisites": "Ανικανοποίητες Προϋποθέσεις",
    "Update Church Name": "Ενημέρωση Ονόματος Εκκλησίας",
    "Use classification and family filters to generate reports for specific groups of donors.": "Χρησιμοποιήστε φίλτρα ταξινόμησης και οικογένειας για δημιουργία αναφορών για συγκεκριμένες ομάδες δοτών.",
    "User accounts and roles": "Λογαριασμοί χρήστη και ρόλοι",
    "User must be an Admin or have Finance permission": "Ο χρήστης πρέπει να είναι Admin ή να έχει άδεια Finance",
    "Your fiscal year starts in month": "Το οικονομικό σας έτος ξεκινά σε μήνα",
    "Unable to detect system locales": "Δεν είναι δυνατή η ανίχνευση τοπικών ρυθμίσεων συστήματος",
    "Unable to determine available locales": "Δεν είναι δυνατή η καθορισμός διαθέσιμων τοπικών ρυθμίσεων",
    "Unable to load locale information": "Δεν είναι δυνατή η φόρτωση πληροφοριών τοπικής ρύθμισης",
    "Unable to load state list. Please check your network connection or try again later.": "Δεν είναι δυνατή η φόρτωση της λίστας κατάστασης. Παρακαλώ ελέγξτε τη σύνδεσή σας στο δίκτυο.",
    "Update it to a valid URL that:": "Ενημερώστε το σε ένα έγκυρο URL που:",
    "View Logs": "Προβολή Σημειωμάτων",
    "View and manage system log files for debugging.": "Προβολή και διαχείριση αρχείων καταγραφής συστήματος για αποσφαλμάτωση.",
    "You must enter a Last Name.": "Πρέπει να εισαγάγετε ένα Επίθετο.",
    "Your privacy is important. We never share your information with third parties.": "Η ιδιωτικότητά σας είναι σημαντική. Δεν κοινοποιούμε ποτέ τις πληροφορίες σας σε τρίτα μέρη.",
    "Visit Our Website": "Επισκεφθείτε τον Ιστότοπό μας",
    "Your verification request has been received. Thank you for keeping your information up to date.": "Η αίτημά σας για επαλήθευση έχει λάβει. Ευχαριστούμε που διατηρείτε τις πληροφορίες σας ενημερωμένες.",
}

# Apply
af_file = 'locale/missing-terms/af.json'
el_file = 'locale/missing-terms/el.json'

with open(af_file, 'r', encoding='utf-8') as f:
    af_data = json.load(f)

af_updated = 0
for key, translation in af_final.items():
    if key in af_data and af_data[key] == "":
        af_data[key] = translation
        af_updated += 1

with open(af_file, 'w', encoding='utf-8') as f:
    json.dump(af_data, f, ensure_ascii=False, indent=2)

with open(el_file, 'r', encoding='utf-8') as f:
    el_data = json.load(f)

el_updated = 0
for key, translation in el_final.items():
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
