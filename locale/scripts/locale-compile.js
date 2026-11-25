#!/usr/bin/env node
'use strict';

/**
 * Build-time locale compilation: generate every locale's .po/.mo from the
 * checked-in JSON (src/locale/i18n/<locale>.json, the source of truth).
 *
 * The generated files under src/locale/textdomain are NOT committed (see
 * .gitignore). This is the step that produces them: it runs as part of
 * `npm run build` (so the release package and the Docker images built from
 * it carry the catalogs), in CI before the test stacks start, and as the
 * lint suite's "locale files in sync" check.
 *
 * After compiling, every locale target must have a non-empty .mo; a missing
 * one fails the build. That failure is the point: without it, a missed
 * compile would surface only as the app silently falling back to English.
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

execFileSync(process.execPath, [path.join(__dirname, 'locale-apply.js')], { stdio: 'inherit' });

const repo = require('./lib/locale-repo');
const targets = repo.targetLocales();
const missing = [];
for (const target of targets) {
    const mo = repo.paths(target).mo;
    if (!fs.existsSync(mo) || fs.statSync(mo).size === 0) missing.push(target.code);
}
if (missing.length) {
    console.error(`❌ locale:compile — no .mo produced for: ${missing.join(', ')}`);
    process.exit(1);
}
console.log(`✅ locale:compile — .po/.mo generated for ${targets.length} locales`);
