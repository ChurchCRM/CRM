#!/usr/bin/env python3
"""Final mega push - Get AF to 90%+ and EL to 25%+"""

import json

af_final = {
    "File Integrity Issues Detected": "Lêer-integriteitsuitgawes Opgespoor",
    "File Integrity:": "Lêer-integriteit:",
    "File Path": "Lêer Pad",
    "File signatures match the official release.": "Lêertekens stem ooreen met die amptelike vrylating.",
    "Files may be modified or missing. Consider re-deploying from an official release.": "Lêers mag gewysigd of ontbreek wees. Oorweeg om opnuut te plaas vanaf 'n amptelike vrylating.",
    "Finance Dashboard": "Finansiële Paneelbord",
    "Finance access": "Finansiële toegang",
    "Find the line:": "Soek die lyn:",
    "Force Import": "Forseer Invoer",
    "Force Import will retry and may create duplicate data.": "Forseer Invoer sal herhaal en mag dupliseer data skep.",
    "Force Re-install": "Forseer Herinstallasie",
    "Force re-install initiated. Please backup your database before applying.": "Forseer herinstallasie inisieer. Rugsteun asseblief jou databasis voor toepassing.",
    "Full Backup": "Volle Rugsteun",
    "Fund Allocation": "Fonds Toekenning",
    "Fund(s)": "Fonds(se)",
    "Generate annual tax-deductible giving statements for donors. Can be printed or emailed.": "Genereer jaarlikse belastingaftrekbare gawingstate vir skenkers. Kan gedruk of per e-pos gestuur word.",
    "Generate detailed reports for individual deposits or date ranges.": "Genereer gedetailleerde verslae vir individuele deposito of datumreekse.",
    "Generate reminder letters for families with outstanding pledges.": "Genereer herinnringsbriefs vir gesinne met uitstaande beloftes.",
    "Generate reports for tax statements, pledge tracking, and financial analysis.": "Genereer verslae vir belastingstukke, belofte-sporing en finansiële analise.",
    "Giving funds and categories": "Gawingsfondse en kategorieë",
    "Support for both": "Ondersteuning vir albei",
    "System Update Version": "Stelsel-opdateringsweergawe",
    "Thank you for helping us!": "Dankie dat jy ons help!",
    "The application is running from the location": "Die toepassing loop vanaf die ligging",
    "The latest version": "Die jongste weergawe",
    "The system is being updated...": "Die stelsel word opgedateer...",
    "There are no groups defined yet.": "Daar is nog geen groepe gedefinieer nie.",
    "This is a critical upgrade that may take a few minutes to complete.": "Dit is 'n kritieke opgradering wat 'n paar minute kan neem.",
    "This may result in data loss. Ensure all users are logged out before forcing a re-install.": "Dit kan dataverlies tot gevolg hê. Maak seker dat alle gebruikers afgemel is voordat jy 'n herinstallasie forseer.",
    "Total Amount": "Totale Bedrag",
    "Total Records": "Totale Rekorde",
    "Two Factor Authentication": "Twee-faktor-verifikasie",
    "Two Factor Disabled": "Twee-faktor Onaktiveer",
    "Two Factor Enabled": "Twee-faktor Geaktiveer",
    "Type": "Tipe",
    "Types": "Tipes",
    "Unable to check for updates": "Kan nie vir opgradering kontroleer nie",
    "Unable to check for upgrades": "Kan nie vir opgradering kontroleer nie",
    "Unable to connect to GitHub": "Kan nie aan GitHub koppel nie",
    "Unable to connect to upgrade server": "Kan nie aan opgraderingsbediener koppel nie",
    "Unable to create backup": "Kan nie rugsteun skep nie",
    "Unable to download update": "Kan nie opgradering aflaai nie",
    "Unable to get latest version": "Kan nie jongste weergawe kry nie",
    "Unable to restore database": "Kan nie databasis herstel nie",
    "Unable to run upgrade": "Kan nie opgradering hardloop nie",
    "Uninstall Plugin": "Deïnstalleer Inprop",
    "Unique constraint violated": "Unieke beperking geskend",
    "Unique or Primary Key": "Unieke of Primêre Sleutel",
    "Unit": "Eenheid",
    "Units": "Eenhede",
    "Unlocked": "Ontsluit",
    "Unknown error": "Onbekende fout",
    "Unnamed": "Naamloos",
    "Unofficial Release": "Onoffisiële Vrylating",
    "Unverified": "Onverifieerd",
    "Update": "Opdatering",
    "Update Available": "Opdatering Beskikbaar",
    "Update Successful": "Opdatering Suksesvol",
    "Updates Available": "Opgraderingge Beskikbaar",
    "Updating": "Opdatering",
    "Upgrade": "Opgradering",
    "Upgrade Assistant": "Opgradering Assistent",
    "Upgrade canceled": "Opgradering Gekanselleer",
    "Upgrade check skipped": "Opgradering kontroleer oorgeslaan",
    "Upgrade Check": "Opgradering Kontroleer",
    "Upgrade complete": "Opgradering Voltooi",
    "Upgrade Database": "Opgradering Databasis",
    "Upgrade Database Wizard": "Opgradering Databasis Tooweraar",
    "Upgrade Details": "Opgradering Besonderhede",
    "Upgrade File Restore": "Opgradering Lêer Herstel",
    "Upgrade failed": "Opgradering Gefaal",
    "Upgrade Integrity Check": "Opgradering Integriteitstoets",
    "Upgrade is ready for manual installation.": "Opgradering is gereed vir handmatige installasie.",
    "Upgrade is running...": "Opgradering hardloop...",
    "Upgrade Options": "Opgradering Opsies",
    "Upgrade running. This may take a while...": "Opgradering hardloop. Dit kan 'n rukkie vat...",
    "Upgrade Wizard": "Opgradering Tooweraar",
    "Upgraded to version": "Opgegradeer na weergawe",
    "Upgrading Database": "Opgradering Databasis",
    "Upgrading Database Structure": "Opgradering Databasis Struktuur",
    "Upgrading your Church CRM": "Opgradering jou Kerk CRM",
    "Upload Photo": "Laai Foto Op",
    "Upload Video": "Laai Video Op",
    "User Guide": "Gebruikersgids",
    "User roles have different access restrictions": "Gebruikerrolle het verskillende toegangsbeperkings",
    "User Settings": "Gebruikerinstellings",
    "User Warnings Issued for Users": "Gebruiker Waarskuwings Uitgegee vir Gebruikers",
    "User(s)": "Gebruiker(s)",
    "Username Exists": "Gebruikernaam Bestaan",
    "Username is required": "Gebruikernaam Vereis",
    "Users": "Gebruikers",
    "Utility": "Gereedskap",
    "Value": "Waarde",
    "Values": "Waardes",
    "Verify All Files": "Verifieer Alle Lêers",
    "Verify System Files": "Verifieer Stelsellêers",
    "Version Control Update": "Weergawekontrole Opdatering",
    "Versions": "Weergawes",
    "Video": "Video",
    "Videos": "Video's",
    "View": "Bekyk",
    "Viewed": "Bekyke",
    "Viewing": "Bekyking",
    "Views": "Bekyke",
    "Volunteer": "Vrywilliger",
    "Volunteers": "Vrywilligers",
}

el_final = {
    "A ": "Ένα ",
    "ADD, Delete, and ordering changes are immediate. Name and Description changes must be saved by clicking 'Save Changes'.": "Το ADD, Delete και οι αλλαγές σειράς είναι άμεσες. Οι αλλαγές στο όνομα και την περιγραφή πρέπει να αποθηκευτούν κάνοντας κλικ στο 'Αποθήκευση αλλαγών'.",
    "Address is required": "Η διεύθυνση είναι απαραίτητη",
    "All Files Verified": "Όλα τα αρχεία επαληθεύθηκαν",
    "All Types": "Όλοι οι τύποι",
    "All information is correct": "Όλες οι πληροφορίες είναι σωστές",
    "All pre-upgrade checks have passed. You may proceed with the upgrade.": "Όλες οι προ-ενημέρωσης ελέγχους έχουν περάσει. Μπορείτε να προχωρήσετε με την ενημέρωση.",
    "All system file signatures match the expected values.": "Όλες οι υπογραφές αρχείων συστήματος ταιριάζουν με τις αναμενόμενες τιμές.",
    "All system files have passed integrity validation.": "Όλα τα αρχεία συστήματος έχουν περάσει τον έλεγχο ακεραιότητας.",
    "All system files match their expected signatures.": "Όλα τα αρχεία συστήματος ταιριάζουν με τις αναμενόμενες υπογραφές τους.",
    "Allow Pre-release Upgrades": "Επιτρέψτε Προ-κυκλοφορίες",
    "Amount exceeds maximum allowed value (999999.99).": "Το ποσό υπερβαίνει τη μέγιστη επιτρεπόμενη τιμή (999999.99).",
    "An error occurred during demo data import": "Παρουσιάστηκε σφάλμα κατά την εισαγωγή δεδομένων δείγματος",
    "An error occurred. Please contact your system administrator.": "Παρουσιάστηκε σφάλμα. Επικοινωνήστε με το διαχειριστή του συστήματός σας.",
    "An unexpected error occurred. Please contact your administrator.": "Παρουσιάστηκε απροσδόκητο σφάλμα. Επικοινωνήστε με το διαχειριστή σας.",
    "Application Integrity": "Ακεραιότητα Εφαρμογής",
    "Application files updated to latest version": "Τα αρχεία εφαρμογής ενημερώθηκαν στην τελευταία έκδοση",
    "Apply Update Now": "Εφαρμόστε Ενημέρωση Τώρα",
    "Applying System Update...": "Εφαρμογή Ενημέρωσης Συστήματος...",
    "Apr": "Απρ",
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
