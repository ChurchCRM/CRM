#!/usr/bin/env node

/**
 * Capture every screenshot in English and the key captures in the other locales.
 * Installs once, then switches locale without reinstalling.
 * Usage: node scripts/marketing/capture-all-locales.js [locale ...]
 * Defaults to en es pt zh fr ru de ar. BROWSER_CHANNEL=chrome uses Chrome.
 */

const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');
const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
const npx = process.platform === 'win32' ? 'npx.cmd' : 'npx';
const browserChannel = process.env.BROWSER_CHANNEL || '';
const locales = process.argv.slice(2);
const selected = locales.length > 0 ? locales : ['en', 'es', 'pt', 'zh', 'fr', 'ru', 'de', 'ar'];

function runStep(name, command, args, env) {
    console.error(`  ↳ ${name}`);
    const result = spawnSync(command, args, {
        cwd: root,
        env: { ...process.env, ...env },
        stdio: 'inherit',
        shell: process.platform === 'win32',
    });
    if (result.status !== 0) {
        console.error(`❌ ${name} failed`);
        process.exit(result.status || 1);
    }
}

function playwright(name, args, locale) {
    const env = { CHURCHCRM_LOCALE: locale };
    if (browserChannel) {
        env.BROWSER_CHANNEL = browserChannel;
    }
    runStep(name, npx, ['playwright', 'test', '--config=playwright/playwright.config.ts', ...args], env);
}

console.error(`📸 CRM #10048 — capturing ${selected.length} locale(s): ${selected.join(' ')}`);
console.error('');

const first = selected[0];
console.error(`▶ [1/${selected.length}] ${first} — full install + capture`);
console.error('  ↳ Removing old artifacts');
fs.rmSync(path.join(root, 'playwright', 'artifacts'), { recursive: true, force: true });

runStep('Tearing down old Docker CI environment', npm, ['run', 'docker:ci:new-system:down']);

runStep('Installing Composer dependencies', npm, ['run', 'composer:install']);
runStep('Building JavaScript', npm, ['run', 'build:webpack']);
runStep('Starting Docker CI environment', npm, ['run', 'docker:ci:new-system:start']);
runStep('Building signatures', npm, ['run', 'build:signatures']);

if (first === 'en') {
    runStep('marketing:screenshots (en)', npm, ['run', 'marketing:screenshots'], {
        CHURCHCRM_LOCALE: first,
        BROWSER_CHANNEL: browserChannel,
    });
    runStep('Generating marketing videos', npm, ['run', 'marketing:videos']);
} else {
    playwright(`Capturing screenshots for ${first}`, ['--project=screenshots', '--grep', '@all-locales'], first);
    console.error('  ↳ Dropping unpublished video metadata');
    fs.rmSync(path.join(root, 'playwright', 'artifacts', 'metadata', 'setup'), { recursive: true, force: true });
    fs.rmSync(path.join(root, 'playwright', 'artifacts', 'metadata', 'recordings'), { recursive: true, force: true });
}

selected.slice(1).forEach((locale, index) => {
    const step = index + 2;
    console.error('');
    console.error(`▶ [${step}/${selected.length}] ${locale} — locale switch + capture (no reinstall)`);
    playwright(`Switching to locale: ${locale}`, ['--project=locale-set'], locale);
    playwright(`Capturing screenshots for ${locale}`, ['--project=screenshots', '--no-deps', '--grep', '@all-locales'], locale);
});

console.error('');
console.error('▶ Validating captures');
runStep('Validating captures', npm, ['run', 'marketing:check']);
console.error('');
console.error('▶ Regenerating manifest.json (once, across all locales)');
runStep('Regenerating manifest', npm, ['run', 'marketing:manifest']);
console.error('');
console.error('✅ Done. English captures plus key captures across the other locales — verify with:');
console.error("   find playwright/artifacts/screenshots -name '*.png' | wc -l");
console.error('   node -e "const m=require(\'./playwright/artifacts/manifest.json\'); const byLocale={}; m.forEach(e=>byLocale[e.locale]=(byLocale[e.locale]||0)+1); console.log(byLocale)"');
