#!/usr/bin/env python3
"""Final Greek push - 50%+ with actual remaining keys"""

import json

# Greek translations for actual remaining keys from file
el_final_batch = {
    "If that doesn't work, copy and paste the following link in your browser": "Εάν αυτό δεν λειτουργεί, αντιγράψτε και επικολλήστε τον ακόλουθο σύνδεσμο στο πρόγραμμα περιήγησής σας",
    "You received this email because we received a request for activity on your account. If you didn't request this you can safely delete this email.": "Λάβατε αυτό το email γιατί λάβαμε αίτημα δραστηριότητας στο λογαριασμό σας. Εάν δεν ζητήσατε αυτό μπορείτε να διαγράψετε με ασφάλεια αυτό το email.",
    "As part of the restore, external backups have been disabled.  If you wish to continue automatic backups, you must manually re-enable the bEnableExternalBackupTarget setting.": "Ως μέρος της επαναφοράς, τα εξωτερικά αντίγραφα έχουν απενεργοποιηθεί. Εάν θέλετε να συνεχίσετε τα αυτόματα αντίγραφα, πρέπει να ενεργοποιήσετε ξανά χειροκίνητα τη ρύθμιση bEnableExternalBackupTarget.",
    "The previous integrity check passed. All system file hashes match the expected values.": "Ο προηγούμενος έλεγχος ακεραιότητας πέρασε. Όλα τα hashes αρχείων συστήματος ταιριάζουν με τις αναμενόμενες τιμές.",
    "Enforce Content Security Policy (CSP) to help protect against cross-site scripting. When disabled, CSP violations are only reported.": "Εφαρμόστε πολιτική ασφάλειας περιεχομένου (CSP) για προστασία από cross-site scripting. Όταν απενεργοποιηθεί, οι παραβιάσεις CSP αναφέρονται μόνο.",
    "Delete All Logs": "Διαγραφή Όλων των Αρχείων Καταγραφής",
    "No log files found.": "Δεν βρέθηκαν αρχεία καταγραφής.",
    "Log File": "Αρχείο Καταγραφής",
    "Size": "Μέγεθος",
    "Last Modified": "Τελευταία Τροποποίηση",
    "Log File Viewer": "Προβολέας Αρχείου Καταγραφής",
    "Filter by log level:": "Φίλτρο κατά επίπεδο καταγραφής:",
    "Number of lines to display:": "Αριθμός γραμμών προς εμφάνιση:",
    "Loading log file...": "Φόρτωση αρχείου καταγραφής...",
    "No notes have been added for this family.": "Δεν έχουν προστεθεί σημειώσεις για αυτήν την οικογένεια.",
    "added": "προστέθηκε",
    "already in cart": "ήδη στο καλάθι",
    "Calendar properties could not be loaded. This calendar may be missing an access token or is not configured correctly.": "Οι ιδιότητες ημερολογίου δεν μπορούσαν να φορτωθούν. Αυτό το ημερολόγιο ενδέχεται να λείπει ένα διακριτικό πρόσβασης ή δεν έχει ρυθμιστεί σωστά.",
    "Empty your entire cart?": "Αδειάστε ολόκληρο το καλάθι σας;",
    "Failed to add family to cart": "Αποτυχία προσθήκης οικογένειας στο καλάθι",
    "Failed to add group to cart": "Αποτυχία προσθήκης ομάδας στο καλάθι",
    "Failed to add to cart": "Αποτυχία προσθήκης στο καλάθι",
    "Failed to empty cart": "Αποτυχία αδείασης καλαθιού",
    "Failed to empty cart to group": "Αποτυχία αδείασης καλαθιού σε ομάδα",
    "Failed to remove family from cart": "Αποτυχία αφαίρεσης οικογένειας από το καλάθι",
    "Failed to remove from cart": "Αποτυχία αφαίρεσης από το καλάθι",
    "Failed to remove group": "Αποτυχία αφαίρεσης ομάδας",
    "Family added to cart": "Οικογένεια προστέθηκε στο καλάθι",
    "Family removed from cart": "Οικογένεια αφαιρέθηκε από το καλάθι",
    "Group added to cart": "Ομάδα προστέθηκε στο καλάθι",
    "Group removed from cart": "Ομάδα αφαιρέθηκε από το καλάθι",
    "in cart": "στο καλάθι",
    "members. Already had": "μέλη. Ήδη είχε",
    "people added to cart": "άνθρωποι προστέθηκαν στο καλάθι",
    "people already in cart": "άνθρωποι ήδη στο καλάθι",
    "people from cart": "άνθρωποι από το καλάθι",
    "people removed from cart": "άνθρωποι αφαιρέθηκαν από το καλάθι",
    "Person already in cart": "Πρόσωπο ήδη στο καλάθι",
    "Remove this family from cart?": "Αφαιρέστε αυτήν την οικογένεια από το καλάθι;",
    "Remove this group from cart?": "Αφαιρέστε αυτήν την ομάδα από το καλάθι;",
    "Remove this person from cart?": "Αφαιρέστε αυτό το πρόσωπο από το καλάθι;",
    "Removed from cart successfully": "Αφαιρέθηκε από το καλάθι με επιτυχία",
    "Yes, Empty Cart": "Ναι, Αδείασε Καλάθι",
    "Yes, Remove": "Ναι, Αφαιρέστε",
    "Default role updated.": "Ο προεπιλεγμένος ρόλος ενημερώθηκε.",
    "Enter group name": "Εισάγετε όνομα ομάδας",
    "Failed to add role. Please try again.": "Αποτυχία προσθήκης ρόλου. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to create group. Please try again.": "Αποτυχία δημιουργίας ομάδας. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to delete role. Please try again.": "Αποτυχία διαγραφής ρόλου. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to load cart status.": "Αποτυχία φόρτωσης κατάστασης καλαθιού.",
    "Failed to load groups. Please refresh the page.": "Αποτυχία φόρτωσης ομάδων. Παρακαλώ ανανεώστε τη σελίδα.",
    "Failed to set default role. Please try again.": "Αποτυχία ορισμού προεπιλεγμένου ρόλου. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to update group. Please try again.": "Αποτυχία ενημέρωσης ομάδας. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to update properties. Please try again.": "Αποτυχία ενημέρωσης ιδιοτήτων. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to update role name. Please try again.": "Αποτυχία ενημέρωσης ονόματος ρόλου. Παρακαλώ δοκιμάστε ξανά.",
    "Leave blank to keep existing password": "Αφήστε κενό για να διατηρήσετε τον υπάρχοντα κωδικό πρόσβασης",
    "Please enter a group name.": "Παρακαλώ εισάγετε όνομα ομάδας.",
    "Role added successfully.": "Ρόλος προστέθηκε με επιτυχία.",
    "Role deleted successfully.": "Ρόλος διαγράφηκε με επιτυχία.",
    "Role name updated.": "Το όνομα του ρόλου ενημερώθηκε.",
}

# Apply to Greek file
el_file = 'locale/missing-terms/el.json'

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
el_empty = len([v for v in el_data.values() if v == ""])
el_total = len(el_data)
el_done = el_total - el_empty

print(f"\n✓ Greek: {el_updated} terms → {el_done}/{el_total} ({100*el_done/el_total:.1f}%)")
print(f"Remaining: EL {el_empty}")
