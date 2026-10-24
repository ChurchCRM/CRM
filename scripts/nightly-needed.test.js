const test = require('node:test');
const assert = require('node:assert');
const fs = require('fs');
const path = require('path');
const { decide, globToRegExp, isTestable, pushPatterns } = require('./nightly-needed');

const patterns = pushPatterns(fs.readFileSync(path.join(__dirname, '..', '.github', 'workflows', 'build-test-package.yml'), 'utf8'));
const NEW = 'b'.repeat(40);
const OLD = 'a'.repeat(40);
const scheduled = (changedFiles, extra = {}) => decide({ event: 'schedule', sha: NEW, lastSha: OLD, changedFiles, patterns, ...extra });

test('glob matching follows GitHub: ** crosses folders, * does not', () => {
    assert.ok(globToRegExp('**/*.md').test('src/a/b.md'));
    assert.ok(globToRegExp('**/*.md').test('README.md'));
    assert.ok(!globToRegExp('docs/*').test('docs/a/b'));
    assert.ok(globToRegExp('docs/**').test('docs/a/b'));
});

test('the push rules read from build-test-package.yml', () => {
    assert.ok(!isTestable('docs/design/x.md', patterns));
    assert.ok(!isTestable('.github/workflows/issue-management.yml', patterns));
    assert.ok(!isTestable('playwright/artifacts/manifest.json', patterns));
    assert.ok(isTestable('src/FamilyEditor.php', patterns));
    assert.ok(isTestable('cypress/e2e/ui/a.spec.js', patterns));
    assert.ok(isTestable('src/locale/i18n/de_DE.json', patterns), 'a push builds translations');
    assert.ok(isTestable('.github/workflows/build-test-nightly.yml', patterns), 'a later pattern re-includes the test workflows');
    assert.ok(isTestable('.github/workflows/build-test-package.yml', patterns));
});

test('manual, release and push runs always run', () => {
    for (const event of ['workflow_dispatch', 'release', 'push']) {
        assert.strictEqual(decide({ event, sha: NEW, lastSha: NEW, changedFiles: [], patterns }).run, true);
    }
});

test('a scheduled run with no earlier green run runs', () => {
    assert.strictEqual(scheduled([], { lastSha: null }).run, true);
});

test('a scheduled run on a commit that already passed is skipped', () => {
    assert.strictEqual(scheduled([], { lastSha: NEW }).run, false);
});

test('a scheduled run skips when only ignorable files changed', () => {
    assert.strictEqual(scheduled(['docs/a.md', '.coderabbit.yaml', 'playwright/x.png']).run, false);
});

test('a scheduled run runs when one testable file changed', () => {
    const decision = scheduled(['docs/a.md', 'src/api/routes/cart.php']);
    assert.strictEqual(decision.run, true);
    assert.match(decision.reason, /src\/api\/routes\/cart\.php/);
});

test('an unlisted or very large change runs', () => {
    assert.strictEqual(scheduled(null).run, true);
    assert.strictEqual(scheduled(Array.from({ length: 300 }, (_, i) => `docs/${i}.md`)).run, true);
});
