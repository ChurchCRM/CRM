#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const picomatch = require('picomatch');

const root = process.cwd();
const IGNORE_DIRS = ['node_modules', '.git', 'vendor', 'src/vendor', 'src/locale/vendor', 'src/skin/external', 'src/skin/v2', 'src/skin/icons', 'dist', 'build', 'src/locale/i18n', 'src/locale/textdomain', 'locale/locales', 'locale/messages.po'];
const FILE_EXTS = ['.php', '.js', '.jsx', '.ts', '.tsx', '.vue', '.po', '.json', '.html', '.phtml'];

// ---- i18next extractor coverage -------------------------------------------
//
// An i18next.t('…') call in a file the extractor does not scan is silently
// untranslatable: it never reaches locale/messages.po, so POEditor never offers
// it and the string renders in English in every locale. That is exactly how 226
// call sites across 19 .php views went unnoticed (#9723).
//
// Rather than hard-coding a list of directories here, read the globs out of the
// extractor's own config — both `extract.input` and `extract.ignore` — so the
// two can never drift apart.

const I18NEXT_CONFIG = path.join(root, 'locale', 'scripts', 'i18next.config.ts');

/**
 * Blank every comment in `source`, replacing its characters with spaces so all
 * offsets stay put. Quote-aware, so a `//` inside a string literal (a URL, a
 * glob) survives untouched.
 */
function blankComments(source) {
  const out = source.split('');
  let i = 0;
  while (i < source.length) {
    const char = source[i];
    if (char === "'" || char === '"' || char === '`') {
      const quote = char;
      i++;
      while (i < source.length) {
        if (source[i] === '\\') {
          i += 2;
          continue;
        }
        if (source[i] === quote) {
          i++;
          break;
        }
        i++;
      }
      continue;
    }
    if (char === '/' && source[i + 1] === '/') {
      while (i < source.length && source[i] !== '\n') {
        out[i] = ' ';
        i++;
      }
      continue;
    }
    if (char === '/' && source[i + 1] === '*') {
      const end = source.indexOf('*/', i + 2);
      const stop = end === -1 ? source.length : end + 2;
      for (; i < stop; i++) {
        if (source[i] !== '\n') out[i] = ' ';
      }
      continue;
    }
    i++;
  }
  return out.join('');
}

/**
 * Index of the delimiter closing the one at `openPos`, or -1. Counts depth so a
 * character class inside a glob (`'src/[Vv]endor/**'`) cannot end the array
 * early, and skips string literals so a delimiter inside one is not counted.
 */
function findMatchingDelimiter(source, openPos, open = '[', close = ']') {
  let depth = 0;
  for (let i = openPos; i < source.length; i++) {
    const char = source[i];
    if (char === "'" || char === '"' || char === '`') {
      const quote = char;
      i++;
      while (i < source.length && source[i] !== quote) {
        if (source[i] === '\\') i++;
        i++;
      }
      continue;
    }
    if (char === open) {
      depth++;
    } else if (char === close) {
      depth--;
      if (depth === 0) return i;
    }
  }
  return -1;
}

/**
 * Body of the `extract: { … }` block, or the whole source when there is no such
 * block (the self-test fixtures are bare `input: […]` / `ignore: […]` snippets).
 *
 * Scoping matters for `ignore`: i18next-cli accepts an `ignore` under `lint` as
 * well as under `extract`, and reading the wrong one would widen the exclusion
 * set — which makes this rule quieter, not louder, and so hides exactly the
 * kind of gap it exists to catch.
 */
function extractSection(code) {
  const marker = code.match(/\bextract\s*:\s*\{/);
  if (!marker) return code;
  const open = marker.index + marker[0].length - 1;
  const close = findMatchingDelimiter(code, open, '{', '}');
  return close === -1 ? code : code.slice(open + 1, close);
}

/**
 * Glob strings from `<key>: [...]` (or `<key>: '...'`, which i18next-cli also
 * accepts for `ignore`) inside `section`. Returns null for an absent optional
 * key; throws for anything it cannot read when the key is required.
 */
function parseGlobList(section, key, label, required) {
  const arrayStart = section.match(new RegExp(`\\b${key}\\s*:\\s*\\[`));
  if (arrayStart) {
    const open = arrayStart.index + arrayStart[0].length - 1;
    const close = findMatchingDelimiter(section, open, '[', ']');
    if (close === -1) {
      throw new Error(`the "${key}" array in ${label} is never closed — cannot tell which files the extractor scans.`);
    }
    const block = section.slice(open + 1, close);
    const globs = [...block.matchAll(/(['"`])((?:\\.|(?!\1)[^\\])*)\1/g)].map((m) => m[2]);
    if (globs.length === 0 && required) {
      throw new Error(`the "${key}" array in ${label} has no string literals — cannot tell which files the extractor scans.`);
    }
    return globs;
  }

  const single = section.match(new RegExp(`\\b${key}\\s*:\\s*(['"\`])((?:\\\\.|(?!\\1)[^\\\\])*)\\1`));
  if (single) return [single[2]];

  if (!required) return null;
  throw new Error(`no "${key}: [" array in ${label} — cannot tell which files the extractor scans.`);
}

/**
 * Pull the `extract.input` and `extract.ignore` globs out of i18next.config.ts
 * source text.
 *
 * Both matter. i18next-cli hands `input` straight to node-glob, which does not
 * implement `!` negation inside a pattern array, so a `'!…'` entry there is
 * inert and `ignore` is the option that actually excludes anything (#9790).
 * `'!'` entries are still honoured wherever they appear, so that one
 * re-introduced by hand leaves this rule at least as strict as the extractor
 * rather than looser.
 *
 * Throws instead of returning null on anything it cannot read. A coverage rule
 * that silently switches itself off is worse than no rule at all — #9723 is
 * precisely the story of 226 unextractable call sites that nobody noticed.
 *
 * @param {string} source raw i18next.config.ts contents
 * @param {string} label  path to name in error messages
 * @returns {{include: string[], exclude: string[]}}
 */
function parseExtractorInput(source, label) {
  // Comments first: a commented-out entry inside the array is not a live glob,
  // and neither is the prose after a trailing `//`.
  const code = blankComments(source);
  const section = extractSection(code);

  const input = parseGlobList(section, 'input', label, true);
  const ignore = parseGlobList(section, 'ignore', label, false) ?? [];

  const stripBang = (g) => (g.startsWith('!') ? g.slice(1) : g);
  const include = input.filter((g) => !g.startsWith('!'));
  const exclude = [...input.filter((g) => g.startsWith('!')), ...ignore].map(stripBang);
  if (include.length === 0) {
    throw new Error(`the "input" array in ${label} has only "!" exclusions — cannot tell which files the extractor scans.`);
  }

  return { include, exclude };
}

/**
 * Compile parsed globs into matchers.
 *
 * picomatch rather than a hand-rolled glob→RegExp translation: brace
 * alternatives can themselves contain wildcards (`'{*.js,*.ts}'`), and escaping
 * those alternatives as regex literals produced a pattern that matched no file
 * at all — silently switching the rule off.
 */
function compileExtractorGlobs({ include, exclude }) {
  return {
    include: picomatch(include, { dot: true }),
    exclude: exclude.length > 0 ? picomatch(exclude, { dot: true }) : () => false,
  };
}

function loadExtractorGlobs() {
  const label = path.relative(root, I18NEXT_CONFIG);
  let source;
  try {
    source = fs.readFileSync(I18NEXT_CONFIG, 'utf8');
  } catch (e) {
    throw new Error(`cannot read ${label} (${e.message}) — the i18next-outside-extractor rule needs the extractor's own input globs.`);
  }
  return compileExtractorGlobs(parseExtractorInput(source, label));
}

let extractorGlobs;
try {
  extractorGlobs = loadExtractorGlobs();
} catch (error) {
  console.error(`locale-check: ${error.message}`);
  console.error('  → Fix locale/scripts/i18next.config.ts, or update scripts/locale-check.js to match it.');
  process.exit(2);
}

/** Repo-relative, forward-slashed path — what the globs are written against. */
function toRepoRelative(filePath) {
  return path.relative(root, path.resolve(root, filePath)).split(path.sep).join('/');
}

function isScannedByExtractor(filePath) {
  const rel = toRepoRelative(filePath);
  if (extractorGlobs.exclude(rel)) return false;
  return extractorGlobs.include(rel);
}

/**
 * The extraction tooling itself — this file and the extractor config/plugin it
 * reads — documents what a call site looks like rather than containing one.
 * Exempt it from the coverage rule only; every other rule still applies.
 */
const EXTRACTOR_TOOLING = ['scripts/locale-check.js', 'locale/scripts/'];

function isExtractorTooling(filePath) {
  const rel = toRepoRelative(filePath);
  return EXTRACTOR_TOOLING.some((prefix) => rel === prefix || rel.startsWith(prefix));
}

function isMinified(filePath) {
  return filePath.endsWith('.min.js') || filePath.endsWith('.min.css');
}

// Each rule matches a single source line. All operate on gettext()/i18next.t()/t() call sites.
const rules = [
  {
    name: 'trailing-colon',
    re: /(?:gettext|i18next\.t|(?:\bt|translate))\(\s*(['"])([^'"]*):\1\s*[,)]/,
    message: 'Colon baked into a translatable string — move the colon outside the call (see i18n-localization.md "Punctuation & Colon Placement").',
  },
  {
    name: 'html-in-string',
    re: /(?:gettext|i18next\.t)\(\s*(['"])([^'"]*)<[a-zA-Z/][^'"]*\1\s*[,)]/,
    message: 'HTML/markup baked into a translatable string — no HTML or formatting inside gettext()/i18next.t() strings (see i18n-localization.md "No HTML or Markup in Translatable Strings"). Confine any markup to an interpolated value instead.',
  },
  {
    name: 'decorative-wrapper',
    re: /(?:gettext|i18next\.t)\(\s*(['"])— [^'"]*—\1\s*[,)]/,
    message: 'Decorative em-dash wrapper baked into a translatable string — move the "—" characters outside the call (see i18n-localization.md "Decorative Wrappers").',
  },
  {
    name: 'i18next-outside-extractor',
    // A literal first argument only — `i18next.t()` in a comment or a type
    // declaration is not a call site.
    re: /i18next\.t\(\s*['"`]/,
    appliesTo: (file) => !isExtractorTooling(file) && !isScannedByExtractor(file),
    message: 'i18next.t() in a file the extractor does not scan — the string will never reach locale/messages.po and can never be translated (see #9723). Add the file\'s directory to extract.input in locale/scripts/i18next.config.ts (and check it is not excluded by extract.ignore), or move the string to a scanned file / gettext().',
  },
];

function isIgnored(filePath) {
  return IGNORE_DIRS.some((d) => filePath.includes(path.normalize(d)));
}

function checkLine(file, lineNum, text) {
  const issues = [];
  for (const rule of rules) {
    if (rule.appliesTo && !rule.appliesTo(file)) continue;
    if (rule.re.test(text)) {
      issues.push({ file, line: lineNum, text: text.trim(), rule: rule.name, message: rule.message });
    }
  }
  return issues;
}

// ---- Full-repo mode (npm run locale:lint) ----

function walk(dir, cb) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const e of entries) {
    const full = path.join(dir, e.name);
    if (isIgnored(full)) continue;
    if (e.isDirectory()) walk(full, cb);
    else cb(full);
  }
}

function checkFileFull(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  if (!FILE_EXTS.includes(ext)) return [];
  let data;
  try {
    data = fs.readFileSync(filePath, 'utf8');
  } catch (e) {
    return [];
  }
  const issues = [];
  const lines = data.split(/\r?\n/);
  lines.forEach((line, i) => {
    issues.push(...checkLine(filePath, i + 1, line));
  });
  return issues;
}

function runFullRepoScan() {
  const results = [];
  walk(root, (file) => {
    results.push(...checkFileFull(file));
  });
  return results;
}

// ---- Staged-diff mode (pre-commit hook): only check lines actually being added ----

function getStagedAddedLines() {
  const diff = execSync('git diff --cached -U0 --no-color', {
    encoding: 'utf8',
    maxBuffer: 1024 * 1024 * 100,
  });
  const filesLines = {};
  let currentFile = null;
  let newLineNum = null;

  for (const line of diff.split('\n')) {
    if (line.startsWith('+++ ')) {
      const m = line.match(/^\+\+\+ b\/(.*)$/);
      currentFile = m ? m[1] : null;
      continue;
    }
    if (line.startsWith('@@')) {
      const m = line.match(/^@@ -\d+(?:,\d+)? \+(\d+)(?:,\d+)? @@/);
      newLineNum = m ? Number.parseInt(m[1], 10) : null;
      continue;
    }
    if (!currentFile || newLineNum === null) continue;
    if (line.startsWith('+') && !line.startsWith('+++')) {
      if (!filesLines[currentFile]) filesLines[currentFile] = [];
      filesLines[currentFile].push({ line: newLineNum, text: line.slice(1) });
      newLineNum++;
    } else if (line.startsWith('-') && !line.startsWith('---')) {
      // removed line — does not advance the new-file line counter
    } else if (!line.startsWith('\\')) {
      newLineNum++;
    }
  }
  return filesLines;
}

function runStagedScan() {
  const filesLines = getStagedAddedLines();
  const results = [];
  for (const [file, addedLines] of Object.entries(filesLines)) {
    const ext = path.extname(file).toLowerCase();
    if (!FILE_EXTS.includes(ext) || isIgnored(file)) continue;
    for (const { line, text } of addedLines) {
      results.push(...checkLine(file, line, text));
    }
  }
  return results;
}

function report(results, scopeLabel) {
  if (results.length === 0) {
    console.log(`locale-check (${scopeLabel}): no issues found.`);
    return 0;
  }
  console.error(`locale-check (${scopeLabel}): found ${results.length} translatable-string issue(s):\n`);
  for (const r of results) {
    console.error(`${r.file}:${r.line}: [${r.rule}] ${r.text}`);
    console.error(`  → ${r.message}\n`);
  }
  return 2;
}

// ---- Self-test (npm run locale:lint:self-test) ----
//
// The repo has no unit-test runner for scripts/, so the parser cases that the
// i18next-outside-extractor rule depends on are checked here with node:assert.
// Each case is a shape that used to break the parser silently.

function runSelfTest() {
  const assert = require('node:assert');
  const cases = [];
  const check = (name, fn) => {
    try {
      fn();
      cases.push(`  ok   ${name}`);
    } catch (e) {
      cases.push(`  FAIL ${name}\n       ${e.message}`);
      process.exitCode = 2;
    }
  };

  // Brace alternatives that contain wildcards. The previous escapeRegExp pass
  // turned '{*.js,*.ts}' into a regex for a literal asterisk, so every file
  // failed to match and the rule went quiet.
  check('brace alternatives may contain wildcards', () => {
    const m = compileExtractorGlobs({ include: ['webpack/**/{*.js,*.ts}'], exclude: [] });
    assert.ok(m.include('webpack/admin/main.ts'), 'expected webpack/admin/main.ts to match');
    assert.ok(m.include('webpack/main.js'), 'expected webpack/main.js to match');
    assert.ok(!m.include('webpack/main.css'), 'expected webpack/main.css not to match');
  });

  check('plain brace alternatives still match', () => {
    const m = compileExtractorGlobs({ include: ['webpack/**/*.{js,ts,tsx}'], exclude: [] });
    assert.ok(m.include('webpack/a/b/c.tsx'));
    assert.ok(!m.include('webpack/a/b/c.css'));
  });

  // A character class inside a glob used to terminate the lazy [\s\S]*? match
  // at its ']', truncating the array to whatever came before it.
  check('character class in a glob does not truncate the input array', () => {
    const parsed = parseExtractorInput(
      `export default defineConfig({ extract: { input: [
         'src/skin/js/**/*.js',
         '!src/[Vv]endor/**',
         'webpack/**/*.{js,ts,tsx}'
       ] } });`,
      'fixture',
    );
    assert.deepStrictEqual(parsed.include, ['src/skin/js/**/*.js', 'webpack/**/*.{js,ts,tsx}']);
    assert.deepStrictEqual(parsed.exclude, ['src/[Vv]endor/**']);
  });

  // A commented-out entry (and the prose after a trailing //) is not a glob.
  check('// comments are not read as globs', () => {
    const parsed = parseExtractorInput(
      `input: [
         'src/skin/js/**/*.js',
         // '!src/legacy/**',   // temporarily disabled
         'webpack/**/*.ts'
       ]`,
      'fixture',
    );
    assert.deepStrictEqual(parsed.include, ['src/skin/js/**/*.js', 'webpack/**/*.ts']);
    assert.deepStrictEqual(parsed.exclude, []);
  });

  check('block comments are not read as globs', () => {
    const parsed = parseExtractorInput(
      `input: [ 'a/**/*.js', /* 'b/**/ /* *.js' */ 'c/**/*.ts' ]`,
      'fixture',
    );
    assert.ok(parsed.include.includes('a/**/*.js'));
    assert.ok(parsed.include.includes('c/**/*.ts'));
  });

  check('a // inside a string literal is kept', () => {
    const parsed = parseExtractorInput(`input: [ 'src/**/*.php', '!https://not-a-comment/**' ]`, 'fixture');
    assert.deepStrictEqual(parsed.include, ['src/**/*.php']);
    assert.deepStrictEqual(parsed.exclude, ['https://not-a-comment/**']);
  });

  // #9790: exclusions live in `extract.ignore`, because node-glob has no array
  // negation and the `'!…'` entries in `input` were inert.
  check('extract.ignore entries become exclusions', () => {
    const parsed = parseExtractorInput(
      `export default defineConfig({ extract: {
         input: [ 'src/**/*.php', 'webpack/**/*.{js,ts,tsx}' ],
         ignore: [ 'src/skin/external/**', 'webpack/**/*.d.ts' ]
       } });`,
      'fixture',
    );
    assert.deepStrictEqual(parsed.include, ['src/**/*.php', 'webpack/**/*.{js,ts,tsx}']);
    assert.deepStrictEqual(parsed.exclude, ['src/skin/external/**', 'webpack/**/*.d.ts']);

    const m = compileExtractorGlobs(parsed);
    assert.ok(!m.exclude('src/ChurchCRM/dashboard/views/Dashboard.php'), 'a scanned view is not excluded');
    assert.ok(m.exclude('src/skin/external/vendor.php'), 'expected src/skin/external to be excluded');
    assert.ok(m.exclude('webpack/types/window.d.ts'), 'expected .d.ts to be excluded');
  });

  // i18next-cli types `ignore` as `string | string[]`. Missing a single-string
  // form would shrink the exclusion set, which makes the rule quieter — the
  // silent-failure direction this file exists to avoid.
  check('a single-string extract.ignore is read', () => {
    const parsed = parseExtractorInput(
      `extract: { input: [ 'src/**/*.php' ], ignore: 'src/vendor/**' }`,
      'fixture',
    );
    assert.deepStrictEqual(parsed.exclude, ['src/vendor/**']);
  });

  // `ignore` is also a valid key under `lint`. Reading that one would exclude
  // files the extractor really does scan, silencing the rule for them.
  check('a lint.ignore is not read as an extract exclusion', () => {
    const parsed = parseExtractorInput(
      `export default defineConfig({
         extract: { input: [ 'src/**/*.php' ], ignore: [ 'src/vendor/**' ] },
         lint: { ignore: [ 'src/**/*.php' ] }
       });`,
      'fixture',
    );
    assert.deepStrictEqual(parsed.include, ['src/**/*.php']);
    assert.deepStrictEqual(parsed.exclude, ['src/vendor/**']);
  });

  check('a missing extract.ignore is not an error', () => {
    const parsed = parseExtractorInput(`extract: { input: [ 'src/**/*.php' ] }`, 'fixture');
    assert.deepStrictEqual(parsed.exclude, []);
  });

  // Failing loudly is the point: a rule that disables itself hides exactly the
  // bug (#9723) it exists to catch.
  check('an unreadable input array throws', () => {
    assert.throws(() => parseExtractorInput('export default defineConfig({});', 'fixture'), /no "input: \[" array/);
    assert.throws(() => parseExtractorInput('input: [', 'fixture'), /never closed/);
    assert.throws(() => parseExtractorInput('input: []', 'fixture'), /no string literals/);
    assert.throws(() => parseExtractorInput(`input: ['!a/**']`, 'fixture'), /only "!" exclusions/);
  });

  // The live config must stay readable, and must still cover the .php views
  // that #9723 was about.
  check('the live i18next.config.ts still parses and covers .php views', () => {
    const live = loadExtractorGlobs();
    assert.ok(live.include('src/ChurchCRM/dashboard/views/Dashboard.php'), 'expected .php views to be scanned');
    assert.ok(live.include('src/skin/js/anything.js'), 'expected src/skin/js to be scanned');
    assert.ok(!live.include('cypress/e2e/ui/some.spec.js'), 'expected cypress specs not to be scanned');
    // The live exclusions must be readable from `extract.ignore` (#9790).
    assert.ok(live.exclude('src/skin/external/anything.php'), 'expected src/skin/external to be excluded');
    assert.ok(live.exclude('webpack/types/window.d.ts'), 'expected .d.ts files to be excluded');
    assert.ok(!isScannedByExtractor('webpack/types/window.d.ts'), 'expected .d.ts not to count as scanned');
  });

  console.log('locale-check self-test:');
  console.log(cases.join('\n'));
  return process.exitCode === 2 ? 2 : 0;
}

function main() {
  if (process.argv.includes('--self-test')) {
    process.exit(runSelfTest());
  }
  const staged = process.argv.includes('--staged');
  const results = staged ? runStagedScan() : runFullRepoScan();
  const exitCode = report(results, staged ? 'staged changes' : 'full repo');
  process.exit(exitCode);
}

main();
