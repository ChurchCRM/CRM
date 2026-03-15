---
title: "i18n & Localization Best Practices"
intent: "Guidance for adding UI terms, workflows for locale builds, and term consolidation"
tags: ["i18n","localization","gettext","i18next"]
prereqs: []
complexity: "beginner"
---

# i18n & Localization Best Practices

Guidelines for multilingual support, term consolidation, and the locale rebuild workflow.

---

## Overview

ChurchCRM supports 45+ languages through gettext (PHP) and i18next (JavaScript). Proper localization reduces translator workload and improves consistency across languages.

**Key Principle:** Every translatable term added = 45+ translations needed (one per language). Consolidate compound terms to reduce this burden.

---

## Terminology & UI Conventions

### Canonical Terms

Use consistent, single-source-of-truth UI terms: **Define once, reuse everywhere.**

**Examples:**
- ✅ Use "Family Listing" everywhere (not "family list" or "Family List")
- ✅ Use "People" for all user-facing text (not "Persons")
- ✅ Use "Active / Inactive" (not "Enabled / Disabled" or "Deactivated")
- ✅ Use "Set Active" action (not "Activate" or "Enable")
- ✅ Use banner text: "This Family is Inactive"
- ✅ Use status note: "Marked the Family as Inactive"

### People vs Persons

**CRITICAL: Different everywhere else:**

| Context | Term | Example |
|---------|------|---------|
| **User-facing text** | `People` | "List All People", gettext('People') |
| **API routes** | `persons` | `/api/persons/`, DO NOT CHANGE |
| **Internal keys** | `Persons` | `$cartPayload['Persons']`, DO NOT CHANGE |
| **Database tables** | `person` | `person_per`, DO NOT CHANGE |

**Pattern:**
```php
// ✅ CORRECT - UI text uses "People"
<?= gettext('People') ?>
<?= i18next.t('People') ?>

// DB/API internal names use original
$cartPayload['Persons']  // Internal key, don't rename
$request->get('/api/persons/');  // Route, don't change
```

**When encountering:**
- Renaming `Persons` in internal APIs → Requires coordination with API clients
- Changing `People` UI term → Only translate, don't rename key
- All UI gettext/i18next entries → Can rename consolidated terms

### Family Life Cycle

Use **Active / Inactive** for consistent family status:

```php
// ✅ CORRECT - Family status
if ($family->isInactive()) {  // Method name
    echo gettext('Inactive');  // UI display
}

// Action labels
gettext('Set Active');
gettext('Set Inactive');

// Banners (appears at top of page)
echo gettext('This Family is Inactive');  // Or 'is Active'

// Status change notes
printf(
    gettext('Marked the Family as %s'),
    $isInactive ? gettext('Inactive') : gettext('Active')
);
```

**Avoid:**
- ❌ "Enabled / Disabled" (unclear in family context)
- ❌ "Deactivated" (use "Inactive")
- ❌ "Activate / Deactivate" (use "Set Active / Set Inactive")

### Date/Time Handling

```php
// ✅ CORRECT - Localized formatting
$formatter = new IntlDateFormatter(
    'en_US',  // Or getUserLocale()
    IntlDateFormatter::LONG,
    IntlDateFormatter::NONE
);
echo $formatter->format($timestamp);

// ❌ WRONG - Uses deprecated strftime
echo strftime('%B %d, %Y', $timestamp);  // Not localized
```

---

## i18next Load Order — Always Use $(document).ready() <!-- learned: 2026-03-07 -->

`i18next` is loaded by `Footer.php` at the **end** of the page. Any inline `<script>` block that calls `i18next.t()` before the footer runs will throw `ReferenceError: i18next is not defined`.

**Always wrap i18next calls in `$(document).ready()`:**

```javascript
// ✅ CORRECT — deferred until Footer.php has loaded i18next
$(document).ready(function() {
    window.CRM.settingsPanel.init({
        title: i18next.t('Map Settings'),
        // ...
    });
});

// ❌ WRONG — i18next not yet loaded at script parse time
window.CRM.settingsPanel.init({
    title: i18next.t('Map Settings'),  // ReferenceError!
});
```

This applies to all inline scripts in PHP templates that use `i18next.t()`. Webpack entry points are unaffected (they use `DOMContentLoaded`).

---

## Adding New UI Terms

### Workflow

**BEFORE wiring into code:**

1. **Add to `locale/messages.po`** with empty translations
2. **Run `npm run locale:build`** to extract and sync
3. **Commit the updated messages.po**
4. **Then** wire into PHP/JS (gettext/i18next)

### Step-by-Step Example

**Goal:** Add "Apply" button to form

**Step 1** - Add to messages.po:
```gettext
# locale/terms/messages.po

msgid "Apply"
msgstr ""
```

**Step 2** - Build locale files:
```bash
npm run locale:build   # Extracts strings to messages.po
npm run build          # Regenerates frontend .json files
```

**Step 3** - Commit messages.po:
```bash
git add locale/terms/messages.po
git commit -m "Add 'Apply' button term to localization"
```

**Step 4** - Wire into code:
```php
// In template
<button><?= gettext('Apply') ?></button>

// Or JavaScript
<button id="apply-btn"><?= gettext('Apply') ?></button>
<script>
document.getElementById('apply-btn').textContent = i18next.t('Apply');
</script>
```

---

## Term Consolidation Patterns

### Problem: Translation Explosion

Creating unique strings for similar UI elements multiplies translator workload:

```php
// ❌ WRONG - 16+ unique terms (one per entity type)
gettext('Add New Field')
gettext('Add New Fund')
gettext('Add New User')
gettext('Add New Group')
gettext('Add New Person')
// ... 16 more, 45 languages = 880+ translations!

// ❌ WRONG - 7+ unique terms (multiple confirmation dialogs)
gettext('Family Delete Confirmation')
gettext('Note Delete Confirmation')
gettext('Fund Delete Confirmation')
gettext('Group Delete Confirmation')
// ... more, 45 languages = 315+ translations!
```

### Solution: Component-Based Terms

Consolidate compound terms into reusable parts:

```php
// ✅ CORRECT - 1 action + entity names = fewer translations
gettext('Add New') . ' ' . gettext('Field')
gettext('Add New') . ' ' . gettext('Fund')
gettext('Add New') . ' ' . gettext('User')
// Result: 10 total strings, 45 languages = 450 translations (saves 430!)

// ✅ CORRECT - 1 pattern + type names
gettext('Delete Confirmation') . ': ' . gettext('Family')
gettext('Delete Confirmation') . ': ' . gettext('Note')
// Result: 9 total strings (5 entity types), saves 98 translations
```

### Pattern: "Add New" Button

```php
// ✅ CORRECT - Consolidated pattern
<input type="submit" 
       value="<?= gettext('Add New') . ' ' . gettext('Fund') ?>" />

<h3><?= gettext('Add New') . ' ' . gettext('Group') ?></h3>

<div class="card-header">
    <?= gettext('Add New') . ' ' . gettext('Field') ?>
</div>
```

### Pattern: Delete Confirmation

```php
// ✅ CORRECT - Consolidated deletion dialog
<?php
$entityType = 'Family';  // Dynamic
$pageTitle = gettext('Delete Confirmation') . ': ' . gettext($entityType);
?>

<h1><?= $pageTitle ?></h1>
<p><?= sprintf(gettext('Are you sure you want to delete this %s?'), gettext($entityType)) ?></p>
```

### Pattern: Status Messages

```php
// ❌ WRONG - 4 unique terms
gettext('Record updated successfully')
gettext('Record created successfully')
gettext('Record deleted successfully')
gettext('Record saved successfully')

// ✅ CORRECT - 2 terms reused
sprintf(
    gettext('%s %s successfully'),
    ucfirst($action),          // "Created", "Updated", "Deleted"
    gettext($entityType)       // "Person", "Family", "Family"
)
```

### Guidelines: When to Consolidate

| Situation | Action | Example |
|-----------|--------|---------|
| Compound appears 2+ times | **CONSOLIDATE** | "Add New X", "Add New Y" → consolidate |
| Unique, appears once | **Keep as-is** | "Welcome to ChurchCRM" → single term |
| Idiomatic phrase | **Keep as-is** | "Oops! Something went wrong" → can't split |
| Repeated action+type | **CONSOLIDATE** | "[Action] [Type]" patterns |
| Menu items (consistency) | **Case-by-case** | May keep "Add New Person" unified for UX |

### Consolidation Decision Tree

```
Is this term a compound "[Action] [Type]"?
├─ YES: Does the action appear 2+ times?
│  ├─ YES: CONSOLIDATE → action + type names separately
│  └─ NO: Keep as-is
├─ NO: Is this an idiomatic phrase?
│  ├─ YES: Keep as-is (can't split translation)
│  └─ NO: Is it unique to this context?
│     ├─ YES: Keep as-is
│     └─ NO: Check for similar existing terms, reuse if possible
```

---

## General Consolidation Principles

### Step 1: Identify Patterns

Look for compound terms with repeated elements:

```php
// Pattern 1: Repeated action
gettext('Add New Field')         // Action: "Add New"
gettext('Add New Fund')          // Action: "Add New" (repeated!)
gettext('Delete Field')          // Action: "Delete"
gettext('Delete Fund')           // Action: "Delete" (repeated!)

// Pattern 2: Type variations
gettext('Person')                // Type: "Person"
gettext('Family')                // Type: "Family" (reusable)
gettext('Fund')                  // Type: "Fund" (reusable)
```

### Step 2: Extract Components

Split compound terms into reusable parts:

```php
// BEFORE (12 unique terms)
gettext('Add New Field')
gettext('Add New Fund')
gettext('Add New User')
gettext('Add New Group')
gettext('Delete Field')
gettext('Delete Fund')
gettext('Delete User')
gettext('Delete Group')

// AFTER (7 unique terms)
gettext('Add New')               // Shared action
gettext('Delete')                // Shared action
gettext('Field')                 // Reused type
gettext('Fund')                  // Reused type
gettext('User')                  // Reused type
gettext('Group')                 // Reused type
```

### Step 3: Implement with Concatenation

Use string concatenation to combine components:

```php
// Component-based approach
$action = gettext('Add New');
$entityType = gettext('Fund');
$label = $action . ' ' . $entityType;  // Result: "Add New Fund"

// In templates
<button><?= gettext('Add New') . ' ' . gettext('Fund') ?></button>

// In PHP
echo sprintf('%s %s', gettext('Add New'), gettext('Fund'));
```

### Step 4: Test with Locale Rebuild

```bash
npm run locale:build   # See if consolidation works
npm run build          # Regenerate all assets
```

### Step 5: Measure Impact

```
Original:  12 unique terms × 45 languages = 540 translations
Consolid: 7 unique terms × 45 languages = 315 translations
SAVED: 225 translations (42% reduction!)
```

---

## Locale Rebuild Workflow

### When to Rebuild

**BEFORE EVERY COMMIT with new UI strings:**

```bash
# 1. You added new gettext() or i18next.t() strings
# 2. You modified existing gettext() keys
# 3. You removed old i18n terms
```

### Step-by-Step

```bash
# 1. Rebuild translations (extracts all strings)
npm run locale:build
# Generates: locale/terms/messages.po
# Updates: locale/locales/*.json files

# 2. Verify changes
git diff locale/terms/messages.po
# Should show new msgid entries your added

# 3. Rebuild front-end assets (uses .json files)
npm run build
# Regenerates: src/skin/v2/locale/*.js

# 4. Test locally
npm run test              # Verify UI text displays

# 5. Commit both files
git add locale/terms/messages.po
git add src/skin/v2/locale/
git commit -m "Add new localization terms"
```

### Locale Directory Structure

```
locale/
├── terms/
│   ├── messages.po              # Master translation file (editable)
│   └── ...                      # Locale-specific PO files
├── locales/
│   ├── en_US.json              # English (compiled)
│   ├── es_ES.json              # Spanish (compiled)
│   └── ...                      # 45+ language files
├── messages.json               # Frontend translation cache
└── scripts/
    ├── locale-build.js          # Extracts strings
    └── ...
```

### Common Mistakes

❌ **Forgot to commit `messages.po`**
```bash
# Forgetting this breaks translation build for next developer
git commit -m "Add new feature"   # ❌ Forgot locale/terms/messages.po
```

✅ **Always commit locale changes**
```bash
git add locale/terms/messages.po
git add src/skin/v2/locale/       # Include compiled files
git commit -m "Add new UI strings and rebuild locale"
```

---

## PHP Localization

### PHP Strings (gettext)

```php
// ✅ CORRECT - Wrap in gettext()
echo gettext('Welcome to ChurchCRM');

// ✅ CORRECT - With variables
printf(gettext('Hello, %s'), $firstName);

// ✅ CORRECT - Concatenation for consolidated terms
echo gettext('Add New') . ' ' . gettext('Fund');

// ❌ WRONG - No translation wrapper
echo 'Welcome to ChurchCRM';

// ❌ WRONG - Escaped strings
echo gettext('User\'s Name');  // Awkward
echo gettext("User's Name");   // Better

// ❌ WRONG - Dynamic content in gettext
echo gettext('Hello, ' . $name);  // $name won't translate
```

### Plural Forms

```php
// ✅ CORRECT - Use ngettext for proper pluralization
printf(
    ngettext('%d person', '%d people', $count),
    $count
);
// Result: "1 person" or "5 people" (translated per language rules)

// ❌ WRONG - Manual pluralization
echo $count > 1 ? gettext('people') : gettext('person');
// Missing translation for singular + plural logic
```

---

## JavaScript Localization

### JavaScript Strings (i18next)

```javascript
// ✅ CORRECT - Use i18next.t()
window.CRM.notify(i18next.t('Operation completed'), {type: 'success'});

// ✅ CORRECT - With interpolation
i18next.t('Hello, {{name}}', {name: firstName});

// ✅ CORRECT - Concatenation for consolidated terms
const label = i18next.t('Add New') + ' ' + i18next.t('Fund');

// ❌ WRONG - String literal (no translation)
window.CRM.notify('Operation completed');

// ❌ WRONG - Dynamic concatenation in i18next.t()
i18next.t('Hello, ' + name);  // name value won't translate
```

### Notifications

```javascript
// ✅ CORRECT - Use window.CRM.notify() with i18next.t()
window.CRM.notify(i18next.t('Settings saved'), {
    type: 'success',
    delay: 3000
});

// ❌ WRONG - Use alert()
alert('Settings saved');  // Not translatable, poor UX
```

---

## Pre-Commit i18n Checklist

Before committing:

- [ ] All new UI text wrapped with `gettext()` (PHP) or `i18next.t()` (JS)
- [ ] No hardcoded user-facing strings
- [ ] If strings added: Ran `npm run locale:build`
- [ ] If strings added: Ran `npm run build`
- [ ] Committed `locale/terms/messages.po`
- [ ] Committed `src/skin/v2/locale/` (generated files)
- [ ] Checked for existing similar terms (reuse instead of creating new)
- [ ] Used consolidation patterns for compound terms
- [ ] Verified UI displays correctly (test with `npm run test`)

---

## Common Issues & Solutions

### Issue: "Translation not showing after rebuild"

```bash
# 1. Verify string is in messages.po
grep "My New String" locale/terms/messages.po

# 2. Verify you ran both builds
npm run locale:build    # Extract strings
npm run build           # Regenerate frontend

# 3. Clear browser cache and hard refresh
# Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows/Linux)

# 4. Check console for i18next errors
# Open DevTools → Console tab
```

### Issue: "Plural form not translating correctly"

```php
// ❌ WRONG
echo $count > 1 ? gettext('people') : gettext('person');

// ✅ CORRECT
echo ngettext('person', 'people', $count);
// This uses language-specific plural rules from gettext
```

### Issue: "Consolidation broke the translation"

```php
// ✅ CORRECT - String concatenation
gettext('Add New') . ' ' . gettext('Fund')

// ✅ ALSO CORRECT - Use sprintf
sprintf(gettext('Add New %s'), gettext('Fund'))

// ❌ WRONG - Gettext inside sprintf
sprintf(gettext('Add New %s'), gettext('Fund'))
// Have to use: sprintf(gettext('Add New %s'), 'Fund')
```

---

## Translator Perspective

Consolidation reduces workload from 45+ languages:

```
❌ No consolidation:
- 880 strings × 45 languages = 39,600 translation segments!
- Translator spends weeks translating variations of "Add New"

✅ With consolidation:
- 315 strings × 45 languages = 14,175 translation segments
- Translator completes in less time
- More consistency across UI
```

---

## AI-Assisted Translation Instructions

When using AI models (ChatGPT, Claude, etc.) to translate ChurchCRM terms, use the following prompt template to ensure accurate, culturally appropriate translations for church volunteers.

### Translation Prompt Template

```
Role: You are an expert localization specialist with deep knowledge of [Target Language] 
and Christian church culture.

Context: You are translating a software platform used for Church Management (ChMS). 
This includes modules for:
- Member directories and people management
- Small groups and community organization
- Financial stewardship (tithes, offerings, and accounting)

Target Audience: The end-users are church volunteers. They are deeply committed to their 
faith but are generally non-technical. The tone should be welcoming, respectful, and 
communal, rather than corporate or clinical.

Instructions:

1. **Ecclesiastical Accuracy**: Use terms that feel natural in a [Target Language] 
   church setting. For example:
   - Use the local word for "Congregation," "Parish," or "Community" instead of "Customer Base"
   - Use worship-appropriate terminology (e.g., "Offering" vs. "Donation")
   - Reference titles that are recognized in your church culture (e.g., "Pastor," "Elder," "Deacon")

2. **Simplify Technical Terms**: Avoid "dev-speak." Use plain language that church 
   volunteers understand:
   - Instead of: "Execute Batch Transaction"
   - Use: "Post Contributions" or "Record Gifts"
   - Instead of: "Initialize Data Sync"
   - Use: "Update Information"
   - Instead of: "Validate Input Schema"
   - Use: "Check Information"

3. **Consistency**: Ensure key terms are translated consistently throughout:
   - **Giving** (not "Donations" mixed with "Offerings")
   - **Pledge** (not "Promise" or "Commitment" interchangeably)
   - **Member** (not "Person" or "Individual")
   - **Active / Inactive** (not "Enabled / Disabled")
   - **Set Active** (not "Activate" for family/person status)
   - **Contribution** (not "Payment" or "Transaction")

4. **Constraint**: Keep translations concise so they fit within software UI buttons, 
   headers, and labels. Aim for 1-3 words when possible; never exceed what appears 
   in the English version's character count.

Input Data: [INSERT YOUR TERMS OR JSON HERE]

Please provide translations that maintain the spiritual tone while remaining practical 
for volunteers managing church operations.
```

### Usage Example: Using Missing Terms Files

ChurchCRM maintains missing term files for each language in `locale/terms/missing/[LANGUAGE]/`.
These JSON files contain untranslated strings with empty values:

**File:** `locale/terms/missing/es-SV/es-SV-1.json`
```json
{
  "Add Link": "",
  "Allow Self-Signed Certificates": "",
  "Audiences": "",
  "Back to Dashboard": "",
  "Change Your Locale": "",
  "Configure MailChimp": "",
  "CRM Members Not Subscribed": "",
  "Enable Two-Factor Authentication": "",
  "Post Contributions": "",
  "Set Active": ""
}
```

**Workflow:**

1. **Extract missing terms** for your target language from `locale/terms/missing/[LANGUAGE]/`
2. **Use the translation prompt** (above) with these actual terms from ChurchCRM
3. **Fill in the translations** so the empty strings `""` become properly translated values
4. **Verify consistency** against ecclesiastical and UI principle guidelines

**Example Output (Spanish-El Salvador):**
```json
{
  "Add Link": "Agregar enlace",
  "Allow Self-Signed Certificates": "Permitir certificados auto-firmados",
  "Audiences": "Audiencias",
  "Back to Dashboard": "Volver al panel de control",
  "Change Your Locale": "Cambiar idioma",
  "Configure MailChimp": "Configurar MailChimp",
  "CRM Members Not Subscribed": "Miembros no suscritos",
  "Enable Two-Factor Authentication": "Habilitar autenticación de dos factores",
  "Post Contributions": "Registrar contribuciones",
  "Set Active": "Marcar como activo"
}
```

### Guidelines for Adjustment

When using this template with ChurchCRM missing terms files:

- **Locate missing terms**: Find your language in `locale/terms/missing/[LANGUAGE]/` 
  - Files are split into numbered chunks (e.g., `es-SV-1.json`, `es-SV-2.json`)
  - Empty strings `""` indicate untranslated terms that need your attention
- **Update [Target Language]** with the actual language code (es-SV, pt-BR, ja, etc.)
- **Adjust ecclesiastical examples** to match the target language's church culture
  - Catholic churches might use "Offering" differently than Protestant churches
  - Orthodox traditions have different spiritual terminology
  - Some cultures emphasize "Community" over "Congregation"
- **Fill missing JSON values** with proper translations—never copy English terms
- **Test translations locally** by running `npm run locale:build` and `npm run build`
- **Include context** for ambiguous terms in your translation notes

**Common Missing Terms in ChurchCRM:**
- "Add Link", "Configure [Plugin]", "Enable Two-Factor Authentication"
- "Post Contributions", "CRM Members Not Subscribed", "Audiences"
- "Back to Dashboard", "Set Active", "Allow Self-Signed Certificates"

Refer to existing translations in `locale/locales/[LANGUAGE].json` for consistency with already-translated terms.

---

## Related Skills

- [Git Workflow](./git-workflow.md) - Locale rebuild in pre-commit checklist
- [Security Best Practices](./security-best-practices.md) - Sanitization for localized content
- [PHP Best Practices](./php-best-practices.md) - gettext and internationalization

---

Last updated: March 1, 2026
