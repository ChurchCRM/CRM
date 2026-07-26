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

> [!NOTE] Scope — CORE `messages` domain only
> Community plugins do **not** go through POeditor and do **not** contribute strings to
> `locale/messages.po` or `locale/i18n/*.json`. Plugin authors ship their
> translations inside the plugin directory and ChurchCRM loads them via
> `PluginLocalization`. See
> [`plugin-development.md → Plugin Localization`](./plugin-development.md#plugin-localization-independent-of-poeditor)
> for the plugin workflow. Anything below applies to the core `messages`
> textdomain; do not copy these patterns into a plugin.

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

### Capitalization Convention <!-- learned: 2026-04-22 -->

**Use Title Case for UI chrome, Sentence case for body text. Never have both forms of the same string in the codebase — translators see them as two different msgids and must translate twice.**

| Use Title Case for | Use Sentence case for |
|---|---|
| Form field labels: `First Name`, `Email Address`, `Phone Number` | Helper text: `Enter your first name` |
| Button labels: `Save`, `Sign In`, `Add Family` | Validation messages: `This field is required` |
| Column headers: `Member`, `Family`, `Status` | Helper/placeholder text |
| Page titles & card headers: `Email Configuration` | Body paragraphs, descriptions |
| Navigation/menu items: `Reports`, `Settings` | Toast notifications: `Saved successfully` |
| Tab labels: `App`, `Server`, `Database` | Modal body content |
| Status badges (single word): `Active`, `Inactive`, `Online` | Counts/aggregates: `5 members`, `3 families` |
| Dialog titles: `Confirm Delete`, `Error` | Status sentences: `1 family is inactive` |

**Survey result (2026-04-22):** Title Case dominates form labels in this codebase. Form labels with case duplicates were ALL won by Title Case (`First Name` 10× vs `First name` 1×, `Last Name` 11× vs 1×, `Phone Number` 5× vs 1×, etc.).

```php
// CORRECT — Title Case for labels, Sentence case for help text
<label><?= gettext('Email Address') ?></label>
<small class="text-muted"><?= gettext('We never share your email address.') ?></small>

// WRONG — inconsistent: same label, different casing creates 2 msgids
<label><?= gettext('Email Address') ?></label>     // page A
<label><?= gettext('Email address') ?></label>     // page B
```

**Detection:**
```bash
# Find case-only duplicate msgids
python3 -c "
import re
from collections import defaultdict
po = open('locale/messages.po').read()
ids = [m for m in re.findall(r'^msgid \"(.*?)\"\$', po, re.MULTILINE) if m]
g = defaultdict(set)
for m in ids: g[m.lower()].add(m)
for v in g.values():
    if len(v) > 1: print(sorted(v))
"
```

> [!WARNING] The case-dupe grep has a high false-positive rate — verify each hit's call sites before merging <!-- learned: 2026-07-24 -->
> A 2026-07-24 audit ran this script and got **72 case-duplicate groups** (`Active`/`active`,
> `People`/`people`, `Loading`/`loading`, `By`/`by`, `Family`/`family`, `Records`/`records`, …).
> Grepping the actual call sites for a sample showed almost all of them are **already correct**
> per the Title-Case-vs-sentence-case rule above — e.g. `gettext('people')` in
> `webpack/people/importDemoData.js` renders `"5 people"` (a count, correctly sentence case) while
> `gettext('People')` in nav breadcrumbs is UI chrome (correctly Title Case). Same story for
> `gettext('active')` → `"3 active"` badge count vs. `gettext('Active')` → status badge label, and
> `gettext('by')` → `"by John Smith"` attribution vs. a Title-Case heading elsewhere.
> Some flagged msgids (e.g. `Loading`/`loading`) had **no live call site at all** for one side —
> `locale/messages.po` accumulates entries that the automation hasn't pruned yet, so a msgid
> existing in the `.po` file doesn't guarantee a matching `gettext()`/`i18next.t()` call still
> exists in source.
> **Rule: never merge a case-dupe pair from the `.po` grep alone.** For each pair, `grep -rn` both
> forms across `src/` and `webpack/`, read the surrounding context, and only touch call sites
> where the same UI role (both chrome, or both body text) is using two different casings of the
> same string. `Id`/`ID` (below) is the pattern of a genuine hit: same role (table column header)
> in every call site, just an unnormalized acronym.

**Acronym exceptions** (always uppercase regardless of case form): `URL`, `ID`, `IP`, `SMTP`, `CSV`, `PDF`, `2FA`, `API`, `HTML`, `TLS`, `SSL`. Never write `Id`, `Url`, `Sms`, etc. — pick the acronym form once and use it everywhere.

```php
// CORRECT
gettext('User ID')
gettext('SMTP Host')

// WRONG — acronym should stay all-caps
gettext('User Id')
gettext('Smtp Host')
```

**Real-world instance (2026-07-24):** `person-list.php` (2 call sites, both DataTables column-header maps) and `self-register.php` used `gettext('Id')` / `i18next.t('Id')` for a table column header, while `finance/views/dashboard.php` used `gettext('ID')` for the same kind of column header — two msgids for one concept. Fixed by normalizing all three to `gettext('ID')` / `i18next.t('ID')`. The internal array key/DataTables `data:` field (`'Id'`) was left untouched — that's an identifier, not display text, and renaming it would require a matching change on the API response shape.

**Dialog title special case:** `i18next.t('ERROR')` was historically used as a bootbox title and created an `ERROR`/`Error` msgid pair. Always use `i18next.t('Error')` (Title Case) for dialog titles. Reserve all-caps strings for log levels / data attribute values, NOT translation keys (e.g. `data-level="ERROR"` is fine, but the visible label uses `gettext('Error')`).

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

> [!WARNING] NEVER run `npm run locale:build` <!-- learned: 2026-07-11 -->
> Term extraction and `locale/terms/messages.po` updates are **automated outside
> this repo** (POEditor sync). Do not run `locale:build`, and do not hand-edit or
> commit `messages.po` or the generated locale JSON files. Running it locally only
> creates a spurious diff that conflicts with the automation.
>
> **To add a UI term: wrap it in `gettext()` / `i18next.t()` and commit the code.
> That's the whole job.** The automation picks it up from the source.

### Workflow

1. **Wrap the string** in `gettext()` (PHP) or `i18next.t()` (JS)
2. **Check for an existing equivalent term first** — reuse beats creating a new one
3. **Commit the code change only**

### Step-by-Step Example

**Goal:** Add "Apply" button to form

**Step 1** - Wrap the string in code (PHP):
```php
<button type="submit"><?= gettext('Apply') ?></button>
```

...or in JS:
```js
$("#applyBtn").text(i18next.t("Apply"));
```

**Step 2** - Commit the code. Do **not** touch `messages.po`:
```bash
git add src/admin/views/settings.php
git commit -m "Add 'Apply' button term to localization"
```

That's it — there is no locale rebuild step. The POEditor automation extracts the
new `msgid` from the committed source on its own schedule.

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

### Correctness Outranks Term-Count Savings <!-- learned: 2026-07-25 -->

> [!WARNING] Raw two-piece concatenation (`gettext('Delete') . ' ' . gettext('Group')`) has a real, confirmed grammar defect. **Do not use this pattern for new consolidations.** If in doubt between saving a term and getting every language's grammar right, get the grammar right — the cost math in this file exists to eliminate *waste*, not to buy savings at the price of broken output in any of the 45+ languages this project ships.

**The defect, confirmed against real translations, not theoretical:** Japanese and Korean are verb-final (SOV) languages — a correct translation of "Delete Group" puts the verb *last* ("Group['s] deletion", not "Delete Group"). Checking the actual shipped translations of the pre-existing `gettext('Add New') . ' ' . gettext('Fund')` pattern:

```
ja_JP: "新規追加" (Add New) + " " + "基金" (Fund)  →  "新規追加 基金"  — reads backwards; natural Japanese is "基金の新規追加" (Fund['s] new-addition)
ko_KR: "새로 추가" (Add New) + " " + "헌금 항목" (Fund)  →  same problem
```

Each piece is translated **in isolation** — the "Add New" translator has no idea what noun follows, so they cannot place the verb where their language's grammar requires it. This is different from RTL (Arabic/Hebrew): those two are commonly verb-first like English for short commands, so the RTL-specific concern is bidi *punctuation positioning* (see the RTL section below), not clause order. The word-order defect is specifically about SOV languages already in this project's 49-locale list (`ja_JP`, `ko_KR`), independent of text direction.

**What to do instead, in priority order:**

1. **Keep it as one whole, indivisible phrase** (`gettext('Delete Group')`) whenever the entity is fixed at that call site. This costs one term instead of zero, but it is the only option with *zero* grammar risk — the translator sees the complete real-world phrase and can write it in whatever order their language needs.
2. **If the entity is genuinely dynamic** (a loop over entity types, a runtime variable), use a single parameterized msgid with `sprintf()`/`{{placeholder}}` — `sprintf(gettext('Delete %s'), gettext($entityType))`. This is **fundamentally different from concatenation**: the whole string `"Delete %s"` is one translatable unit, and the translator repositions `%s` anywhere their grammar requires (e.g. a ja/ko translation of `"Delete %s"` can legally be `"%sを削除"`, placing `%s` first). This is exactly the same reasoning already established for split-sentence fragments elsewhere in this file — a single parameterized string, never pieces glued by code.
3. **Never** `gettext('A') . ' ' . gettext('B')` where A and B are independently common words (a verb and a noun) that only make sense in a fixed relative order in English.

**Full-codebase audit completed and fixed (2026-07-25).** Given a choice between term-count savings and correct grammar in every supported language, correctness wins — full stop. A repo-wide sweep found 20 genuine verb+object concatenation call sites across 15 files (`gettext('Add New') . ' ' . gettext('Fund')`, `gettext('Delete Confirmation') . ': ' . gettext('Family')`, and JS equivalents in `Footer.js`/`FindDepositSlip.js`) — all were collapsed into single whole-phrase msgids (e.g. `gettext('Add New Fund')`, `gettext('Family Delete Confirmation')`), each verified against the existing "`{Entity} Delete Confirmation`" naming convention already used by `users.js`'s `"User Delete Confirmation"`. This re-adds roughly 20 terms to the catalog — an accepted, deliberate cost, not an oversight. Five *new* instances of the pattern were also caught mid-session (introduced while chasing pure term-count savings on "Add New Class"/"Add New Group"/"Delete Event"/"Delete Calendar"/"User Delete Confirmation") and reverted the same way before they shipped.

**Detection — repo-wide sweep for this pattern:**
```bash
grep -rnoE "gettext\(['\"][^'\"]*['\"]\)\s*\.\s*['\"][^'\"]{0,3}['\"]\s*\.\s*gettext\(['\"][^'\"]*['\"]\)" src --include="*.php" | grep -v vendor
grep -rnoE "i18next\.t\(['\"][^'\"]*['\"]\)\s*\+\s*['\"][^'\"]{0,3}['\"]\s*\+\s*i18next\.t\(['\"][^'\"]*['\"]\)" webpack src --include="*.js" --include="*.ts" | grep -v "\.min\.js"
```
Not every hit is a violation — skip pairs where both sides are already complete, independent phrases joined by a separator (`gettext('New Payment') . ' - ' . gettext('New Deposit Will Be Created')`), a noun/noun compound (`gettext('Birth Date') . ' / ' . gettext('Anniversary Date')`), or a `Label: Value` pair (`gettext('Gender') . ': ' . gettext('Male')`) — those don't have a verb that needs repositioning, so there's no SOV risk to fix.

### Solution: Component-Based Terms (Legacy Pattern — Read the Warning Above First)

Consolidate compound terms into reusable parts:

```php
// ⚠️ Pre-existing pattern, has the SOV defect above — don't add new instances
gettext('Add New') . ' ' . gettext('Field')
gettext('Add New') . ' ' . gettext('Fund')
gettext('Add New') . ' ' . gettext('User')
// Result: 10 total strings, 45 languages = 450 translations (saves 430!)

// ⚠️ Same defect via ': ' concatenation — don't add new instances
gettext('Delete Confirmation') . ': ' . gettext('Family')
gettext('Delete Confirmation') . ': ' . gettext('Note')
// Result: 9 total strings (5 entity types), saves 98 translations
```

### Pattern: "Add New" Button (Legacy — Do Not Add New Instances)

```php
// ⚠️ Pre-existing, has the SOV word-order defect above — don't copy this for new code
<input type="submit" 
       value="<?= gettext('Add New') . ' ' . gettext('Fund') ?>" />

<h3><?= gettext('Add New') . ' ' . gettext('Group') ?></h3>

<div class="card-header">
    <?= gettext('Add New') . ' ' . gettext('Field') ?>
</div>
```

### Pattern: Delete Confirmation

The page-title line below is the same `A . ': ' . B` concatenation shape and has the same known defect — it's existing, accepted debt in `VolunteerOpportunityEditor.php`/`PropertyTypeDelete.php`, not a pattern to extend. The `sprintf()` line directly under it is the **correct** approach for a genuinely dynamic entity — one parameterized msgid, translator controls word order:

```php
<?php
$entityType = 'Family';  // Dynamic
// ⚠️ Legacy concatenation — has the SOV defect, don't copy for new code
$pageTitle = gettext('Delete Confirmation') . ': ' . gettext($entityType);
?>

<h1><?= $pageTitle ?></h1>
<!-- ✅ CORRECT — single parameterised msgid, translator repositions %s freely -->
<p><?= sprintf(gettext('Are you sure you want to delete this %s?'), gettext($entityType)) ?></p>
```

**Confirm-button labels don't need to repeat the entity name either**, when the surrounding dialog (page title, card header, or a "Please confirm deletion of X: {name}" line) already states it. Real instances fixed (2026-07-25): `VolunteerOpportunityEditor.php` used `gettext('Yes, delete this Opportunity')` and `PropertyTypeDelete.php` used `gettext('Yes, delete this record')` as one-off button labels, when a generic `gettext('Yes, delete')` (already used elsewhere, e.g. `PropertyList.php`) would do — both pages already display the entity name/type in the confirmation heading above the button.

```php
// ❌ WRONG — entity name baked into the button label (one-off msgid, only used here)
<button><?= gettext('Yes, delete this Opportunity') ?></button>

// ✅ CORRECT — generic label; the card header/title above already says "Volunteer Opportunity"
<button><?= gettext('Yes, delete') ?></button>
```

Note: `"Yes, Remove"` (used for reversible cart-removal actions) is intentionally kept as a **separate** canonical term from `"Yes, delete"` (destructive record deletion) — they represent different-consequence actions, not a punctuation/wording variant of the same concept. Don't merge those two.

### Real-World Duplicate-Field-Label Instances (Zip / State) <!-- learned: 2026-07-25 -->

A field that appears on multiple forms should have exactly one canonical label — let translators render the regionally-appropriate variant (e.g. "Postal Code" instead of "Zip") as *their* translation of that one term, rather than shipping two English source strings for the same concept.

```php
// ❌ WRONG — same Zip field, two different English labels across forms
// FamilyEditor.php, CSVExport.php, etc.:
gettext('Zip')
// people/views/cart/to-family.php (same #Zip field):
gettext('Zip / Postal Code')

// ✅ CORRECT — one canonical term everywhere; translators localize the wording
gettext('Zip')
```

Found and fixed 2026-07-25: `people/views/cart/to-family.php` used `'Zip / Postal Code'` and `'State / Province'` for the exact same `id="Zip"`/`id="State"` fields that every other form (`FamilyEditor.php`, `PersonEditor.php`, `church-info.php`, `family-list.php`, `family-register.php`) labels with the bare `gettext('Zip')` / `gettext('State')`. Also updated the CSV import column-label constant (`CSV_CORE_FIELD_LABELS` in `admin/routes/api/import.php`) which had independently baked in `'Zip / Postal Code'` as static array data.

**Not the same as a DB-context-tagged term** — `"Zip Code"` (with `#. Context: queryparameteroptions_qpo`) is seeded row data in the Advanced Search / Query Builder field-picker (`queryparameteroptions_qpo` table, see `database-operations.md`), consistent with sibling option labels in that same set (`Home Phone`, `City`, `State`). Changing it means a DB data migration across every install, not a source-code fix — leave it alone; it is not a duplicate of the UI `"Zip"` label.

**Detection — grep for a field's bare label vs. compound "X / Y" labels used on the same `id=`:**
```bash
grep -rn "gettext('Zip')\|gettext('Zip / Postal Code')" src webpack
grep -rn "gettext('State')\|gettext('State / Province')" src webpack
```
Not every `"X / Y"` label is a duplicate — some legitimately combine two distinct data concepts into one compound column header or checkbox (e.g. `"Class / Group"` in `kiosk/views/manager.php` covers rows that are either a class or a group; `"Role / Gender"` in `people/views/dashboard.php` covers two separate stat columns; `"Age / Years Married"` in `CSVExport.php` is a CSV column that means Age for a person row and Years Married for a couple row). Only merge when both sides genuinely label the *same single field*.

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

### Step 4: Verify in the UI

```bash
npm run build          # Regenerate front-end assets
```

Do **not** run `npm run locale:build` to "test" a consolidation — verify by
reading the source and checking the rendered UI. Extraction is automated.

### Step 5: Measure Impact

```
Original:  12 unique terms × 45 languages = 540 translations
Consolid: 7 unique terms × 45 languages = 315 translations
SAVED: 225 translations (42% reduction!)
```

---

## Locale Rebuild Workflow

### Never rebuild the locale catalog <!-- learned: 2026-07-11 -->

**There is no locale rebuild step in the dev workflow.** Extraction of `gettext()` /
`i18next.t()` strings into `locale/terms/messages.po`, and the sync of translations
back from POEditor, are **automated outside this repo**.

```bash
# ❌ NEVER — not before a commit, not to "test", not to "verify extraction"
npm run locale:build

# ❌ NEVER — the automation owns these files
git add locale/terms/messages.po
git add src/skin/v2/locale/
```

```bash
# ✅ The entire workflow for a new UI string:
#   1. Wrap it: gettext('My String')  /  i18next.t('My String')
#   2. npm run build     (front-end assets only, if you touched JS/CSS)
#   3. Commit the source file. Nothing else.
```

Running `locale:build` locally produces a large spurious diff that conflicts with
the automation and will be rejected in review. If a term seems to be missing from
a translation, that is an automation/POEditor concern — see
[`locale-translation-workflow.md`](./locale-translation-workflow.md), not a reason
to rebuild by hand.

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

### Punctuation & Colon Placement <!-- learned: 2026-03-15 -->

**Rule: Move colons OUTSIDE gettext() calls.** Colons are UI punctuation, not translatable content. Translators should not include punctuation.

**Pattern:**
```php
// ❌ WRONG - Colon inside translation
echo gettext('Birth Date:');
echo gettext('Type:');
echo gettext('File Name:');

// ✅ CORRECT - Colon outside translation
echo gettext('Birth Date') . ':';
echo gettext('Type') . ':';
echo gettext('File Name') . ':';
```

**With spaces after colon (label separator):**
```php
// ❌ WRONG
echo gettext('Label: ');

// ✅ CORRECT
echo gettext('Label') . ': ';
```

**In sentence-ending colons (introducing a list):**
```php
// ❌ WRONG
echo gettext('Please select from the following:');

// ✅ CORRECT
echo gettext('Please select from the following') . ':';
```

**Detection — grep for a trailing colon inside the quotes:**
```bash
grep -rnoE "gettext\('[^']*:'\)" src/
grep -rnoE 'gettext\("[^"]*:"\)' src/
grep -rnoE "i18next\.t\('[^']*:'" webpack/ src/
grep -rnoE 'i18next\.t\("[^"]*:"' webpack/ src/
```

**Before fixing, check whether the colon-less form already exists** — a term wrapped with and
without a trailing colon in different files is two msgids for one word (e.g. `gettext('Warning')`
in one place, `gettext('Warning:')` in another). Search for the bare term first and reuse it:
```bash
grep -rn "gettext('Warning')" src/   # does the colon-less form already exist?
```
If it does, fix the colon-suffixed call sites to use the existing bare term plus a literal `:`
outside the call, rather than leaving two separate msgids for the same word. <!-- learned: 2026-07-24 -->

**In HTML attributes or templates:**
```php
// ✅ CORRECT - Inline concatenation
<?= gettext('Birth Date') . ':' ?>

// ✅ CORRECT - Attribute context
<label><?= gettext('Type') . ':' ?></label>

// ✅ CORRECT - More readable format (if wrapping is needed)
echo '<label>'
    . gettext('Type')
    . ':</label>';
```

**Do not hand-edit `locale/messages.po` for this change.** <!-- learned: 2026-07-24 -->
Moving the colon out of the `gettext()`/`i18next.t()` call is enough — the old `"Label:"` msgid
simply stops being extracted on the next automated run, and a new `"Label"` msgid appears in its
place. This supersedes older guidance in this section that said to update `messages.po` by hand;
see ["Never rebuild the locale catalog"](#never-rebuild-the-locale-catalog----learned-2026-07-11-)
below — the automation, not the developer, owns that file.

### Decorative Wrappers (em-dash, brackets, etc.) Go Outside the Call <!-- learned: 2026-07-25 -->

**Rule: Move purely decorative wrapper characters — em-dashes, brackets, parentheses used as visual framing — OUTSIDE `gettext()`/`i18next.t()` calls, same as the colon rule above.** A dropdown blank-option label like `"— Select Country —"` bakes UI decoration into the msgid; the dashes are identical in every language and contribute nothing for a translator to translate, they just add visual noise and risk the translator "translating" or repositioning them.

```php
// ❌ WRONG - em-dash decoration inside gettext()
<option value=""><?= gettext('— Select a group —') ?></option>

// ✅ CORRECT - decoration outside, translator only sees the real words
<option value="">— <?= gettext('Select a group') ?> —</option>
```

```javascript
// ❌ WRONG
const blankLabel = i18next.t("— Select Country —");

// ✅ CORRECT
const blankLabel = `— ${i18next.t("Select Country")} —`;
```

**Check for an existing bare form first** — exactly like the colon rule, a decorated and undecorated version of the same phrase are two msgids for one concept. `"— Select a group —"` was found duplicating an already-existing bare `gettext('Select a group')` used in four other editors (`PersonCustomFieldsEditor.php`, `FamilyCustomFieldsEditor.php`, `GroupEditor.php`, `GroupPropsFormEditor.php`); the decorated call site was switched to reuse the existing term rather than keep a second msgid.

**Not the same as "e.g." example-hint text** — `"e.g., Sunday Service"`-style placeholder strings are NOT decoration; "e.g." is itself a real word that needs translation (Spanish "p. ej.", French "par ex.", Japanese "例："), and it forms one natural phrase with its example. Splitting it out only helps when the fragment is reused across many strings — checked here (2026-07-25): `"e.g."` appears in exactly 7 strings and nowhere else, so splitting would create 8 translation units instead of 7. Leave these as complete phrases.

**Detection:**
```bash
grep -rnoE "gettext\(['\"]— [^'\"]*—['\"]\)" src --include="*.php" | grep -v vendor
grep -rnoE 'i18next\.t\(["\047]— [^"\047]*—["\047]\)' webpack src | grep -v "\.min\.js"
```

### Trailing-Period/Exclamation Duplicates — Check Sibling Convention Before Merging <!-- learned: 2026-07-25 -->

A msgid pair differing only by trailing `.`/`!`/`...` (e.g. `"User not found"` vs `"User not found."`) is **not automatically a bug**. A 2026-07-25 audit found this codebase has two genuinely distinct, internally-consistent conventions that legitimately collide on wording:

- **Short-phrase / API-JSON / page-subtitle contexts almost always omit the trailing period** — `SlimUtils::renderErrorJSON()` error messages, `sPageSubtitle` values, toast/`notify()` calls, exception messages thrown internally.
- **Full-sentence / user-facing display contexts almost always include it** — bootbox modal alerts, `$_SESSION['sGlobalMessage']` flash messages, HTML alert `<div>`s, body paragraphs.

```php
// ✅ Both correct — same underlying error, two different display contexts, each internally consistent
// API route (short phrase, no period):
return SlimUtils::renderErrorJSON($response, gettext('Note not found'), [], 404);
// Modal alert (full sentence, period) — delete-note-modal.php:
bootbox.alert(<?= json_encode(gettext('Note not found.')) ?>);
```

**Before merging a punctuation-duplicate pair, check each call site's *sibling* messages in the same file/context** — if 6 other messages in the same file all share the same punctuation style as one side of the pair, that side is intentional, not a bug. Only merge when one side is an **outlier against its own sibling family** (a one-off inconsistency, not a deliberate context split). Real examples found and fixed this way: `UserService.php` had 7 `RuntimeException` messages with no period and exactly 1 outlier (`'User not found.'`) — fixed the outlier, left the API/modal split pairs (`Note not found`, `Property not found` in `PropertyMiddleware`, `Failed to delete note`, etc.) alone since each side matched its own family.

**Detection:**
```bash
python3 -c "
import re
po = open('locale/messages.po').read()
ids = [m for m in re.findall(r'^msgid \"(.*?)\"\$', po, re.MULTILINE) if m]
base_map = {}
for m in ids:
    base = m.rstrip('!.?')
    base_map.setdefault(base, set()).add(m)
for base, variants in base_map.items():
    if len(variants) > 1:
        print(sorted(variants))
"
```
Then `grep -rn -F "<the phrase>"` each variant's call sites and read the surrounding sibling messages before deciding whether to merge.

### Do Not Wrap Brand / Technical Literals <!-- learned: 2026-04-22 -->

**Rule: Never wrap brand names, product names, language/runtime identifiers, config keys, protocol acronyms, or placeholder strings in `gettext()` / `_()`.** These are literals, not UI copy — translators cannot (and should not) change them, and wrapping them pollutes every locale's missing-terms batch and creates POEditor noise in every language.

**Always bare literals (never translate, never wrap):**

| Category | Examples |
|---|---|
| Language / runtime names | `PHP`, `Node.js`, `MySQL`, `MariaDB`, `Apache`, `nginx` |
| Extension / module names | `OPcache`, `SAPI`, `mod_rewrite`, `ionCube` |
| Config keys & directives | `date.timezone`, `memory_limit`, `post_max_size`, `session.save_handler` |
| Protocol / tech acronyms | `TLS`, `Auto-TLS`, `SSL`, `SMTP`, `IMAP`, `DNS`, `CSP`, `CORS`, `SHA1 Hash` |
| Brand names | `ChurchCRM`, `Vonage`, `MailChimp`, `GitHub`, `OpenLP`, `Nextcloud`, `Gravatar`, `WebDAV`, `POEditor`, `ownCloud`, `Stripe`, `PayPal` |
| Placeholder examples | `name@example.com`, `+1-555-123-4567`, `https://example.com` |
| Punctuation-only placeholders | `—` (em dash "no data" marker), `-`, `...`, `•` |

**Pattern:**

```php
// ❌ WRONG - Brand/config literal wrapped in gettext (pollutes all locales' missing batches)
echo gettext('OPcache');
echo gettext('PHP');
echo gettext('SAPI');
$phpIni = [
    gettext('date.timezone') => ini_get('date.timezone'),
];
<td><?= gettext('Auto-TLS') ?></td>
<input placeholder="<?= gettext('name@example.com') ?>">

// ✅ CORRECT - Bare literal
echo 'OPcache';
echo 'PHP';
echo 'SAPI';
$phpIni = [
    'date.timezone' => ini_get('date.timezone'),
];
<td>Auto-TLS</td>
<input placeholder="name@example.com">
```

**Punctuation-only placeholder example** — an em-dash "no data" marker was found wrapped in `gettext()` even though the identical raw `—` character appears unwrapped elsewhere on the same page:

```php
// ❌ WRONG — punctuation, not copy; there is nothing for a translator to translate
'noStreak' => gettext('—'),

// ✅ CORRECT — bare literal, matches the raw '—' used elsewhere on the page
'noStreak' => '—',
```

A `gettext()`/`i18next.t()` call whose argument is entirely punctuation/whitespace (`-`, `—`, `...`, `•`, a bare space) is always a leak — grep for it directly:

```bash
grep -rnE "gettext\(['\"][^a-zA-Z0-9]{1,4}['\"]\)" src/
grep -rnE "i18next\.t\(['\"][^a-zA-Z0-9]{1,4}['\"]\)" src/ webpack/
```
(Short *alphanumeric* results like `gettext('To')`, `gettext('OK')`, `gettext('N')` from the same grep are legitimate translatable words/abbreviations — only punctuation-only matches are leaks.) <!-- learned: 2026-07-24 -->


**How to detect leaks:** a term that a) appears in `locale/terms/missing/{code}/{code}-N.json` across many locales with an empty string, and b) is a brand / technical / config literal, is almost certainly wrongly wrapped. Quick aggregation:

```bash
node -e "
const fs=require('fs'),path=require('path');
const root='locale/terms/missing';
const counts={};
for (const d of fs.readdirSync(root)) {
  const dir=path.join(root,d);
  if (!fs.statSync(dir).isDirectory()) continue;
  for (const f of fs.readdirSync(dir)) {
    if (!f.endsWith('.json')) continue;
    const data=JSON.parse(fs.readFileSync(path.join(dir,f),'utf8'));
    for (const k of Object.keys(data)) counts[k]=(counts[k]||0)+1;
  }
}
Object.entries(counts).sort((a,b)=>b[1]-a[1]).slice(0,30)
  .forEach(([k,v])=>console.log(String(v).padStart(3)+'  '+JSON.stringify(k)));
"
```

Terms at the top of the list that match the table above should be unwrapped in source.

**If you remove a wrapper**, the next automated extraction stops picking it up — you do **not** run `npm run locale:build` yourself to make that happen. New missing batches no longer include it; stale POEditor entries are harmless and can be cleaned up manually.

**Related:** the locale translation commands (`/locale-translate`, `/locale-release`) list these same tokens under "Preserve exactly / never translate" — the fix here is to stop them entering the pipeline in the first place.

### Never Split a Sentence Across Multiple gettext() Calls <!-- learned: 2026-04-22 -->

Concatenating `gettext()` fragments loses context and creates broken msgids with leading/trailing spaces that translators cannot understand. Always wrap the **complete sentence** as a single string and use `sprintf()` for embedded values.

```php
// ❌ WRONG — splits "This value cannot be more than N characters long" into 3 pieces
$msg = gettext('This value cannot be more than ') . $n . gettext(' characters long');
// Produces orphaned msgid ' characters long' with leading space — untranslatable

// ✅ CORRECT — full sentence, value injected via sprintf
$msg = sprintf(gettext('This value cannot be more than %d characters long'), $n);

// ❌ WRONG — page title split across fragments
$title = gettext('New Payment') . " - $dep_Type" . gettext(' Deposit #') . " $id";

// ✅ CORRECT
$title = sprintf(gettext('New Payment - %1$s Deposit #%2$d'), $dep_Type, $id);

// ❌ WRONG — result count
echo mysqli_num_rows($rs) . gettext(' record(s) returned');

// ✅ CORRECT
echo sprintf(gettext('%d record(s) returned'), mysqli_num_rows($rs));
```

**Detection:** fragment msgids are identifiable in `locale/messages.po` by a leading or trailing space in the msgid string — e.g. `msgid " characters long"`. Open issue [#8772](https://github.com/ChurchCRM/CRM/issues/8772) tracks the known fragments still in the codebase. Grep source directly rather than relying on the `.po` file (which lags the automation):
```bash
grep -rnoE "gettext\('[^']* '\)" src/    # trailing space
grep -rnoE "gettext\('[^']*'\)" src/ | grep -E "\('\s"  # leading space
```

**Real-world instances fixed (2026-07-24):** the two `❌ WRONG` examples above (`QueryView.php` alpha/numeric validation messages, `PledgeEditor.php` deposit page title) were not hypothetical — they were live bugs in this codebase, along with ~15 more of the same shape in `Reports/TaxReport.php`, `Reports/ReminderReport.php`, `Reports/FamilyPledgeSummary.php`, `WhyCameEditor.php`, `admin/routes/api/import.php`, `ChurchCRM/Authentication/AuthenticationProviders/APITokenAuthentication.php`, and `fundraiser/routes/reports.php` (PDF cell-write calls). All were fixed the same way: pull the fragment into one `sprintf()`-parameterised string. Where a value only fills in a leading/trailing connector word rather than a full sentence (e.g. building `" for fund "` before appending a loop of fund names), the minimal fix is to move the space to a plain string concatenation outside `gettext()` rather than restructure the surrounding loop — e.g. `gettext(' for fund ')` → `' ' . gettext('for fund') . ' '`.

**Related but distinct: pure-formatting content should not be wrapped at all.** A decorative PDF divider line (`gettext('----...----')`, all dashes, no words) was found wrapped in `fundraiser/routes/reports.php` — unwrapped to a bare literal, same as the punctuation-only-placeholder case in ["Do Not Wrap Brand / Technical Literals"](#do-not-wrap-brand--technical-literals----learned-2026-04-22---) above. A related case in the same file padded a real word (`'Signature'`) with 40 leading spaces and 68 trailing underscores for fixed-width PDF layout — the word was pulled into its own `gettext('Signature')` call with the padding built via `str_repeat()` outside it, rather than baking layout whitespace into the msgid.

**Real-world instance (2026-07-24):** `ChurchCRM\Service\PersonService::search()` built a family-role string as `$roleText . gettext(' of the') . ' <a>...</a> ' . gettext('family') . ' )'` — an orphaned `' of the'` fragment (leading space) plus a bare `'family'` msgid that duplicates the unrelated `gettext('family')` used elsewhere in `PersonList.php`'s "records" sentence. Fixed by building the dynamic HTML link first, then wrapping the whole phrase in one `sprintf(gettext('%1$s of the %2$s family'), $roleText, $familyLink)` call:
```php
// ❌ WRONG — orphaned ' of the' fragment, msgid leading space
$familyRole .= gettext(' of the') . ' ' . $familyLinkHtml . ' ' . gettext('family') . ' )';

// ✅ CORRECT — one msgid, HTML link passed as a %2$s value
$familyRole .= sprintf(gettext('%1$s of the %2$s family'), $roleText, $familyLinkHtml) . ' )';
```

**Checklist addition:** Add `- [ ] No gettext() fragments — full sentence per call, sprintf for values` to your pre-commit review.

### Never Split a Sentence in JavaScript (i18next template literals) <!-- learned: 2026-07-11 -->

The same anti-pattern occurs in JS where two `i18next.t()` calls are concatenated in a template literal, often with HTML or a runtime value in between. The extracted msgids are short, decontextualised fragments that translators cannot understand.

```javascript
// ❌ WRONG — three fragments; count bolded via HTML between two t() calls
$("#upgradePathSummary").html(
  `${i18next.t("You are")} <strong>${count}</strong> ${i18next.t("releases behind. Here's what you'll gain:")}`,
);
// Produces msgids: "You are" and "releases behind. Here's what you'll gain:"

// ❌ ALSO WRONG — single msgid, but bakes <strong> markup into the translatable string
$("#upgradePathSummary").html(
  `${i18next.t("You are <strong>{{releaseCount}}</strong> releases behind. Here's what you'll gain", {
    releaseCount: count,
  })}:`,
);

// ✅ CORRECT — single parameterised msgid, no markup in the string at all.
// Trailing colon is UI punctuation (see "Punctuation & Colon Placement" above) — kept
// outside the t() call even in JS. Use .text() (not .html()) since there is no
// markup to render.
$("#upgradePathSummary").text(
  `${i18next.t("You are {{releaseCount}} releases behind. Here's what you'll gain", {
    releaseCount: count,
  })}:`,
);
```

**Detection — grep for JS template-literal split patterns:**
```bash
# Detects: `...${i18next.t(...)} ... ${i18next.t(...)}...`
grep -rn '\${i18next\.t(' webpack/src/skin/js/ | grep -v '//' | head
# Multiple i18next.t hits on the same line = likely fragment concatenation
grep -rn 'i18next\.t(' webpack/ | awk -F: '{if (gsub(/i18next\.t\(/, "&", $2) > 1) print}'
```

**i18next `count` reserved key — use named alternatives:**
i18next treats `count` as a special interpolation key that triggers plural-form lookup. When injecting a plain integer that is *not* a plural selector, use a distinct name to avoid unintended plural behaviour:
```javascript
// ❌ WRONG — triggers i18next plural lookup
i18next.t("{{count}} releases behind", { count })

// ✅ CORRECT — named interpolation, no plural side-effect
i18next.t("{{releaseCount}} releases behind", { releaseCount: count })
```

**HTML-valued interpolation (i18next `escapeValue: false`):**
When an interpolated value must contain HTML (e.g. a `<span>` to keep an element ID stable for Cypress), sanitize the inner text with `escapeHtml()` yourself, then disable escaping per-call:
```javascript
function setWhatsNewHeading(version) {
  const versionHtml = `<span id="whatsNewVersion" class="text-primary">${escapeHtml(version || "")}</span>`;
  $("#whatsNewHeading").html(
    i18next.t("What's New in {{version}}", {
      version: versionHtml,
      interpolation: { escapeValue: false }, // safe: inner text manually escaped
    }),
  );
}
```
This is a per-call option; it does not affect other `i18next.t()` calls.

### No HTML or Markup in Translatable Strings <!-- learned: 2026-07-25 -->

**Rule: never bake HTML tags (`<strong>`, `<br>`, etc.) or other markup into a `gettext()`/`i18next.t()` msgid.** Earlier guidance in this file treated `<strong>`-wrapped msgids as acceptable precedent — that guidance was wrong and has been reversed. Translators should see plain, complete sentences; visual emphasis is a presentation concern, not a translation concern, and baking markup into the string risks a translator mangling or dropping the tag entirely.

```php
// ❌ WRONG — markup baked into the translatable string
gettext('Starts with <strong>http://</strong> or <strong>https://</strong>')
gettext('Ignore Incomplete<br>Addresses')

// ✅ CORRECT — plain sentence, no markup; let CSS/layout handle emphasis if needed
gettext('Starts with http:// or https://')
gettext('Ignore Incomplete Addresses')
```

```javascript
// ❌ WRONG — <strong> baked into the msgid
i18next.t("Remove <strong>{{personName}}</strong> from this class?", {
  personName, interpolation: { escapeValue: false },
})

// ✅ CORRECT — plain sentence; i18next's default escaping handles the interpolated value
i18next.t("Remove {{personName}} from this class?", { personName })
```

**Exception: HTML confined entirely to an *interpolated value*, never the msgid itself**, is still fine — e.g. wrapping a version number in a `<span>` to keep a stable element id for Cypress (see `setWhatsNewHeading()` in `upgrade-wizard-app.js`). The translator only ever sees `"What's New in {{version}}"` — no markup — because the `<span>` lives in the JS-built `version` value passed to `i18next.t()`, not in the translatable string.

**Detection:**
```bash
grep -rnoE "gettext\(['\"][^'\"]*<[a-zA-Z/][^'\"]*['\"]\)" src --include="*.php" | grep -v vendor
grep -rnoE 'i18next\.t\(["\047][^"\047]*<[a-zA-Z/][^"\047]*["\047]' webpack src | grep -v "\.min\.js"
```

### Trailing-Preposition / Cross-Language Split (PHP prefix + JS suffix) <!-- learned: 2026-07-11 -->

A subtle variant of the split-sentence anti-pattern occurs when **PHP renders a translatable prefix** and **JavaScript appends the dynamic suffix** at runtime. The PHP msgid ends in a dangling preposition or incomplete phrase:

```php
// ❌ WRONG — "What's New in" ends in a preposition; JS appends the version
<?= gettext("What's New in") ?> <span id="whatsNewVersion" class="text-primary"></span>
// JS later: $("#whatsNewVersion").text(nextVersion)
// Translator sees msgid "What's New in" with no idea what follows.
```

**Detection in messages.po:**
```bash
# Short msgids (≤ 5 words) that end in a preposition or article suggest a suffix will be appended
grep '^msgid ' locale/messages.po | awk '{if (NF <= 6) print}' | grep -iE '" ?(in|of|for|to|a|the|an)"$'
```

**Fix:** Move the *complete* sentence into a single JS `i18next.t()` call with a named placeholder, and remove the PHP `gettext()` call entirely:
```php
<!-- PHP: static shell only, no translatable prefix -->
<h4 class="mb-0">
    <i class="fa fa-tag me-1 text-primary"></i>
    <span id="whatsNewHeading"></span>
</h4>
```
```javascript
// JS: full sentence, single msgid
$("#whatsNewHeading").html(
  i18next.t("What's New in {{version}}", {
    version: versionHtml,
    interpolation: { escapeValue: false },
  }),
);
```

**Rule:** If a PHP `gettext()` key ends in a preposition (`in`, `of`, `for`, `to`) or an article and the rendered element is later updated by JS, it is a cross-language split. Consolidate into one JS (or PHP) parameterised string.

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

**Do not wrap brand / technical literals in `i18next.t()`** — same rule as PHP. Brand names (`ChurchCRM`, `GitHub`, `Stripe`), protocol acronyms (`TLS`, `SMTP`, `SSL`), runtime/language names (`PHP`, `Node.js`), config keys (`date.timezone`), and placeholder examples (`name@example.com`) are literals, not UI copy — leave them unwrapped. See the full table and detection recipe in the PHP section's ["Do Not Wrap Brand / Technical Literals"](#do-not-wrap-brand--technical-literals----learned-2026-04-22---) subsection above.

```javascript
// ❌ WRONG - Brand/tech literal wrapped in i18next.t()
el.textContent = i18next.t('ChurchCRM');
el.placeholder = i18next.t('name@example.com');

// ✅ CORRECT - Bare literal
el.textContent = 'ChurchCRM';
el.placeholder = 'name@example.com';
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
- [ ] No brand / technical literals wrapped — see ["Do Not Wrap Brand / Technical Literals"](#do-not-wrap-brand--technical-literals----learned-2026-04-22---) (brand names, config keys, protocol acronyms, `name@example.com`-style placeholders all stay as bare literals)
- [ ] No split-sentence fragments — each `gettext()` call wraps a complete sentence; dynamic values use `sprintf()` — see ["Never Split a Sentence"](#never-split-a-sentence-across-multiple-gettext-calls----learned-2026-04-22-)
- [ ] No JS template-literal split — no `${i18next.t(...)} ... ${i18next.t(...)}` concatenation; use single parameterised `i18next.t()` with `{{placeholder}}` — see ["Never Split in JavaScript"](#never-split-a-sentence-in-javascript-i18next-template-literals----learned-2026-07-11-)
- [ ] No trailing-preposition PHP msgid followed by JS runtime suffix — use a single JS `i18next.t('... {{version}}')` instead — see ["Trailing-Preposition"](#trailing-preposition--cross-language-split-php-prefix--js-suffix----learned-2026-07-11-)
- [ ] **Did NOT run `npm run locale:build`** — extraction is automated outside this repo
- [ ] **Did NOT commit `locale/terms/messages.po` or `src/skin/v2/locale/`** — the automation owns those
- [ ] If JS/CSS changed: Ran `npm run build`
- [ ] Checked for existing similar terms (reuse instead of creating new)
- [ ] Used consolidation patterns for compound terms
- [ ] Verified UI displays correctly (test with `npm run test`)

---

## Common Issues & Solutions

### Issue: "Translation not showing"

```bash
# 1. Verify the string is actually wrapped in the source
grep -rn "My New String" src/

# 2. Verify the term has reached the catalog (populated by the automation).
#    If it is missing here, the extraction automation has not run yet —
#    do NOT run `npm run locale:build` to force it.
grep "My New String" locale/terms/messages.po

# 3. Regenerate front-end assets (JS/CSS bundles only)
npm run build

# 4. Clear browser cache and hard refresh
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
- **Test translations locally** by running `npm run build` (never `npm run locale:build`)
- **Include context** for ambiguous terms in your translation notes

**Common Missing Terms in ChurchCRM:**
- "Add Link", "Configure [Plugin]", "Enable Two-Factor Authentication"
- "Post Contributions", "CRM Members Not Subscribed", "Audiences"
- "Back to Dashboard", "Set Active", "Allow Self-Signed Certificates"

Refer to existing translations in `locale/locales/[LANGUAGE].json` for consistency with already-translated terms.

---

## RTL (Right-to-Left) Locale Support <!-- learned: 2026-03-28 -->

Arabic (`ar`) and Hebrew (`he`) locales have `"isRTL": true` in `locales.json`. The rendering pipeline honours this flag automatically.

### How it works

| Layer | What happens |
|-------|-------------|
| `LocaleInfo::isRTL()` | Reads `localeConfig['isRTL']` from the loaded locale |
| `Header.php` | Emits `<html dir="rtl">` and sets `window.CRM.isRTL = true` |
| `Header-Minimal.php` | Same — call `Bootstrapper::getCurrentLocale()` then use `$localeInfo->isRTL()` |
| `Header-Short.php` | Same — needed for PrintView RTL support |
| `HeaderNotLoggedIn.php` | Same — login page is also RTL-aware |
| `Header-HTML-Scripts.php` | Loads `churchcrm-rtl.min.css` instead of `churchcrm.min.css` |

### Adding a new RTL locale

When `locale-add.js` creates a new locale, it now defaults `isRTL: false`. For Arabic/Hebrew, manually set `"isRTL": true` in `locales.json` after creation.

### Using RTL in JavaScript

```javascript
// Check direction in JS
if (window.CRM.isRTL) {
    // flip any LTR-specific logic (e.g. swipe direction, chart axis)
}
```

### Why the Term-Hygiene Rules Matter Even More for RTL <!-- learned: 2026-07-25 -->

Two of the RTL locales (`ar`, `he`) are part of the same 45+ language set every rule in this file applies to — RTL isn't a separate concern, it's a reason several of these rules exist in the first place:

- **Decorative punctuation outside the call (colon, em-dash wrappers) is *more* important for RTL, not just tidier.** The browser's bidi algorithm positions `— X —` / `X:` correctly around translated text automatically based on `dir="rtl"` (see the table above) — but only if that punctuation is plain surrounding text the browser controls, not characters baked into the middle of a translated msgid where a translator would have to reason manually about which visual side they render on.
- **Raw string concatenation for consolidation (`gettext('Delete') . ' ' . gettext('Group')`) hard-codes English word order** (action, then entity). Not every language — including RTL ones — necessarily wants the verb before the noun. This is an accepted, pre-existing trade-off in this pattern (used throughout the codebase), not something to rip out, but it's the reason `sprintf()`/`{{placeholder}}` parameterization is the *stronger* choice whenever there's a real full sentence involved: `sprintf(gettext('Add New %s'), gettext($entityType))` lets each language's translation place `%s` wherever its own grammar needs it, RTL or not. The split-sentence-fragment rule earlier in this file is the same principle at the sentence level — concatenating pieces around a runtime value locks in one language's word order for all 45+.

### Critical: all headers must initialize `$localeInfo`

Every PHP header file that includes `Header-HTML-Scripts.php` **must** initialise `$localeInfo` before the include. If it doesn't, the RTL CSS will not load and the `<html dir>` attribute will be missing.

```php
// ✅ Required in any header that includes Header-HTML-Scripts.php
use ChurchCRM\Bootstrapper;
$localeInfo = Bootstrapper::getCurrentLocale();
```

---

## POEditor Download/Upload Workflow <!-- learned: 2026-04-03 -->

### Extracting and Uploading New Terms

> [!WARNING] Not a developer task
> Extraction (`locale:build`) and the POEditor upload are
> run by the **release/locale automation**, not by hand during feature work. The
> steps below document what that automation does — they are not a checklist to
> follow when you add a `gettext()` string. See
> [`locale-translation-workflow.md`](./locale-translation-workflow.md).

```bash
# 1. Extract all translatable strings (PHP, JS, DB)  ← automation only
npm run locale:build
# Output: locale/messages.po (master template)

# 2. Upload messages.po to POEditor web dashboard
# - Go to https://poeditor.com → ChurchCRM project
# - Click "Import" or "Upload"
# - Select locale/messages.po
# - Choose "Override existing terms" (updates outdated terms)
# - Confirm upload

# 3. Download translations back to sync with POEditor
npm run locale:download
# Output: src/locale/i18n/*.json (all language files)
# Also generates: locale/terms/missing/[LANGUAGE]/*.json (untranslated batches)

# 4. Translate missing terms via POEditor or AI
# (See "AI-Assisted Translation Instructions" section)

# 5. Download again to get all translations
npm run locale:download

# 6. Commit
git add src/locale/i18n/
git commit -m "locale: download updated translations from POEditor"
```

### HTML Entity Corruption in Downloaded Translations <!-- learned: 2026-04-03 -->

**Issue:** POEditor's web interface sometimes encodes apostrophes as HTML entities (`&#39;`) when translations are copy-pasted from HTML contexts. When downloaded, these appear as literal `&#39;` strings in JSON files, breaking UI text display.

**Example of corrupted translations:**
```json
{
  "Church Event Editor": "Gestionnaire d&#39;événement de l&#39;église",
  "Email is Not Valid": "L&#39;adresse email n'est pas valide",
  "Sunday School Dashboard": "Tableau de bord de l&#39;école du dimanche"
}
```

**Root cause:** Translators or POEditor's import process copy text from web pages (which encode apostrophes as `&#39;`) without stripping HTML encoding before pasting into JSON.

**Fix:** After downloading, replace all `&#39;` with plain apostrophes across all locale files:

```bash
# 1. Replace HTML entities in all locale files
for file in src/locale/i18n/*.json; do
  sed -i '' 's/&#39;/'\''/g' "$file"
done

# 2. Verify no remaining entities
grep -r "&#39;" src/locale/i18n/

# 3. Upload cleaned files back to POEditor
# - This prevents re-downloading the broken versions
# - Keeps POEditor as source of truth for future translators
```

**Prevention:** 
1. After each `npm run locale:download`, audit for `&#39;` in locale files
2. If found, apply the fix above and **upload corrected files back to POEditor**
3. Train translators to use POEditor's interface directly (not copy-paste from external sources)
4. Set up a pre-commit hook to catch `&#39;` before they reach the repo

**Checking for the issue:**
```bash
# Count occurrences per locale
for file in src/locale/i18n/*.json; do
  count=$(grep -c "&#39;" "$file" 2>/dev/null || echo 0)
  if [ "$count" -gt 0 ]; then
    echo "$(basename $file): $count occurrences"
  fi
done
```

---

## AI-Assisted Bulk Translation: Agent Patterns <!-- learned: 2026-04-09 -->

When running bulk locale translation via AI sub-agents (e.g. the `locale-translate` skill), the following patterns were learned from empirical sessions:

### ✅ What Works: One Locale Per Sub-Agent

**Assign exactly ONE locale (2 files) to each sub-agent.** This is the only reliably completing pattern.

```
✅ agent-1: translate de (de-1.json + de-2.json)
✅ agent-2: translate ru (ru-1.json + ru-2.json)
✅ agent-3: translate ja (ja-1.json + ja-2.json)
... (all launched in parallel)
```

**Why it works:** Each agent reads 2 files, translates ~190 terms, and applies 2 `--apply` calls — a total of ~4–6 operations well within token/time budget.

### ❌ What Fails: Multiple Locales Per Sub-Agent

**Do NOT assign 2+ locales per agent.** This pattern causes timeout/failure on nearly every locale beyond the first.

```
❌ agent-1: translate de + ja   → reads 4 files, translates 380 terms, times out
❌ agent-1: translate sw + am   → reads 4 files, both incomplete
```

**Why it fails:** Reading 4 files + translating 380+ terms + applying 4 `--apply` calls exhausts the agent's context/time budget. The agent typically finishes reading but runs out of time before applying.

### ✅ Commit Immediately After Each Batch

**Always call `report_progress` to commit and push after each batch of agents completes.** Translations applied locally are lost on session termination if not committed.

```
❌ WRONG: Translate 20 locales, then call report_progress once → session expires, all work lost
✅ RIGHT: Translate 10 locales, call report_progress → push → translate next 10 → call report_progress
```

### Translation Conventions for All Locales

These rules must be stated explicitly in every agent prompt:

- Preserve `%d`, `%s`, `%1$s` format specifiers exactly  
- Preserve brand names: ChurchCRM, Vonage, MailChimp, GitHub, OpenLP, Nextcloud, Azure, Gravatar, WebDAV, POEditor, ownCloud  
- Leave intentionally empty strings as `""`: `N/A`, `name@example.com`, `SHA1 Hash`, `BCC`  
- For plural objects `{"one": "", "other": ""}`: provide both forms  
- Country names (e.g., "Australia", "Canada") stay in English (value = key) — mark these in `locale/terms/english-ok.json` rather than translating  

### Locale Church Vocabulary Reference

| Locale | Members | Groups | Giving | Kiosk |
|--------|---------|--------|--------|-------|
| es/es-MX/es-AR/es-CO/es-SV | Miembros | Ministerios | Ofrendas | Quiosco |
| pt/pt-br | Membros | Ministérios | Ofertas | Quiosque |
| fr | Membres | Ministères | Offrandes | Borne interactive |
| de | Gemeindemitglieder | Dienste | Gaben/Spenden | Kiosk |
| ru | Прихожане | Служения | Пожертвования | Киоск |
| zh-CN | 会众 | 事工部门 | 奉献 | 签到台 |
| zh-TW | 會眾 | 事工部門 | 奉獻 | 簽到台 |
| ja | 信徒 | ミニストリー | 献金 | チェックインキオスク |
| ko | 교인 | 사역 | 헌금 | 출석 키오스크 |
| ar | المؤمنين | الخدمات | العطاء | كشك |
| hi | सदस्य | मंत्रालय | दान/अर्पण | चेक-इन कियोस्क |
| id | Jemaat | Pelayanan | Persembahan | Kios |
| sw | Wanachama wa Kanisa | Huduma | Sadaka/Matoleo | Kiosk |
| am | አባላት | አገልግሎቶች | ስጦታ | ኪዮስክ |
| vi | Giáo đoàn | Các bộ phận | Dâng hiến | Quầy điểm danh |
| nl | Gemeenteleden | Bedieningen | Gaven/Giften | Kiosk |
| pl | Parafianie | Posługi | Ofiary/Datki | Kiosk |
| uk | Парафіяни | Служіння | Пожертвування | Кіоск |
| el | Ενορίτες | Λειτουργίες | Προσφορές | Περίπτερο |
| sv | Församlingsmedlemmar | Tjänster | Gåvor | Kiosk |
| ro | Enoriași | Slujiri | Ofrande | Chioșc |
| cs | Farníci/Členové | Služby | Příspěvky/Dary | Kiosek |
| hu | Gyülekezeti tagok | Szolgálatok | Adományok | Kiosk |
| he | מאמינים | שירותים | תרומות | קיוסק |
| nb | Menighetsmedlemmer | Tjenester | Gaver | Kiosk |
| fi | Seurakuntalaiset | Palvelut | Lahjoitukset | Kioski |
| et | Koguduse liikmed | Teenistused | Annetused | Kioski |
| af | Gemeentelede | Bedieninge | Gawes/Offergawes | Kiosk |
| sq | Besimtarët | Shërbesat | Kontributet | Kiosk |
| fil | Mga Miyembro ng Simbahan | Mga Ministeryo | Mga Handog | Kiosk |
| ml | സഭാ അംഗങ്ങൾ | ശുശ്രൂഷകൾ | കാഴ്ചദ്രവ്യം | കിയോസ്ക് |
| ta | சபை உறுப்பினர்கள் | ஊழியங்கள் | காணிக்கை | வருகை மையம் |
| te | సంఘ సభ్యులు | పరిచర్యలు | కానుకలు | హాజరు కేంద్రం |
| th | สมาชิกคริสตจักร | พันธกิจ | การถวาย | คีออสก์ |
| tr | Cemaat üyeleri | Hizmetler | Bağışlar | Kiosk |

---

## Related Skills

- [Git Workflow](./git-workflow.md) - Locale rebuild in pre-commit checklist
- [Security Best Practices](./security-best-practices.md) - Sanitization for localized content
- [PHP Best Practices](./php-best-practices.md) - gettext and internationalization

---

Last updated: July 24, 2026
