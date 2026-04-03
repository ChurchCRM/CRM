# /locale-translate

Translate missing ChurchCRM UI terms for one or all locales.

**Arguments:** `--list`, `--locale <code>`, or `--all`

---

## Step 1: Branch setup

```bash
node locale/scripts/locale-branch-manager.js --init
```

If already on a `locale/*` branch, skip this step.

---

## Step 2: Get remaining locales

```bash
node locale/scripts/locale-translate.js --list
```

---

## Step 3: For EACH locale with remaining terms

### 3a. Read the untranslated terms

```bash
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/<LOCALE>/<LOCALE>-1.json
```

This outputs a JSON object. Every key with `""` value needs a translation.

### 3b. Translate the terms

Produce a JSON object mapping each English key to its translation in the target language.

**Rules:**
- Church vocabulary: Members→Congregation, Groups→Ministries, Giving→Offerings, Pledge→Financial commitment, Cart→Selection
- Denomination context: Catholic (es/it/fr/pt/pl), Orthodox (ru/uk/el/ro), Lutheran (de/sv/nb/fi/et), Evangelical (ko/zh-CN/zh-TW/id), Coptic (ar)
- Preserve `%d`, `%s`, `%1$s` format specifiers exactly
- Preserve markdown formatting, URLs, and brand names (ChurchCRM, Vonage, MailChimp, GitHub, OpenLP, Nextcloud, etc.)
- Leave these keys with `""` value (do NOT translate): `N/A`, `name@example.com`, `SHA1 Hash`, `BCC`

### 3c. Apply translations via temp file

```bash
cat > /tmp/<LOCALE>-1-trans.json << 'ENDJSON'
{ ... your translations JSON ... }
ENDJSON

node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/<LOCALE>/<LOCALE>-1.json \
  --translations "$(cat /tmp/<LOCALE>-1-trans.json)"

rm /tmp/<LOCALE>-1-trans.json
```

For locales with multiple batch files (Telugu has 5, Greek has 2), repeat 3a-3c for each file.

### 3d. Commit and push

```bash
node locale/scripts/locale-branch-manager.js --commit-and-push \
  --locale <CODE> --language "<Language Name>" --terms <N>
```

---

## Step 4: Repeat for next locale

Process locales in this priority order (highest impact first):

**TIER-1 (53% world coverage):** es, es-MX, es-AR, es-CO, es-SV, zh-CN, zh-TW, pt-br, pt, hi, fr, ru, id, de, ja, ar
**TIER-2 (80% coverage):** sw, am, vi, te, it, ko, ta, th, uk, pl, nl, el, sv
**TIER-3 (completeness):** ro, cs, hu, he, nb, fi, et, af, sq

---

## Example: Translating French

```bash
# Read terms
node locale/scripts/locale-translate.js --read-file --file locale/terms/missing/fr/fr-1.json

# Output shows: {"Confession": "", "Format": "", "Minimum": "", ...}

# Write translations to temp file
cat > /tmp/fr-1-trans.json << 'ENDJSON'
{"Confession": "Confession", "Format": "Format", "Minimum": "Minimum", "Options": "Options", "Photo": "Photo", "Communication": "Communication", "Parents": "Parents", "Continent": "Continent", "Configuration": "Configuration", "N/A": "", "options": "options", "page": "page"}
ENDJSON

# Apply
node locale/scripts/locale-translate.js --apply \
  --file locale/terms/missing/fr/fr-1.json \
  --translations "$(cat /tmp/fr-1-trans.json)"

rm /tmp/fr-1-trans.json

# Commit + push
node locale/scripts/locale-branch-manager.js --commit-and-push \
  --locale fr --language "French - France" --terms 12
```

---

## Resume after timeout

Just rerun `/locale-translate --all`. The `--list` command shows which locales still have terms. Already-translated locales will show 0 or 1 remaining (the 1 is usually `N/A`).
