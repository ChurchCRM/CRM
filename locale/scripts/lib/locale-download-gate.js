'use strict';

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '../../..');

// What Locale: sync is allowed to commit. Anything else stays a PR for a person.
const ALLOWED = [
    /^src\/locale\/i18n\/[^/]+\.json$/,
    /^src\/locale\/textdomain\/[^/]+\/LC_MESSAGES\/messages\.(po|mo)$/,
    /^locale\/terms\/missing\/[^/]+\/[^/]+\.json$/,
];

// The base locale is an empty file. Every other JSON file must parse.
const EMPTY_OK = new Set(['src/locale/i18n/en_US.json']);

function isAllowedPath(file) {
    return ALLOWED.some((pattern) => pattern.test(file));
}

function parsePorcelain(porcelain) {
    const files = [];
    for (const line of porcelain.split('\n')) {
        if (line === '') {
            continue;
        }
        const status = line.slice(0, 2);
        let file = line.slice(3);
        if (file.includes(' -> ')) {
            const [from, to] = file.split(' -> ');
            files.push({ path: from, deleted: true });
            file = to;
        }
        const deleted = status.includes('D');
        files.push({ path: file, deleted });
    }
    return files;
}

function localeDownloadGate(files, readText) {
    if (files.length === 0) {
        return { merge: false, reasons: ['no locale changes'] };
    }

    const reasons = [];
    for (const file of files) {
        if (!isAllowedPath(file.path)) {
            reasons.push(`unexpected path: ${file.path}`);
            continue;
        }
        if (file.deleted || !file.path.endsWith('.json')) {
            continue;
        }

        let text;
        try {
            text = readText(file.path);
        } catch {
            reasons.push(`unreadable json: ${file.path}`);
            continue;
        }
        if (text.trim() === '' && EMPTY_OK.has(file.path)) {
            continue;
        }
        try {
            JSON.parse(text);
        } catch {
            reasons.push(`invalid json: ${file.path}`);
        }
    }

    return { merge: reasons.length === 0, reasons };
}

function main() {
    const porcelain = execFileSync(
        'git',
        [
            'status',
            '--porcelain',
            '--untracked-files=all',
            '--',
            'src/locale/i18n',
            'src/locale/textdomain',
            'locale/terms/missing',
        ],
        { cwd: ROOT, encoding: 'utf8' },
    );
    const result = localeDownloadGate(parsePorcelain(porcelain), (file) =>
        fs.readFileSync(path.join(ROOT, file), 'utf8'),
    );

    if (process.env.GITHUB_OUTPUT) {
        fs.appendFileSync(process.env.GITHUB_OUTPUT, `merge=${result.merge}\n`);
    }
    if (result.merge) {
        console.log('Locale download can merge');
        return;
    }
    console.log('Locale download stays open for review:');
    for (const reason of result.reasons) {
        console.log(`  ${reason}`);
    }
}

if (require.main === module) {
    main();
}

module.exports = { localeDownloadGate, parsePorcelain, isAllowedPath };
