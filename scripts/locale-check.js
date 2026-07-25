#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const root = process.cwd();
const IGNORE_DIRS = ['node_modules', '.git', 'vendor', 'src/vendor', 'src/locale/vendor', 'src/skin/external', 'src/skin/v2', 'src/skin/icons', 'dist', 'build', 'src/locale/i18n', 'src/locale/textdomain', 'locale/locales', 'locale/messages.po'];
const FILE_EXTS = ['.php', '.js', '.jsx', '.ts', '.tsx', '.vue', '.po', '.json', '.html', '.phtml'];

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
];

function isIgnored(filePath) {
  return IGNORE_DIRS.some((d) => filePath.includes(path.normalize(d)));
}

function checkLine(file, lineNum, text) {
  const issues = [];
  for (const rule of rules) {
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

function main() {
  const staged = process.argv.includes('--staged');
  const results = staged ? runStagedScan() : runFullRepoScan();
  const exitCode = report(results, staged ? 'staged changes' : 'full repo');
  process.exit(exitCode);
}

main();
