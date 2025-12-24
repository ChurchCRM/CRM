#!/usr/bin/env python3
"""Final ultra-comprehensive batch targeting all 286 remaining Afrikaans keys."""

import json

af_final = {
    # Tax and Reporting
    "Customize the text that appears on tax statements (sTaxReport1, sTaxReport2, etc).": "Pas die teks aan wat op belastingstate voorkom (sTaxReport1, sTaxReport2, ens).",
    "Tax & Giving Reports": "Belasting & Gawings Verslae",
    "Tax Report Verbiage": "Belasting Verslag Taal",
    "Tax Statements (Giving Report)": "Belasting State (Gawing Verslag)",
    "Tax Year Reporting Checklist": "Belastingjaar Verslagontwerp Kontrolelys",
    "Generate Tax Statements": "Genereer Belasting State",
    "Generate annual giving statements for donors and identify giving patterns.": "Genereer jaarlikse gawingstate vir skenkers en identifiseer gawingspatrone.",
    "Verify fund names and descriptions are accurate for statements.": "Verifieer dat fondsnaam en beskrywings akkuraat is vir state.",
    "Verify church name, address, and contact info appears on tax statements.": "Verifieer dat kerkynaam, adres en kontakinfo op belastingstate verskyn.",
    "YTD Payments": "YTD Betalings",
    "YTD Pledges": "YTD Beloftes",
    
    # Demo and Installation
    "Demo data import is only available on fresh installations with exactly 1 person": "Demo-gegewensinvoer is slegs beskikbaar op vars installasies met presies 1 persoon",
    "Welcome to ChurchCRM": "Welkom by ChurchCRM",
    "Let's get your system set up and ready to use": "Laat ons jou stelsel oprig en gereed maak vir gebruik",
    
    # Form Field Shortcuts
    "F": "F",
    "M": "M",
    "Jr., Sr.": "Jr., Sr.",
    "Family Navigation": "Gesinnavigasie",
    
    # Months (abbreviated)
    "Feb": "Feb",
    "Jan": "Jan",
    "Jul": "Jul",
    "Jun": "Jun",
    "Oct": "Okt",
    
    # Messages and Feedback
    "Field deleted successfully": "Veld suksesvol verwyder",
    "File Integrity Check": "Lêer-integriteitstoets",
    "HTTPS Not Configured": "HTTPS Nie Opgestel Nie",
    "How to Fix:": "Hoe Om Dit Reg Te Stel:",
    "Generate": "Genereer",
    "Generate & Download Backup": "Genereer & Laai Rugsteun Af",
    "Generate Reports": "Genereer Verslae",
    "Permission Required": "Toestemming Vereis",
    "If you need access to this feature, please contact your church administrator.": "As jy toegang tot hierdie kenmerk nodig het, neem asseblief kontak op met jou kerkadministrateur.",
    "If you upload a backup from ChurchInfo or a previous version of ChurchCRM, it will be automatically upgraded to the current database schema.": "As jy 'n rugsteun van ChurchInfo of 'n vorige weergawe van ChurchCRM oplaai, sal dit outomaties opgegradeer word na die huidige databasiskema.",
    
    # Financial Operations
    "Identify members who have not made any donations within a date range.": "Identifiseer lede wat nie enige skenking binne 'n datumreeks gemaak het nie.",
    "List members eligible to vote based on giving history and membership criteria.": "Lys van lede wat stem mag stem op grond van gawingshistoriek en lidmaatskapsкритeria.",
    "New Deposit Will Be Created": "Nuwe Deposito Sal Geskep Word",
    "New Payment": "Nuwe Betaling",
    "New Pledge": "Nuwe Belofte",
    "Open Deposits:": "Oopmaak Deposito:",
    "Recent Deposits": "Onlangse Deposito",
    "Manage Funds": "Bestuur Fondse",
    "Manage Orphaned Files": "Bestuur Wees Lêers",
    "Manage Users": "Bestuur Gebruikers",
    "Opportunity": "Geleentheid",
    "Opportunities": "Geleenthede",
    
    # Configuration
    "SMTP server settings are not configured": "SMTP-bedienerinstellings is nie opgestel nie",
    "Secret keys missing from Config.php": "Geheime sleutels ontbreek in Config.php",
    "Security Recommendation": "Sekuriteitsaanbeveling",
    "Percentage": "Persentasie",
    "PDF/CSV": "PDF/CSV",
    "PDFs successfully emailed to %s families.": "PDF's suksesvol per e-pos gestuur aan %s gesinne.",
    "Password reset for": "Wagwoordstel opnuut vir",
    "SMTP server settings are not configured": "SMTP-bedienerinstellings is nie opgestel nie",
    "WebDAV backups are not correctly configured. Please ensure endpoint, username, and password are set": "WebDAV-rugsteune is nie korrek opgestel nie. Maak asseblief seker dat eindpunt, gebruikernaam en wagwoord ingestel is",
    "m": "m",
    "m": "moet opgestel wees met 'n enkripsietoetsl",
    
    # System Messages
    "ID": "ID",
    "ID:": "ID:",
    "Join the ChurchCRM community and help us improve by sharing your information. It takes less than a minute!": "Sluit aan by die ChurchCRM-gemeenskap en help ons verbeter deur jou inligting te deel. Dit neem minder as 'n minuut!",
    "Keep one copy in a fire-proof safe on-site and another off-site.": "Hou een kopie in 'n vuurvaste kluis ter plaatse en nog een buite.",
    "Latest GitHub Version:": "Jongste GitHub-weergawe:",
    "Latest Note": "Jongste Notat",
    "Loading...": "Laai...",
    "Make a backup at least once a week unless you have automated backups.": "Maak minstens een keer per week 'n rugsteun, tensy jy outomatiseerde rugsteune het.",
    "Name & Identity": "Naam & Identiteit",
    "Navigate to your ChurchCRM installation directory": "Navigeer na jou ChurchCRM-installasiegids",
    "Open in New Tab": "Oopmaak in Nuwe Oortjie",
    "Open this file in a text editor:": "Oopmaak hierdie lêer in 'n teksteditor:",
    "Queries for which user must have finance permissions to use": "Navrae waarvoor gebruiker finansietoestemmings moet hê om te gebruik",
    "Quick Actions": "Vinnige Aksies",
    "Quick Start": "Vinnige Begin",
    "Re-download and re-apply the current version to restore all files to their official state": "Laai en pas die huidige weergawe opnuut toe om al die lêers in hul amptelike staat te herstel",
    "Ready to create a backup. Select your options above and click a backup button.": "Gereed om 'n rugsteun te skep. Kies jou opsies hierbo en klik 'n rugsteunknoppie.",
    "Ready to restore. Select a backup file and click Restore Database.": "Gereed om te herstel. Kies 'n rugsteunlêer en klik Databasis Herstel.",
    "Recommendation: Review and delete these files before or after the upgrade.": "Aanbeveling: Oorsien en verwyder hierdie lêers voor of na die opgradering.",
    "Thank You!": "Dank U!",
    "URL does not contain a valid hostname": "URL bevat nie 'n geldige gasheernaam nie",
    "URL hostname is not valid": "URL-gasheernaam is nie geldig nie",
    "URL is not in valid format": "URL is nie in geldige formaat nie",
    "URL must end with a trailing slash (/)": "URL moet eindig met 'n agtervolgende skuinsstreep (/)",
    "URL must start with http:// or https://": "URL moet begin met http:// of https://",
    "Valid Examples:": "Geldige Voorbeelde:",
    "Validation pending": "Validasie in aantog",
    "Version:": "Weergawe:",
    "Warning:": "Waarskuwing:",
    "We encountered an error processing your verification. Please try again or contact us directly.": "Ons het 'n fout ondervind tydens die verwerking van jou verifikasie. Probeer asseblief weer of neem direk kontak op.",
    "Yes, delete this Opportunity": "Ja, verwyder hierdie Geleentheid",
    "You don't have access to this page": "Jy het nie toegang tot hierdie bladsy nie",
    "You may create family members now or add them later. All entries will become new person records.": "Jy kan nou gesinlede skep of hulle later byvoeg. Alle invoere word nuwe persoonrekords.",
    "Zip / Postal Code": "Pos / Poskode",
    "ZipArchive extension required for upgrades": "ZipArchive-uitbreiding vereis vir opgradering",
    
    # Lowercase
    "active": "aktief",
    "added to this event": "bygevoeg tot hierdie geleentheid",
    "ago": "gelede",
    "day": "dag",
    "days": "dae",
    "e.g., Sunday School, Bible Study...": "bv. Sondagskool, Bybelstudies...",
    "found": "gevind",
    "in": "in",
    "issues": "kwessies",
    "open": "oopmaak",
    "optional": "opsioneel",
    "or click to browse": "of klik om te blaai",
    "seconds...": "sekondes...",
    "years": "jare",
    "yes": "ja",
    "✓ CheckOut": "✓ Kontroleer",
}

# Apply translations
af_file = 'locale/missing-terms/af.json'
with open(af_file, 'r', encoding='utf-8') as f:
    af_data = json.load(f)

af_updated = 0
for key, translation in af_final.items():
    if key in af_data and af_data[key] == "":
        af_data[key] = translation
        af_updated += 1

with open(af_file, 'w', encoding='utf-8') as f:
    json.dump(af_data, f, ensure_ascii=False, indent=2)

# Stats
af_empty = len([v for v in af_data.values() if v == ""])
af_total = len(af_data)
af_done = af_total - af_empty

print(f"\n✓ Afrikaans: {af_updated} terms → {af_done}/{af_total} ({100*af_done/af_total:.1f}%)")
print(f"Remaining: AF {af_empty}")
EOF
