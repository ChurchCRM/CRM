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
const browserChannel = process.env.BROWSER_CHANNEL || '';
const locales = process.argv.slice(2);
const selected = locales.length > 0 ? locales : ['en', 'es', 'pt', 'zh', 'fr', 'ru', 'de', 'ar'];
const english = selected.indexOf('en');
if (english > 0) {
    selected.splice(english, 1);
    selected.unshift('en');
}

function runNpm(name, args, env) {
    console.error(`  ↳ ${name}`);
    const result = spawnSync('npm', args, {
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
    console.error(`  ↳ ${name}`);
    const result = spawnSync('npx', ['playwright', 'test', '--config=playwright/playwright.config.ts', ...args], {
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

console.error(`📸 CRM #10048 — capturing ${selected.length} locale(s): ${selected.join(' ')}`);
console.error('');

const first = selected[0];
console.error(`▶ [1/${selected.length}] ${first} — full install + capture`);
console.error('  ↳ Removing old artifacts');
fs.rmSync(path.join(root, 'playwright', 'artifacts'), { recursive: true, force: true });

runNpm('Tearing down old Docker CI environment', ['run', 'docker:ci:new-system:down']);

runNpm('Installing Composer dependencies', ['run', 'composer:install']);
runNpm('Building JavaScript', ['run', 'build:webpack']);
runNpm('Starting Docker CI environment', ['run', 'docker:ci:new-system:start']);
runNpm('Building signatures', ['run', 'build:signatures']);

if (first === 'en') {
    runNpm('marketing:screenshots (en)', ['run', 'marketing:screenshots'], {
        CHURCHCRM_LOCALE: first,
        BROWSER_CHANNEL: browserChannel,
    });
    runNpm('Generating marketing videos', ['run', 'marketing:videos']);
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
runNpm('Validating captures', ['run', 'marketing:check']);
console.error('');
console.error('▶ Regenerating manifest.json (once, across all locales)');
runNpm('Regenerating manifest', ['run', 'marketing:manifest']);
console.error('');
console.error('✅ Done. English captures plus key captures across the other locales — verify with:');
console.error("   find playwright/artifacts/screenshots -name '*.png' | wc -l");
console.error('   node -e "const m=require(\'./playwright/artifacts/manifest.json\'); const byLocale={}; m.forEach(e=>byLocale[e.locale]=(byLocale[e.locale]||0)+1); console.log(byLocale)"');
