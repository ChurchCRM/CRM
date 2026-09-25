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

set -euo pipefail

# Ensure all output is unbuffered and visible immediately
export PYTHONUNBUFFERED=1
exec 1> >(tee -a /dev/stderr)

CRM_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$CRM_DIR"

# Trap to log exits
trap 'echo "❌ Script exited with status $?" >&2' EXIT

if [ "$#" -gt 0 ]; then
  LOCALES=("$@")
else
  LOCALES=(en es pt zh fr ru de ar)
fi

# Optional browser channel (e.g., 'chrome')
BROWSER_CHANNEL="${BROWSER_CHANNEL:-}"

FIRST_LOCALE="${LOCALES[0]}"
REMAINING_LOCALES=("${LOCALES[@]:1}")

echo "📸 CRM #10048 — capturing ${#LOCALES[@]} locale(s): ${LOCALES[*]}"
echo ""

# ── Pass 1: full install + first locale ──────────────────────────────────
# Same step chain as the `marketing` script in package.json, minus its
# trailing marketing:check/marketing:manifest — those run once at the end
# below, after every locale has captured, not after just the first.
echo "▶ [1/${#LOCALES[@]}] ${FIRST_LOCALE} — full install + capture"

echo "  ↳ Removing old artifacts..."
rm -rf playwright/artifacts/

echo "  ↳ Installing Composer dependencies..."
npm run composer:install || { echo "❌ composer:install failed"; exit 1; }

echo "  ↳ Building JavaScript..."
npm run build:js || { echo "❌ build:js failed"; exit 1; }

echo "  ↳ Starting Docker CI environment..."
npm run docker:ci:new-system:start || { echo "❌ docker:ci:new-system:start failed"; exit 1; }

echo "  ↳ Building signatures..."
npm run build:signatures || { echo "❌ build:signatures failed"; exit 1; }

echo "  ↳ Capturing marketing screenshots for ${FIRST_LOCALE}..."
if [ -n "$BROWSER_CHANNEL" ]; then
  CHURCHCRM_LOCALE="$FIRST_LOCALE" BROWSER_CHANNEL="$BROWSER_CHANNEL" npm run marketing:screenshots || { echo "❌ marketing:screenshots failed"; exit 1; }
else
  CHURCHCRM_LOCALE="$FIRST_LOCALE" npm run marketing:screenshots || { echo "❌ marketing:screenshots failed"; exit 1; }
fi

echo "  ↳ Generating marketing videos..."
npm run marketing:videos || { echo "❌ marketing:videos failed"; exit 1; }

# ── Passes 2..N: re-apply locale, re-capture, no reinstall ───────────────
i=2
for locale in "${REMAINING_LOCALES[@]}"; do
  echo ""
  echo "▶ [${i}/${#LOCALES[@]}] ${locale} — locale switch + capture (no reinstall)"

  echo "  ↳ Switching to locale: ${locale}..."
  if [ -n "$BROWSER_CHANNEL" ]; then
    CHURCHCRM_LOCALE="$locale" BROWSER_CHANNEL="$BROWSER_CHANNEL" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=locale-set || { echo "❌ locale-set for ${locale} failed"; exit 1; }
  else
    CHURCHCRM_LOCALE="$locale" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=locale-set || { echo "❌ locale-set for ${locale} failed"; exit 1; }
  fi

  echo "  ↳ Capturing screenshots for ${locale}..."
  if [ -n "$BROWSER_CHANNEL" ]; then
    CHURCHCRM_LOCALE="$locale" BROWSER_CHANNEL="$BROWSER_CHANNEL" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=screenshots \
      --no-deps || { echo "❌ screenshots for ${locale} failed"; exit 1; }
  else
    CHURCHCRM_LOCALE="$locale" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=screenshots \
      --no-deps || { echo "❌ screenshots for ${locale} failed"; exit 1; }
  fi
  i=$((i + 1))
done

echo ""
echo "▶ Validating captures"
npm run marketing:check || { echo "❌ marketing:check failed"; exit 1; }

echo ""
echo "▶ Regenerating manifest.json (once, across all locales)"
npm run marketing:manifest || { echo "❌ marketing:manifest failed"; exit 1; }

echo ""
echo "✅ Done. Expect ~175 PNGs across 8 locales — verify with:"
echo "   find playwright/artifacts/screenshots -name '*.png' | wc -l"
echo "   node -e \"const m=require('./playwright/artifacts/manifest.json'); const byLocale={}; m.forEach(e=>byLocale[e.locale]=(byLocale[e.locale]||0)+1); console.log(byLocale)\""
