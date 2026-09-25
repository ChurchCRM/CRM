#!/bin/bash
#
# CRM #10048 — captures all 7 marketing screenshots in all 8 locales,
# 3 device sizes each, then validates and regenerates the manifest once
# at the end.
#
# Sequencing note (why this isn't just an 8x loop of `npm run marketing`):
#   - `npm run marketing` wipes playwright/artifacts/ and reinstalls
#     ChurchCRM from scratch every time — fine once, wrong 8 times.
#   - The 'setup' project (setup wizard + demo-data-import) can only run
#     once, against a *fresh* install — it cannot be repeated against an
#     already-installed instance.
#   - ChurchCRM resolves locale per-request from a DB-stored user
#     preference (see support/locale-session.ts), so switching locales
#     after the one install just means calling that setting API again —
#     no reinstall, no re-login, no re-seeding needed.
#
# So: install once (first locale, the same step chain `npm run marketing`
# uses, minus its trailing check/manifest steps), then for every
# remaining locale, re-apply the locale via the 'locale-set' project and
# re-run 'screenshots' with --no-deps so neither re-triggers 'setup'.
# Requires `npm run marketing:install` (Playwright browser binaries) to
# have already been run at least once on this machine — that's a one-time
# environment step, not part of this capture run.
#
# Usage: ./scripts/capture-all-locales.sh [locale ...]
#   Defaults to all 8 CRM #10048 locales if none are given.
#   Optional environment variables:
#     BROWSER_CHANNEL=chrome  (to use Chrome instead of Chromium)

#!/bin/bash
set -euo pipefail

# Ensure all output is unbuffered and visible immediately
export PYTHONUNBUFFERED=1

# Function to log and execute commands
run_step() {
  local step_name="$1"
  shift
  echo "  ↳ $step_name" >&2
  "$@" || { echo "❌ $step_name failed" >&2; exit 1; }
}

CRM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$CRM_DIR"

if [ "$#" -gt 0 ]; then
  LOCALES=("$@")
else
  LOCALES=(en es pt zh fr ru de ar)
fi

# Optional browser channel (e.g., 'chrome')
BROWSER_CHANNEL="${BROWSER_CHANNEL:-}"

FIRST_LOCALE="${LOCALES[0]}"
REMAINING_LOCALES=("${LOCALES[@]:1}")

echo "📸 CRM #10048 — capturing ${#LOCALES[@]} locale(s): ${LOCALES[*]}" >&2
echo "" >&2

# ── Pass 1: full install + first locale ──────────────────────────────────
# Same step chain as the `marketing` script in package.json, minus its
# trailing marketing:check/marketing:manifest — those run once at the end
# below, after every locale has captured, not after just the first.
echo "▶ [1/${#LOCALES[@]}] ${FIRST_LOCALE} — full install + capture" >&2

run_step "Removing old artifacts" rm -rf playwright/artifacts/
run_step "Installing Composer dependencies" npm run composer:install
run_step "Building JavaScript" npm run build:js
run_step "Starting Docker CI environment" npm run docker:ci:new-system:start
run_step "Building signatures" npm run build:signatures

echo "  ↳ Capturing marketing screenshots for ${FIRST_LOCALE}..." >&2
if [ -n "$BROWSER_CHANNEL" ]; then
  run_step "marketing:screenshots (${FIRST_LOCALE})" env CHURCHCRM_LOCALE="$FIRST_LOCALE" BROWSER_CHANNEL="$BROWSER_CHANNEL" npm run marketing:screenshots
else
  run_step "marketing:screenshots (${FIRST_LOCALE})" env CHURCHCRM_LOCALE="$FIRST_LOCALE" npm run marketing:screenshots
fi

run_step "Generating marketing videos" npm run marketing:videos

# ── Passes 2..N: re-apply locale, re-capture, no reinstall ───────────────
i=2
for locale in "${REMAINING_LOCALES[@]}"; do
  echo "" >&2
  echo "▶ [${i}/${#LOCALES[@]}] ${locale} — locale switch + capture (no reinstall)" >&2

  if [ -n "$BROWSER_CHANNEL" ]; then
    run_step "Switching to locale: ${locale}" env CHURCHCRM_LOCALE="$locale" BROWSER_CHANNEL="$BROWSER_CHANNEL" npx playwright test --config=playwright/playwright.config.ts --project=locale-set
  else
    run_step "Switching to locale: ${locale}" env CHURCHCRM_LOCALE="$locale" npx playwright test --config=playwright/playwright.config.ts --project=locale-set
  fi

  if [ -n "$BROWSER_CHANNEL" ]; then
    run_step "Capturing screenshots for ${locale}" env CHURCHCRM_LOCALE="$locale" BROWSER_CHANNEL="$BROWSER_CHANNEL" npx playwright test --config=playwright/playwright.config.ts --project=screenshots --no-deps
  else
    run_step "Capturing screenshots for ${locale}" env CHURCHCRM_LOCALE="$locale" npx playwright test --config=playwright/playwright.config.ts --project=screenshots --no-deps
  fi
  i=$((i + 1))
done

echo "" >&2
echo "▶ Validating captures" >&2
run_step "Validating captures" npm run marketing:check

echo "" >&2
echo "▶ Regenerating manifest.json (once, across all locales)" >&2
run_step "Regenerating manifest" npm run marketing:manifest

echo "" >&2
echo "✅ Done. Expect ~175 PNGs across 8 locales — verify with:" >&2
echo "   find playwright/artifacts/screenshots -name '*.png' | wc -l" >&2
echo "   node -e \"const m=require('./playwright/artifacts/manifest.json'); const byLocale={}; m.forEach(e=>byLocale[e.locale]=(byLocale[e.locale]||0)+1); console.log(byLocale)\"" >&2
