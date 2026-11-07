'use strict';

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '../../..');
const I18N = 'src/locale/i18n';

// en_US.json is the base locale and is always empty.
function emptyDownloads(files) {
    return files.filter((file) => path.basename(file.name) !== 'en_US.json' && file.size === 0).map((file) => file.name);
}

// A locale that shrank from substantial to near-empty means the POEditor download failed for it.
function regressions(files) {
    return files.filter((file) => file.now < 50 && file.before > 500);
}

function integrity(dir = path.join(ROOT, I18N)) {
    const files = fs.readdirSync(dir).filter((name) => name.endsWith('.json'));
    if (files.length === 0) {
        return ['No JSON locale files found after download'];
    }
    const empty = emptyDownloads(files.map((name) => ({ name, size: fs.statSync(path.join(dir, name)).size })));
    return empty.length ? [`Empty JSON locale files, so the download failed for them: ${empty.join(', ')}`] : [];
}

// Locales the download rewrote, with their size now and in the checked-out commit.
// `cat-file -s` returns the blob size. `git show` of a locale over 1 MiB overflows execFileSync.
function modifiedLocales(root = ROOT) {
    const git = (...args) => execFileSync('git', args, { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
    const names = git('diff', '--name-only', '--diff-filter=M').split('\n');
    return names
        .filter((name) => new RegExp(`^${I18N}/.*\\.json$`).test(name) && fs.existsSync(path.join(root, name)))
        .map((name) => ({ name, now: fs.statSync(path.join(root, name)).size, before: Number(git('cat-file', '-s', `HEAD:${name}`)) }));
}

if (require.main === module) {
    const check = process.argv[2];
    const problems =
        check === 'integrity'
            ? integrity()
            : check === 'regressions'
              ? regressions(modifiedLocales()).map((f) => `${f.name} (was ${f.before} bytes, now ${f.now} bytes)`)
              : ['Usage: locale-download-check.js integrity|regressions'];
    if (problems.length) {
        console.error(`❌ ${problems.join('\n   ')}`);
        process.exit(1);
    }
    console.log(`✅ locale download ${check} check passed`);
}

module.exports = { emptyDownloads, integrity, modifiedLocales, regressions };
