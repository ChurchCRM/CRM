#!/usr/bin/env node
'use strict';

/**
 * Build-time catalogs: write every locale's .po/.mo from its JSON
 * (src/locale/i18n/<locale>.json, the source of truth) for PHP gettext.
 *
 * The generated files under src/locale/textdomain are gitignored. This is the
 * step that produces them: `npm run build` runs it (so the release ZIP and the
 * Docker images carry the catalogs), CI runs it in the lint suite and the
 * locale gate, and a developer runs it after a fresh checkout. It reads the
 * JSON and writes only .po/.mo: no queue batch is consumed and no JSON is
 * rewritten. A locale that ends without a non-empty .mo fails the run, so a
 * missed compile cannot ship as a silent English fallback.
 */

const fs = require('fs');
const repo = require('./lib/locale-repo');
const { preflight } = require('./locale-apply');

preflight();
const entries = repo.loadSource();
if (entries.length === 0) {
    console.error('❌ locale/messages.po has no terms; refusing to compile catalogs');
    process.exit(1);
}

const targets = repo.targetLocales();
const missing = [];
for (const target of targets) {
    repo.writeLocale(repo.loadLocale(target, entries), entries, { catalogsOnly: true });
    const mo = repo.paths(target).mo;
    if (!fs.existsSync(mo) || fs.statSync(mo).size === 0) missing.push(target.code);
}
if (missing.length) {
    console.error(`❌ locale:compile — no .mo produced for: ${missing.join(', ')}`);
    process.exit(1);
}
console.log(`✅ locale:compile — .po/.mo written for ${targets.length} locales`);
