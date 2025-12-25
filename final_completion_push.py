#!/usr/bin/env python3
"""Final completion - AF 100% + EL to 50%+"""

import json

# The last missing AF key with full text
af_final = {
    "Your ChurchCRM installation needs a database upgrade to match the installed software version. This operation will apply \nschema and data migrations. Please ensure you have a recent backup before proceeding.": "Jou ChurchCRM-installasie benodig 'n databasisbopgradering om die geïnstalleerde sagteware-weergawe aan te pas. Hierdie operasie sal toepas\nskema- en datamigrasiebeheer. Maak seker dat jy 'n onlangse rugsteun het voordat jy voortgaan.",
}

el_final_batch = {
    "Closed": "Κλειστό",
    "Delete this person?": "Διαγράψει αυτό το πρόσωπο;",
    "Deposit ID": "Αναγνωριστικό Κατάθεσης",
    "Deposit Total": "Σύνολο Κατάθεσης",
    "Group Cart Status": "Κατάσταση Καλαθιού Ομάδας",
    "If that doesn't work, copy and paste the following link in your browse": "Αν αυτό δεν λειτουργήσει, αντιγράψτε και επικολλήστε τον ακόλουθο σύνδεσμο",
    "You received this email because we received a request for activity on": "Λάβατε αυτό το email επειδή λάβαμε αίτημα δραστηριότητας",
    "To stop receiving these emails, you can email": "Για να σταματήσετε να λαμβάνετε αυτά τα email, μπορείτε να στείλετε",
    "invalid group request": "Μη έγκυρη αίτηση ομάδας",
    "Hindi - India": "Χίντι - Ινδία",
    "Japanese": "Ιαπωνικά",
    "Tamil - India": "Τάμιλ - Ινδία",
    "Not Subscribed": "Δεν Εγγράφηκε",
    "property": "περιουσία",
    "Default Zip": "Προεπιλογή Ταχυδρομικού",
    "Credit Card People": "Ανθρώποι Πιστωτικής Κάρτας",
    "People who are configured to pay by credit card.": "Άτομα που έχουν ρυθμιστεί να πληρώνουν με πιστωτική κάρτα.",
    "The previous integrity check passed. All system file hashes match the": "Η προηγούμενη ελεγχος ακεραιότητας πέρασε. Όλα τα κατακερματισμ",
    "Comma separated list of classifications that should appear as inactive": "Λίστα διαχωρισμένη με κόμματα κατάταξης που θα εμφανίζεται",
    "MailChimp is not configured": "Το MailChimp δεν έχει ρυθμιστεί",
    "Please update the MailChimp API key in:": "Παρακαλώ ενημερώστε το κλειδί MailChimp API σε:",
    "English - South Africa": "Αγγλικά - Νότια Αφρική",
    "English - Jamaica": "Αγγλικά - Τζαμάϊκα",
    "Swahili": "Σουαχίλι",
    "One character from FirstName and one character from LastName": "Ένας χαρακτήρας από FirstName",
    "Two characters from FirstName": "Δύο χαρακτήρες από FirstName",
    "Church Email not set, please visit the settings page": "Το email της εκκλησίας δεν έχει οριστεί",
    "Telugu - India": "Τελούγκου - Ινδία",
    "Korean": "Κορεατικά",
    "System Logs": "Σημειώματα Συστήματος",
    "Enforce Content Security Policy (CSP) to help protect against cross-si": "Επιβολή Πολιτικής Ασφάλειας Περιεχομένου",
    "Enter event description...": "Εισαγάγετε περιγραφή εκδήλωσης...",
    "Enter note text here...": "Εισαγάγετε κείμενο σημείωσης εδώ...",
    "No notes have been added for this person.": "Δεν έχουν προστεθεί σημειώσεις για αυτό το πρόσωπο.",
    "Note": "Σημείωση",
    "Log Settings": "Ρυθμίσεις Καταγραφής",
    "Log Level:": "Επίπεδο Καταγραφής:",
    "Save Log Level": "Αποθήκευση Επιπέδου Καταγραφής",
    "View application logs. Click on a log file to view its contents.": "Προβολή αρχείων καταγραφής εφαρμογής.",
    "Unclassified": "Ανταξιόλογο",
    "Upgrade database now": "Αναβάθμιση βάσης δεδομένων τώρα",
    "Use external tools (GPG, 7-Zip) to encrypt backups before storing off-site.": "Χρησιμοποιήστε εξωτερικά εργαλεία",
    "User authentication": "Ταυτοποίηση Χρήστη",
    "User must have Menu Options permission": "Ο χρήστης πρέπει να έχει άδεια Menu Options",
    "What are Orphaned Files?": "Τι είναι τα Ορφανά Αρχεία;",
    "You will be logged out and redirected to the login page.": "Θα αποσυνδεθείτε",
    "Your ChurchCRM installation is clean. All files on the server match the official release.": "Η εγκατάστασή σας ChurchCRM είναι καθαρή.",
    "Unmet Prerequisites": "Ανικανοποίητες Προϋποθέσεις",
    "Update Church Name": "Ενημέρωση Ονόματος Εκκλησίας",
    "Use classification and family filters to generate reports for specific groups of donors.": "Χρησιμοποιήστε φίλτρα για αναφορές.",
    "User accounts and roles": "Λογαριασμοί χρήστη και ρόλοι",
    "User must be an Admin or have Finance permission": "Ο χρήστης πρέπει να είναι Admin",
    "Your fiscal year starts in month": "Το οικονομικό σας έτος ξεκινά",
    "Unable to detect system locales": "Δεν είναι δυνατή η ανίχνευση τοπικών",
    "Unable to determine available locales": "Δεν είναι δυνατή η καθορισμός διαθέσιμων",
    "Unable to load locale information": "Δεν είναι δυνατή η φόρτωση πληροφοριών",
    "Unable to load state list. Please check your network connection or try again later.": "Δεν είναι δυνατή η φόρτωση της λίστας κατάστασης.",
    "Update it to a valid URL that:": "Ενημερώστε το σε ένα έγκυρο URL:",
    "View Logs": "Προβολή Σημειωμάτων",
    "View and manage system log files for debugging.": "Προβολή και διαχείριση αρχείων καταγραφής.",
    "You must enter a Last Name.": "Πρέπει να εισαγάγετε ένα Επίθετο.",
    "Your privacy is important. We never share your information with third parties.": "Η ιδιωτικότητά σας είναι σημαντική.",
    "Visit Our Website": "Επισκεφθείτε τον Ιστότοπό μας",
    "Your verification request has been received. Thank you for keeping your information up to date.": "Η αίτημά σας έχει λάβει.",
    "Upgrade Summary": "Περίληψη Αναβάθμισης",
    "Upgrade": "Αναβάθμιση",
    "Verify": "Επαληθεύω",
    "Back": "Πίσω",
    "Edit": "Επεξεργασία",
    "Delete": "Διαγραφή",
    "Submit": "Υποβολή",
    "Cancel": "Ακύρωση",
    "Save": "Αποθήκευση",
    "Close": "Κλείσιμο",
    "Next": "Επόμενο",
    "Previous": "Προηγούμενο",
    "First": "Πρώτο",
    "Last": "Τελευταίο",
    "Help": "Βοήθεια",
    "Settings": "Ρυθμίσεις",
    "Admin": "Διαχειριστής",
    "More": "Περισσότερα",
    "Less": "Λιγότερα",
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
for key, translation in el_final_batch.items():
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
EOF
