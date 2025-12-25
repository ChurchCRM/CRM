#!/usr/bin/env python3
"""Comprehensive Greek push - Target 50%+ (430+/863)"""

import json

el_batch = {
    # Database & System
    "Database": "Βάση Δεδομένων",
    "Database Error": "Σφάλμα Βάσης Δεδομένων",
    "Database is corrupted": "Η βάση δεδομένων είναι κατεστραμμένη",
    "Database migration": "Μεταφορά βάσης δεδομένων",
    "Database restoration": "Αποκατάσταση βάσης δεδομένων",
    "Database schema": "Σχήμα βάσης δεδομένων",
    "Database table": "Πίνακας βάσης δεδομένων",
    "Database upgrade": "Αναβάθμιση βάσης δεδομένων",
    "File system": "Σύστημα αρχείων",
    "File encoding": "Κωδικοποίηση αρχείου",
    "File format": "Μορφή αρχείου",
    "File size": "Μέγεθος αρχείου",
    "File type": "Τύπος αρχείου",
    "System backup": "Σύστημα αντιγράφου ασφαλείας",
    "System configuration": "Διαμόρφωση συστήματος",
    "System error": "Σφάλμα συστήματος",
    "System file": "Αρχείο συστήματος",
    "System performance": "Απόδοση συστήματος",
    "System requirements": "Απαιτήσεις συστήματος",
    "System update": "Ενημέρωση συστήματος",
    
    # Security & Access
    "Security": "Ασφάλεια",
    "Security check": "Έλεγχος ασφάλειας",
    "Security key": "Κλειδί ασφάλειας",
    "Security issue": "Ζήτημα ασφάλειας",
    "Security policy": "Πολιτική ασφάλειας",
    "Security risk": "Κίνδυνος ασφάλειας",
    "Security warning": "Προειδοποίηση ασφάλειας",
    "Access control": "Έλεγχος πρόσβασης",
    "Access level": "Επίπεδο πρόσβασης",
    "Access permission": "Άδεια πρόσβασης",
    "Access token": "Διακριτικό πρόσβασης",
    "Encryption": "Κρυπτογραφία",
    "Encryption key": "Κλειδί κρυπτογραφίας",
    "Encryption method": "Μέθοδος κρυπτογραφίας",
    "Authentication": "Ταυτοποίηση",
    "Authentication method": "Μέθοδος ταυτοποίησης",
    "Two-factor auth": "Δύο παράγοντες ταυτοποίησης",
    
    # Financial
    "Payment": "Πληρωμή",
    "Payment method": "Μέθοδος πληρωμής",
    "Payment status": "Κατάσταση πληρωμής",
    "Pledge": "Υπόσχεση",
    "Pledge status": "Κατάσταση υπόσχεσης",
    "Pledge amount": "Ποσό υπόσχεσης",
    "Donation": "Δωρεά",
    "Donation amount": "Ποσό δωρεάς",
    "Donation fund": "Κεφάλαιο δωρεάς",
    "Donation receipt": "Απόδειξη δωρεάς",
    "Fund": "Κεφάλαιο",
    "Fund balance": "Υπόλοιπο κεφαλαίου",
    "Fund code": "Κωδικός κεφαλαίου",
    "Fund name": "Όνομα κεφαλαίου",
    "Fund type": "Τύπος κεφαλαίου",
    "Giving": "Δίνοντας",
    "Giving summary": "Περίληψη δίνοντας",
    "Giving history": "Ιστορικό δίνοντας",
    "Giving year": "Έτος δίνοντας",
    "Giving report": "Αναφορά δίνοντας",
    
    # Events & Attendance
    "Event": "Εκδήλωση",
    "Event date": "Ημερομηνία εκδήλωσης",
    "Event time": "Ώρα εκδήλωσης",
    "Event location": "Τοποθεσία εκδήλωσης",
    "Event description": "Περιγραφή εκδήλωσης",
    "Event type": "Τύπος εκδήλωσης",
    "Event attendance": "Παρουσία εκδήλωσης",
    "Event checkin": "Έλεγχος εκδήλωσης",
    "Attendance": "Παρουσία",
    "Attendance report": "Αναφορά παρουσίας",
    "Attendance status": "Κατάσταση παρουσίας",
    "Attendance rate": "Ποσοστό παρουσίας",
    "Present": "Παρόν",
    "Absent": "Απών",
    "Late": "Αργά",
    "Excused": "Δικαιολογημένο",
    "Unexcused": "Αδικαιολόγητο",
    
    # People & Family
    "Person": "Πρόσωπο",
    "Family": "Οικογένεια",
    "Family head": "Αρχηγός οικογένειας",
    "Family member": "Μέλος οικογένειας",
    "Family relation": "Σχέση οικογένειας",
    "Relationship": "Σχέση",
    "Spouse": "Σύζυγος",
    "Child": "Παιδί",
    "Parent": "Γονέας",
    "Sibling": "Αδελφός/Αδελφή",
    "Contact": "Επαφή",
    "Contact method": "Μέθοδος επαφής",
    "Contact info": "Πληροφορίες επαφής",
    "Phone": "Τηλέφωνο",
    "Phone number": "Αριθμός τηλεφώνου",
    "Email address": "Διεύθυνση email",
    "Address": "Διεύθυνση",
    "Address type": "Τύπος διεύθυνσης",
    "City": "Πόλη",
    "State": "Περιοχή",
    "Zip code": "Ταχυδρομικός κώδικας",
    
    # Group & Organization
    "Group": "Ομάδα",
    "Group name": "Όνομα ομάδας",
    "Group type": "Τύπος ομάδας",
    "Group member": "Μέλος ομάδας",
    "Group role": "Ρόλος ομάδας",
    "Group status": "Κατάσταση ομάδας",
    "Organization": "Οργάνωση",
    "Organization name": "Όνομα οργάνωσης",
    "Department": "Τμήμα",
    "Division": "Τμήμα",
    "Team": "Ομάδα",
    "Team member": "Μέλος ομάδας",
    "Team role": "Ρόλος ομάδας",
    "Committee": "Επιτροπή",
    "Committee member": "Μέλος επιτροπής",
    
    # User Management
    "Username": "Όνομα χρήστη",
    "Password": "Κωδικός πρόσβασης",
    "Password reset": "Επαναφορά κωδικού",
    "Password change": "Αλλαγή κωδικού",
    "User account": "Λογαριασμός χρήστη",
    "User profile": "Προφίλ χρήστη",
    "User preference": "Προτίμηση χρήστη",
    "User role": "Ρόλος χρήστη",
    "User permission": "Άδεια χρήστη",
    "User status": "Κατάσταση χρήστη",
    "Active user": "Ενεργός χρήστης",
    "Inactive user": "Ανενεργός χρήστης",
    "Admin user": "Διαχειριστής χρήστης",
    "Standard user": "Τυπικός χρήστης",
    
    # Reports & Analytics
    "Report": "Αναφορά",
    "Report type": "Τύπος αναφοράς",
    "Report format": "Μορφή αναφοράς",
    "Report date": "Ημερομηνία αναφοράς",
    "Report period": "Περίοδος αναφοράς",
    "Report summary": "Περίληψη αναφοράς",
    "Analytics": "Ανάλυση",
    "Analytics data": "Δεδομένα ανάλυσης",
    "Statistics": "Στατιστικά",
    "Statistics data": "Δεδομένα στατιστικών",
    "Chart": "Διάγραμμα",
    "Graph": "Γράφημα",
    "Dashboard": "Ταμπλό",
    "Dashboard widget": "Widget ταμπλό",
    "Metric": "Μετρική",
    "KPI": "KPI",
    "Performance metric": "Μετρική απόδοσης",
    
    # Notifications & Messages
    "Notification": "Ειδοποίηση",
    "Notification type": "Τύπος ειδοποίησης",
    "Notification setting": "Ρύθμιση ειδοποίησης",
    "Message": "Μήνυμα",
    "Message type": "Τύπος μηνύματος",
    "Message status": "Κατάσταση μηνύματος",
    "Alert": "Ειδοποίηση",
    "Alert type": "Τύπος ειδοποίησης",
    "Alert message": "Μήνυμα ειδοποίησης",
    "Email notification": "Ειδοποίηση email",
    "SMS notification": "Ειδοποίηση SMS",
    "Push notification": "Ειδοποίηση push",
    
    # Configuration & Settings
    "Setting": "Ρύθμιση",
    "Settings page": "Σελίδα ρυθμίσεων",
    "Configuration": "Διαμόρφωση",
    "Configuration file": "Αρχείο διαμόρφωσης",
    "Configuration option": "Επιλογή διαμόρφωσης",
    "Parameter": "Παράμετρος",
    "Parameter value": "Τιμή παραμέτρου",
    "Preference": "Προτίμηση",
    "User preference": "Προτίμηση χρήστη",
    "System preference": "Προτίμηση συστήματος",
    "Default setting": "Προεπιλογή ρύθμισης",
    "Custom setting": "Προσαρμοσμένη ρύθμιση",
    
    # Validation & Error Handling
    "Validation": "Επαλήθευση",
    "Validation error": "Σφάλμα επαλήθευσης",
    "Validation rule": "Κανόνας επαλήθευσης",
    "Validation message": "Μήνυμα επαλήθευσης",
    "Error": "Σφάλμα",
    "Error code": "Κωδικός σφάλματος",
    "Error message": "Μήνυμα σφάλματος",
    "Error handling": "Χειρισμός σφάλματος",
    "Warning": "Προειδοποίηση",
    "Warning message": "Μήνυμα προειδοποίησης",
    "Success": "Επιτυχία",
    "Success message": "Μήνυμα επιτυχίας",
    "Confirmation": "Επιβεβαίωση",
    "Confirmation message": "Μήνυμα επιβεβαίωσης",
}

# Apply to Greek file
el_file = 'locale/missing-terms/el.json'

with open(el_file, 'r', encoding='utf-8') as f:
    el_data = json.load(f)

el_updated = 0
for key, translation in el_batch.items():
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
