#!/usr/bin/env node

/**
 * Locale Translation Branch Manager
 *
 * Manages git branch creation, detection, and resumption for /locale-translate skill.
 * Prevents data loss on cloud system timeouts by:
 * - Creating a dedicated locale branch (locale/{VERSION}-{DATE})
 * - Committing and pushing after every locale
 * - Supporting resume from interrupted sessions
 *
 * Usage:
 *   node locale/scripts/locale-branch-manager.js --init --version <ver>
 *   node locale/scripts/locale-branch-manager.js --current
 *   node locale/scripts/locale-branch-manager.js --is-locale-branch
 *   node locale/scripts/locale-branch-manager.js --get-version
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

// Helpers
function run(program, args = [], options = {}) {
    try {
        const out = execFileSync(program, args, { encoding: 'utf8', ...options });
        return out.trim();
    } catch (err) {
        if (options.allowFail) return null;
        throw err;
    }
}

function sanitize(str) {
    return String(str).replace(/[\r\n]/g, ' ');
}

/**
 * Get today's date in YYYY-MM-DD format
 */
function getTodayDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

/**
 * Get current time in HHMMSS format (UTC) — used to guarantee a unique
 * branch name per invocation so translation sessions never reuse an
 * existing branch (see /locale-translate docs: "always create a new branch").
 */
function getCurrentTime() {
    const now = new Date();
    const h = String(now.getUTCHours()).padStart(2, '0');
    const m = String(now.getUTCMinutes()).padStart(2, '0');
    const s = String(now.getUTCSeconds()).padStart(2, '0');
    return `${h}${m}${s}`;
}

/**
 * Auto-detect version from package.json or release notes
 */
function getAutoVersion() {
    // Try package.json first
    const packageJsonPath = path.join(__dirname, '../../package.json');
    if (fs.existsSync(packageJsonPath)) {
        try {
            const pkg = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
            return pkg.version;
        } catch (e) {
            // Ignore errors
        }
    }

    // Try git describe (if in a tag)
    try {
        const tag = run('git', ['describe', '--tags', '--abbrev=0'], { allowFail: true });
        if (tag && /^\d+\.\d+\.\d+/.test(tag)) {
            return tag.replace(/^v/, '').split('-')[0]; // strip 'v' prefix and '-*' suffixes
        }
    } catch (e) {
        // Ignore
    }

    // Fallback: prompt user
    console.error('⚠️  Could not auto-detect version from package.json or git tags');
    console.error('    Pass --version manually: --version 7.1.0');
    process.exit(1);
}

/**
 * Build branch name from version, date, and time.
 * A HHMMSS suffix is appended to guarantee each invocation produces a
 * fresh, unique branch — never reusing a prior day's or prior run's branch.
 * Example: locales/7.1.0-2026-04-01-174530
 */
function buildBranchName(version) {
    const date = getTodayDate();
    const time = getCurrentTime();
    return `locales/${version}-${date}-${time}`;
}

/**
 * Check if current branch is a locale branch.
 * Accepts both the current `locales/{v}-YYYY-MM-DD-HHMMSS` form and the
 * legacy `locale/{v}-YYYY-MM-DD` form (so existing in-flight branches
 * still detect correctly during the rollout).
 */
function isLocaleBranch(branchName) {
    return /^locales?\/[\w.-]+-\d{4}-\d{2}-\d{2}(?:-\d{6})?$/.test(branchName);
}

/**
 * Extract version from locale branch name.
 * Handles both the current `locales/{v}-YYYY-MM-DD-HHMMSS` form and the
 * legacy `locale/{v}-YYYY-MM-DD` form.
 * Example: locales/7.1.0-2026-04-01-174530 → 7.1.0
 */
function extractVersionFromBranch(branchName) {
    const match = branchName.match(/^locales?\/([\w.-]+)-\d{4}-\d{2}-\d{2}(?:-\d{6})?$/);
    return match ? match[1] : null;
}

/**
 * Get current branch name
 */
function getCurrentBranch() {
    return run('git', ['rev-parse', '--abbrev-ref', 'HEAD'], { allowFail: true }) || 'master';
}

/**
 * Initialize locale translation branch.
 *
 * Always creates a brand-new branch with a unique timestamp suffix.
 * We do NOT reuse an existing `locale/*` branch — every translation
 * session must start on a fresh branch so prior sessions remain
 * immutable and reviewable. If the current branch is already a
 * `locale/*` branch (e.g. a resumed session mid-run), we still cut
 * away to a new one: callers that genuinely want to continue a
 * branch should `git checkout` it themselves and skip `--init`.
 *
 * Auto-detects version from package.json if not provided.
 */
function initBranch(version) {
    const current = getCurrentBranch();

    if (isLocaleBranch(current)) {
        console.log(`ℹ️  Currently on locale branch: ${current}`);
        console.log(`    --init always creates a NEW branch; branching from current HEAD.`);
    }

    // Auto-detect version if not provided
    if (!version) {
        version = getAutoVersion();
        console.log(`ℹ️  Auto-detected version: ${version}`);
    }

    const branchName = buildBranchName(version);

    // Safety: with a HHMMSS suffix collisions are effectively impossible,
    // but if one somehow exists on the remote, fail loudly rather than
    // silently reuse it.
    const existsRemote = run('git', ['ls-remote', '--heads', 'origin', branchName], { allowFail: true });
    if (existsRemote) {
        console.error(`❌ Branch ${branchName} already exists on remote.`);
        console.error(`   Refusing to reuse. Re-run in a moment for a new timestamp.`);
        process.exit(1);
    }

    console.log(`🌿 Creating new locale branch: ${branchName}`);
    run('git', ['checkout', '-b', branchName]);
    run('git', ['push', '-u', 'origin', branchName]);
    return branchName;
}

/**
 * Commit and push translations for a locale
 */
function commitAndPush(localeCode, languageName, termCount) {
    const branch = getCurrentBranch();
    const message = `locale: translate ${localeCode} (${languageName}, ${termCount} terms)`;

    console.log(`\n  📝 Committing to ${branch}...`);
    run('git', ['add', `locale/terms/missing/${localeCode}/`]);
    run('git', ['commit', '-m', message]);

    console.log(`  ⬆️  Pushing to origin/${branch}...`);
    run('git', ['push', 'origin', branch]);

    console.log(`  ✅ Committed and pushed\n`);
}

/**
 * Get list of already-translated locales on current branch
 * by checking what has been pushed
 */
function getTranslatedLocales() {
    const branch = getCurrentBranch();
    if (!isLocaleBranch(branch)) return [];

    // Get commits unique to this branch vs master
    const commits = run(
        'git', ['log', `origin/master..origin/${branch}`, '--pretty=format:%B'],
        { allowFail: true }
    );
    if (!commits) return [];

    // Extract locale codes from commit messages
    // Format: "locale: translate xx (Language Name, NNN terms)"
    const regex = /locale: translate (\w+(-\w+)?)/g;
    const locales = [];
    let match;
    while ((match = regex.exec(commits)) !== null) {
        locales.push(match[1]);
    }
    return [...new Set(locales)]; // dedupe
}

// ── CLI ──────────────────────────────────────────────────────────────────────

function parseArgs() {
    const args = process.argv.slice(2);
    const opts = { command: null, version: null };

    for (let i = 0; i < args.length; i++) {
        switch (args[i]) {
            case '--init':
                opts.command = 'init';
                break;
            case '--current':
                opts.command = 'current';
                break;
            case '--is-locale-branch':
                opts.command = 'is-locale-branch';
                break;
            case '--get-version':
                opts.command = 'get-version';
                break;
            case '--commit-and-push':
                opts.command = 'commit-and-push';
                break;
            case '--get-translated':
                opts.command = 'get-translated';
                break;
            case '--version':
                opts.version = args[++i];
                break;
            case '--locale':
                opts.locale = args[++i];
                break;
            case '--language':
                opts.language = args[++i];
                break;
            case '--terms':
                opts.terms = args[++i];
                break;
            case '--help':
            case '-h':
                console.log(`
ChurchCRM Locale Translation Branch Manager

Usage:
  node locale/scripts/locale-branch-manager.js --init [--version <version>]
    Create a brand-new locale translation branch with a unique timestamp.
    Never reuses an existing branch — every session gets a fresh one.
    Version is auto-detected from package.json if not provided.
    Examples:
      --init                          (auto-detect version)
      --init --version 7.1.0          (explicit version)
    Output: locales/7.1.0-2026-04-01-174530  (YYYY-MM-DD-HHMMSS)

  node locale/scripts/locale-branch-manager.js --current
    Get current branch name

  node locale/scripts/locale-branch-manager.js --is-locale-branch
    Check if current branch is a locale branch (exit code 0=yes, 1=no)

  node locale/scripts/locale-branch-manager.js --get-version
    Extract version from current locale branch (e.g., 7.1.0)

  node locale/scripts/locale-branch-manager.js --commit-and-push \\
    --locale <code> --language "<name>" --terms <count>
    Commit and push translations for one locale
    Example: --commit-and-push --locale fr --language "French - France" --terms 154

  node locale/scripts/locale-branch-manager.js --get-translated
    List locale codes that have been translated on current branch

  node locale/scripts/locale-branch-manager.js --help
    Show this help message
`);
                process.exit(0);
        }
    }
    return opts;
}

function main() {
    const opts = parseArgs();

    try {
        switch (opts.command) {
            case 'init':
                // Version is optional — auto-detected if not provided
                const branch = initBranch(opts.version || null);
                console.log(JSON.stringify({ branch }, null, 2));
                break;

            case 'current':
                const current = getCurrentBranch();
                console.log(JSON.stringify({ branch: current }, null, 2));
                break;

            case 'is-locale-branch':
                const current2 = getCurrentBranch();
                const isLocale = isLocaleBranch(current2);
                console.log(JSON.stringify({ isLocaleBranch: isLocale }, null, 2));
                process.exit(isLocale ? 0 : 1);
                break;

            case 'get-version':
                const current3 = getCurrentBranch();
                const version = extractVersionFromBranch(current3);
                if (!version) {
                    console.error(`❌ Not on a locale branch: ${current3}`);
                    process.exit(1);
                }
                console.log(JSON.stringify({ version }, null, 2));
                break;

            case 'commit-and-push':
                if (!opts.locale || !opts.language || !opts.terms) {
                    console.error('❌ --locale, --language, and --terms required');
                    process.exit(1);
                }
                commitAndPush(opts.locale, opts.language, opts.terms);
                break;

            case 'get-translated':
                const translated = getTranslatedLocales();
                console.log(JSON.stringify({ translated }, null, 2));
                break;

            default:
                console.error('❌ Specify --init, --current, --is-locale-branch, --get-version, --commit-and-push, or --get-translated');
                process.exit(1);
        }
    } catch (err) {
        console.error(`\n❌ Fatal error: ${sanitize(err.message)}`);
        process.exit(1);
    }
}

main();
