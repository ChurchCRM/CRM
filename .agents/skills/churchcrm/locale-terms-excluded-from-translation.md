# Terms to Exclude from Localization <!-- learned: 2026-04-01 -->

## Overview

Some terms should NEVER be localized because they are:
- **Universal abbreviations** (N/A, API, JSON, etc.)
- **Brand/product names** (ChurchCRM, Vonage, Mailchimp, etc.)
- **Email examples** (name@example.com, user@domain.com, etc.)
- **Technical acronyms** (SMS, SMTP, URL, UUID, etc.)
- **Proper nouns** (GitHub, Google Meet, Slack, etc.)
- **File formats** (JSON, CSV, PO, MO, HTML, XML, CSS, etc.)
- **Programming language names** (JavaScript, PHP, SQL, Python, etc.)

## Complete Exclusion List

```
N/A
name@example.com
(any @example.com variant)
SMS
SMTP
IMAP
POP3
HTTP
HTTPS
API
OAuth
OAuth2
REST
JSON
CSV
TSV
XML
HTML
CSS
JavaScript
TypeScript
PHP
Python
Ruby
Go
Rust
SQL
MySQL
PostgreSQL
MongoDB
Redis
UUID
UUID4
URN
URL
URI
RFC
ISO
UTC
GMT
GMT+1
GMT-5
... (etc for timezones)
PO (gettext format)
MO (gettext format)
POEditor
GitHub
GitLab
Slack
Zoom
Google Meet
Mailchimp
Vonage
ChurchCRM
WordPress
Doctrine
Propel
Slim
Bootstrap
Tabler
Biome
Node.js
npm
Composer
Docker
Kubernetes
AWS
Azure
GCP
GDPR
HIPAA
PCI-DSS
WCAG
AA (accessibility level)
AAA (accessibility level)
2FA
MFA
SAML
JWT
Bearer
```

## Why This Matters

### Before (Incorrect)
```json
{
  "N/A": "Nie van toepassing",
  "SMS Integration": "ኤስ ኤም ኤስ ውህደት",
  "ChurchCRM": "ቸርች ሲ አር ኤም",
  "name@example.com": "ስም@example.com"
}
```

### After (Correct)
```json
{
  "N/A": "N/A",
  "SMS Integration": "SMS Integration",
  "ChurchCRM": "ChurchCRM",
  "name@example.com": "name@example.com"
}
```

## Impact on Current Translations

The 38 locales translated on 2026-04-01 contain several instances of incorrectly localized terms:

- **Afrikaans (af)**: `"N/A": "Nie van toepassing"` ❌
- **Amharic (am)**: `"N/A": "ተገዳሊ አይደለም"`, `"SMS": "ኤስ ኤም ኤስ"` ❌
- **Arabic (ar)**: `"N/A": "غير متاح"`, `"SMS": "رسائل نصية"` ❌
- **All locales**: Various acronyms and brand names were localized ❌

## How to Fix (Future Approach)

### Option 1: Pre-filter Batch Files
Before translation, remove all excluded terms from batch files so agents never see them.

```bash
# Remove excluded terms from all batch files
node locale/scripts/locale-exclude-terms.js --exclude-file .agents/skills/churchcrm/locale-terms-excluded-from-translation.md
```

### Option 2: Post-fix Translations
After translation, revert excluded terms back to English:

```bash
# For each locale, find and revert excluded terms
node locale/scripts/locale-revert-excluded-terms.js --locale af --exclude-file .agents/skills/churchcrm/locale-terms-excluded-from-translation.md
```

### Option 3: Update Translation Guidelines
Include this list in agent prompts so they know what NOT to translate:

```
⛔ DO NOT TRANSLATE THESE TERMS:
- Abbreviations: N/A, API, JSON, CSV, etc.
- Brand names: ChurchCRM, Vonage, Mailchimp, GitHub, etc.
- Email examples: name@example.com, user@domain.com, etc.
- Technical acronyms: SMS, SMTP, IMAP, POP3, HTTP, HTTPS, etc.
- Programming languages: JavaScript, PHP, Python, SQL, etc.
- File formats: JSON, CSV, XML, HTML, CSS, PO, MO, etc.
- Proper nouns: Slack, Zoom, Google Meet, etc.

If a term contains one of these, LEAVE IT UNCHANGED.
```

## Implementation for Next Locale Release

When running `/locale-translate --all` for 7.2.0 or later:

1. **Exclude terms upfront** — remove from batch files before translation
2. **Update agent prompt** — include this list as "DO NOT TRANSLATE" section
3. **Post-translation validation** — scan results for incorrectly localized acronyms
4. **Revert if needed** — script to mass-fix excluded terms

## Recommended Next Steps

1. ✅ Document this list (done)
2. ⏳ Create `locale-exclude-terms.js` helper script
3. ⏳ Create `locale-revert-excluded-terms.js` helper script
4. ⏳ Update `/locale-translate` agent prompt with exclusion list
5. ⏳ Test on 7.2.0 translation run

For 7.1.0 release, consider whether to manually revert the ~100+ incorrectly localized terms or proceed to POEditor for human review (translators can catch and fix these).
