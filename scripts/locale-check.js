#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

const root = process.cwd();
const IGNORE_DIRS = ['node_modules', '.git', 'vendor', 'src/vendor', 'src/locale/vendor', 'dist', 'build', 'src/locale/i18n', 'src/locale/textdomain', 'locale/locales', 'locale/messages.po'];
const FILE_EXTS = ['.php', '.js', '.jsx', '.ts', '.tsx', '.vue', '.po', '.json', '.html', '.phtml'];

const patterns = [
  { name: 'gettext', re: /gettext\(\s*(['\"])([^'\"]*):\1\s*\)/g },
  { name: 'i18next.t', re: /i18next\.t\(\s*(['\"])([^'\"]*):\1\s*\)/g },
  { name: 't()', re: /\b(t|translate)\(\s*(['\"])([^'\"]*):\2\s*\)/g },
  { name: 'msgid', re: /^msgid\s+"(.+:)"\s*$/m }
];

function isIgnored(filePath) {
  return IGNORE_DIRS.some(d => filePath.includes(path.normalize(d)));
}

function walk(dir, cb) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  for (const e of entries) {
    const full = path.join(dir, e.name);
    if (isIgnored(full)) continue;
    if (e.isDirectory()) walk(full, cb);
    else cb(full);
  }
}

function checkFile(filePath) {
  const ext = path.extname(filePath).toLowerCase();
  if (!FILE_EXTS.includes(ext)) return [];
  let data;
  try { data = fs.readFileSync(filePath, 'utf8'); } catch (e) { return []; }
  const issues = [];
  const lines = data.split(/\r?\n/);
  patterns.forEach(p => {
    let match;
    if (p.name === 'msgid') {
      const re = new RegExp(p.re, 'gm');
      while ((match = re.exec(data)) !== null) {
        const prior = data.slice(0, match.index);
        const line = prior.split(/\r?\n/).length;
        issues.push({ file: filePath, line, text: match[0].trim(), rule: p.name });
      }
    } else {
      const re = new RegExp(p.re);
      for (let i = 0; i < lines.length; i++) {
        const l = lines[i];
        if (re.test(l)) {
          issues.push({ file: filePath, line: i + 1, text: l.trim(), rule: p.name });
        }
      }
    }
  });
  return issues;
}

function main() {
  const results = [];
  walk(root, file => {
    const res = checkFile(file);
    if (res.length) results.push(...res);
  });

  if (results.length === 0) {
    console.log('locale-check: no colon-ending i18n keys found.');
    process.exit(0);
  }

  console.error('locale-check: found i18n keys ending with a colon (":"):');
  results.forEach(r => {
    console.error(`${r.file}:${r.line}: [${r.rule}] ${r.text}`);
  });
  console.error('\nPlease remove trailing colons from msgids / i18n keys, and apply the colon in source around the translated string.');
  process.exit(2);
}

main();
