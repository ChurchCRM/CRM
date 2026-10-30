const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const test = require('node:test');

const root = path.join(__dirname, '..');
const allLocales = `portal-member-home portal-member-family portal-member-add-family-member
portal-member-calendar portal-member-calendar-subscribe people-self-register-portal-member
volunteer-portal-schedule volunteer-portal-opportunities kiosk-guest-form kiosk-guest-checked-in
people-family-new-family events-attendance-overview finance-deposit-entry finance-payment-editor
communication-mailing-list volunteer-occurrence-staffing volunteer-assign-picker dashboard-admin
dashboard-sundayschool dashboard-finance dashboard-people dashboard-hero finance-pledge-report
people-directory-list people-family-overview settings-user-permissions`.split(/\s+/).sort();

function discover(locale, project = 'screenshots') {
    const output = execFileSync(process.execPath, [
        require.resolve('@playwright/test/cli'), 'test', '--config=playwright/playwright.config.ts',
        `--project=${project}`, '--no-deps', '--list', '--reporter=json',
    ], { cwd: root, env: { ...process.env, CHURCHCRM_LOCALE: locale }, encoding: 'utf8' });
    const specs = [];
    function visit(suite) {
        specs.push(...(suite.specs || []));
        for (const child of suite.suites || []) visit(child);
    }
    for (const suite of JSON.parse(output).suites) visit(suite);
    return specs;
}

test('discovery narrows non-English captures to the exact epic list', () => {
    const english = discover('en');
    assert.deepEqual(english.filter((spec) => spec.tags.includes('all-locales')).map((spec) => spec.title).sort(), allLocales);
    assert.ok(english.length > allLocales.length);
    for (const locale of ['es', 'pt', 'zh', 'fr', 'ru', 'de', 'ar']) {
        assert.deepEqual(discover(locale).map((spec) => spec.title).sort(), allLocales, locale);
    }
    assert.ok(discover('en', 'recordings').every((spec) => !spec.tags.includes('all-locales')));
});

test('manifest retains locale policy and usedIn and omits unavailable dark twins', () => {
    const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-manifest-'));
    try {
        fs.mkdirSync(path.join(fixture, 'scripts'));
        fs.copyFileSync(path.join(__dirname, 'generate-marketing-manifest.js'), path.join(fixture, 'scripts/generate-marketing-manifest.js'));
        for (const [locale, name, locales, dark] of [
            ['en', 'hero', 'all', 'hero-dark'], ['es', 'hero', 'all', 'hero-dark'],
            ['en', 'hero-dark', 'en', null], ['en', 'overview', undefined, null],
        ]) {
            const metadata = path.join(fixture, 'playwright/artifacts/metadata', locale, 'desktop');
            fs.mkdirSync(metadata, { recursive: true });
            fs.writeFileSync(path.join(metadata, `${name}.json`), JSON.stringify({
                workflow: name, locale, locales, dark, usedIn: ['docs', 'website'], artifact: `${name}.png`,
            }));
        }
        execFileSync(process.execPath, [path.join(fixture, 'scripts/generate-marketing-manifest.js')]);
        const manifest = JSON.parse(fs.readFileSync(path.join(fixture, 'playwright/artifacts/manifest.json')));
        assert.equal(manifest.find((entry) => entry.name === 'hero' && entry.locale === 'es').dark, null);
        assert.equal(manifest.find((entry) => entry.name === 'hero' && entry.locale === 'en').dark, 'hero-dark');
        assert.equal(manifest.find((entry) => entry.name === 'overview').locales, 'en');
        assert.ok(manifest.filter((entry) => entry.name === 'hero').every((entry) => entry.locales === 'all'));
        assert.ok(manifest.every((entry) => entry.usedIn.join(',') === 'docs,website'));
    } finally {
        fs.rmSync(fixture, { recursive: true, force: true });
    }
});
