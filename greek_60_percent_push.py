#!/usr/bin/env python3
"""Greek push to 60%+ with remaining key batch"""

import json

# Greek translations for next batch of remaining keys
el_batch_60 = {
    "View Group": "Προβολή Ομάδας",
    "Sunday School Overview": "Επισκόπηση Σχολής Κυριακής",
    "City is required": "Η πόλη είναι απαραίτητη",
    "Database name is required": "Το όνομα της βάσης δεδομένων είναι απαραίτητο",
    "Database server hostname or IP address is required (e.g., localhost or 127.0.0.1)": "Το όνομα διακομιστή βάσης δεδομένων ή η διεύθυνση IP είναι απαραίτητη (π.χ. localhost ή 127.0.0.1)",
    "Database server port is required (e.g., 3306)": "Η θύρα διακομιστή βάσης δεδομένων είναι απαραίτητη (π.χ. 3306)",
    "Database username is required": "Το όνομα χρήστη της βάσης δεδομένων είναι απαραίτητο",
    "Email Address": "Διεύθυνση Email",
    "Enter family name": "Εισάγετε όνομα οικογένειας",
    "Family Information": "Πληροφορίες Οικογένειας",
    "Family name is required": "Το όνομα της οικογένειας είναι απαραίτητο",
    "Family name must be at least 2 characters": "Το όνομα της οικογένειας πρέπει να έχει τουλάχιστον 2 χαρακτήρες",
    "First name is required": "Το όνομα είναι απαραίτητο",
    "First name must be at least 2 characters": "Το όνομα πρέπει να έχει τουλάχιστον 2 χαρακτήρες",
    "Home phone is required": "Το τηλέφωνο του σπιτιού είναι απαραίτητο",
    "Home phone number": "Αριθμός τηλεφώνου του σπιτιού",
    "Invalid format": "Μη έγκυρη μορφή",
    "Join our community by registering your family": "Συμμετέχετε στην κοινότητά μας εγγράφοντας την οικογένειά σας",
    "Last name is required": "Το επώνυμο είναι απαραίτητο",
    "Last name must be at least 2 characters": "Το επώνυμο πρέπει να έχει τουλάχιστον 2 χαρακτήρες",
    "Maximum length is": "Η μέγιστη διάρκεια είναι",
    "Minimum length is": "Η ελάχιστη διάρκεια είναι",
    "Must be a valid URL starting with http:// or https://": "Πρέπει να είναι έγκυρη διεύθυνση URL που ξεκινά με http:// ή https://",
    "Must be a valid URL starting with http:// or https:// (e.g., http://localhost or https://domain.com)": "Πρέπει να είναι έγκυρη διεύθυνση URL που ξεκινά με http:// ή https:// (π.χ. http://localhost ή https://domain.com)",
    "Must be a valid port number (e.g., 3306)": "Πρέπει να είναι έγκυρος αριθμός θύρας (π.χ. 3306)",
    "Next Member": "Επόμενο Μέλος",
    "Passwords do not match": "Οι κωδικοί πρόσβασης δεν ταιριάζουν",
    "Phone Type": "Τύπος Τηλεφώνου",
    "Please add at least one family member.": "Παρακαλώ προσθέστε τουλάχιστον ένα μέλος της οικογένειας.",
    "Please correct the validation errors before continuing.": "Παρακαλώ διορθώστε τα σφάλματα επικύρωσης πριν συνεχίσετε.",
    "Please enter a valid URL": "Παρακαλώ εισάγετε έγκυρη διεύθυνση URL",
    "Please enter a valid email address": "Παρακαλώ εισάγετε έγκυρη διεύθυνση email",
    "Please enter a valid email address (e.g., name@example.com)": "Παρακαλώ εισάγετε έγκυρη διεύθυνση email (π.χ. name@example.com)",
    "Please enter a valid number": "Παρακαλώ εισάγετε έγκυρο αριθμό",
    "Please enter a valid phone number": "Παρακαλώ εισάγετε έγκυρο αριθμό τηλεφώνου",
    "Please fill in all required fields correctly.": "Παρακαλώ συμπληρώστε όλα τα υποχρεωτικά πεδία σωστά.",
    "Please review all information carefully before submitting. You can go back to make changes if needed.": "Παρακαλώ ελέγξτε όλες τις πληροφορίες προσεκτικά πριν υποβάλετε. Μπορείτε να επιστρέψετε για να κάνετε αλλαγές εάν χρειάζεται.",
    "Review & Submit": "Ανασκόπηση & Υποβολή",
    "Role in Family": "Ρόλος στην Οικογένεια",
    "Select date": "Επιλέξτε ημερομηνία",
    "Submit Registration": "Υποβολή Εγγραφής",
    "Zip code is required": "Ο ταχυδρομικός κώδικας είναι απαραίτητος",
    "Complete": "Ολοκληρωμένο",
    "Continue to Backup": "Συνέχεια σε Αντίγραφο",
    "Continue to Download & Apply": "Συνέχεια σε Λήψη & Εφαρμογή",
    "Create Backup": "Δημιουργία Αντιγράφου",
    "Create a backup of your database before applying the update.": "Δημιουργήστε ένα αντίγραφο της βάσης δεδομένων σας πριν εφαρμόσετε την ενημέρωση.",
    "Current Version:": "Τρέχουσα Έκδοση:",
    "Database Backup": "Αντίγραφο Βάσης Δεδομένων",
    "Download & Apply": "Λήψη & Εφαρμογή",
    "Download and Apply System Update": "Λήψη και Εφαρμογή Ενημέρωσης Συστήματος",
    "Download the latest release and apply it to your ChurchCRM installation.": "Λήψη της τελευταίας έκδοσης και εφαρμογή της στην εγκατάσταση ChurchCRM σας.",
    "Downloaded": "Λήφθηκε",
    "Downloading latest release from GitHub...": "Λήψη τελευταίας έκδοσης από GitHub...",
    "Enter your login name and we will email you a link to reset your password.": "Εισάγετε το όνομα σύνδεσής σας και θα σας στείλουμε ένα email με σύνδεσμο για επαναφορά του κωδικού πρόσβασης.",
    "Failed to create backup.": "Αποτυχία δημιουργίας αντιγράφου.",
    "Failed to download update package.": "Αποτυχία λήψης πακέτου ενημέρωσης.",
    "Failed to refresh upgrade information from GitHub.": "Αποτυχία ανανέωσης πληροφοριών αναβάθμισης από GitHub.",
    "Failed to refresh upgrade information. Please try again.": "Αποτυχία ανανέωσης πληροφοριών αναβάθμισης. Παρακαλώ δοκιμάστε ξανά.",
    "Failed to save setting. Please try again.": "Αποτυχία αποθήκευσης ρύθμισης. Παρακαλώ δοκιμάστε ξανά.",
    "File Name": "Όνομα Αρχείου",
    "Files Missing": "Αρχεία που λείπουν",
    "Files Modified": "Αρχεία Τροποποιημένα",
    "Go to Login": "Μεταβείτε σε Σύνδεση",
    "I Understand - Continue": "Καταλαβαίνω - Συνέχεια",
    "Language updated to": "Γλώσσα ενημερώθηκε σε",
    "Login Name is required": "Το όνομα σύνδεσης είναι απαραίτητο",
    "Once you receive the email, you can log in with your temporary password and change it to something you prefer.": "Αφού λάβετε το email, μπορείτε να συνδεθείτε με τον προσωρινό κωδικό πρόσβασης και να τον αλλάξετε σε κάτι που προτιμάτε.",
    "Password Reset Successful": "Η Επαναφορά Κωδικού Πρόσβασης Ήταν Επιτυχής",
    "Please check your email (including spam/junk folder) for your temporary password.": "Παρακαλώ ελέγξτε το email σας (συμπεριλαμβανομένου του φακέλου spam/junk) για τον προσωρινό κωδικό πρόσβασης.",
}

# Apply to Greek file
el_file = 'locale/missing-terms/el.json'

with open(el_file, 'r', encoding='utf-8') as f:
    el_data = json.load(f)

el_updated = 0
for key, translation in el_batch_60.items():
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
