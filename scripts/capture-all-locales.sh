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

echo "📸 CRM #10048 — capturing ${#LOCALES[@]} locale(s): ${LOCALES[*]}"
echo ""

# ── Pass 1: full install + first locale ──────────────────────────────────
# Same step chain as the `marketing` script in package.json, minus its
# trailing marketing:check/marketing:manifest — those run once at the end
# below, after every locale has captured, not after just the first.
echo "▶ [1/${#LOCALES[@]}] ${FIRST_LOCALE} — full install + capture"
rm -rf playwright/artifacts/
npm run composer:install
npm run build:js
npm run docker:ci:new-system:start
npm run docker:ci:new-system:reset:db
npm run build:signatures
if [ -n "$BROWSER_CHANNEL" ]; then
  CHURCHCRM_LOCALE="$FIRST_LOCALE" BROWSER_CHANNEL="$BROWSER_CHANNEL" npm run marketing:screenshots
else
  CHURCHCRM_LOCALE="$FIRST_LOCALE" npm run marketing:screenshots
fi
npm run marketing:videos

# ── Passes 2..N: re-apply locale, re-capture, no reinstall ───────────────
i=2
for locale in "${REMAINING_LOCALES[@]}"; do
  echo ""
  echo "▶ [${i}/${#LOCALES[@]}] ${locale} — locale switch + capture (no reinstall)"
  if [ -n "$BROWSER_CHANNEL" ]; then
    CHURCHCRM_LOCALE="$locale" BROWSER_CHANNEL="$BROWSER_CHANNEL" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=locale-set
  else
    CHURCHCRM_LOCALE="$locale" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=locale-set
  fi

  if [ -n "$BROWSER_CHANNEL" ]; then
    CHURCHCRM_LOCALE="$locale" BROWSER_CHANNEL="$BROWSER_CHANNEL" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=screenshots \
      --no-deps
  else
    CHURCHCRM_LOCALE="$locale" npx playwright test \
      --config=playwright/playwright.config.ts \
      --project=screenshots \
      --no-deps
  fi
  i=$((i + 1))
done

echo ""
echo "▶ Validating captures"
npm run marketing:check

echo ""
echo "▶ Regenerating manifest.json (once, across all locales)"
npm run marketing:manifest

echo ""
echo "✅ Done. Expect ~175 PNGs across 8 locales — verify with:"
echo "   find playwright/artifacts/screenshots -name '*.png' | wc -l"
echo "   node -e \"const m=require('./playwright/artifacts/manifest.json'); const byLocale={}; m.forEach(e=>byLocale[e.locale]=(byLocale[e.locale]||0)+1); console.log(byLocale)\""
