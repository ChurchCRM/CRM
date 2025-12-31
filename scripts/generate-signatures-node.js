#!/usr/bin/env node
// scripts/generate-signatures-node.js
// Reproduces the Grunt generateSignatures output deterministically
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const repoRoot = path.resolve(__dirname, '..');
const srcDir = path.join(repoRoot, 'src');
const outFile = path.join(srcDir, 'admin/data/signatures.json');
const pkgPath = path.join(repoRoot, 'package.json');
let version = null;
try {
  version = require(pkgPath).version;
} catch (e) {
  // ignore
}

const excludes = [
  /^\.htaccess$/,
  /^\.gitignore$/,
  /^composer\.lock$/,
  /^Include\/Config\.php$/,
  /^propel\/propel\.php$/,
  /^integrityCheck\.json$/,
  /^Images\/Person\/thumbnails\//,
  /^vendor\/.*\/example\//,
  /^vendor\/.*\/tests\//,
  /^vendor\/.*\/docs\//
];

function isExcluded(rel) {
  for (const ex of excludes) {
    if (ex instanceof RegExp) {
      if (ex.test(rel)) return true;
    } else if (typeof ex === 'string') {
      if (rel.indexOf(ex) === 0) return true;
    }
  }
  return false;
}

function walkDir(dir, base) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  let out = [];
  for (const e of entries) {
    const full = path.join(dir, e.name);
    const rel = path.join(base, e.name).replace(/\\/g, '/');
    if (e.isDirectory()) {
      // skip vendor examples/tests/docs quickly by pattern
      if (isExcluded(rel + '/')) continue;
      out = out.concat(walkDir(full, rel));
    } else if (e.isFile()) {
      const ext = path.extname(e.name).toLowerCase();
      if (ext !== '.php' && ext !== '.js') continue;
      if (isExcluded(rel)) continue;
      out.push(rel);
    }
  }
  return out;
}

const files = walkDir(srcDir, '');
files.sort();

const filesArray = files.map((rel) => {
  const full = path.join(srcDir, rel);
  const buf = fs.readFileSync(full);
  const sha1 = crypto.createHash('sha1').update(buf).digest('hex');
  return { filename: rel, sha1 };
});

const aggregate = crypto.createHash('sha1').update(JSON.stringify(filesArray)).digest('hex');

const signatures = { version: version || null, files: filesArray, sha1: aggregate };

const outDir = path.dirname(outFile);
if (!fs.existsSync(outDir)) {
  fs.mkdirSync(outDir, { recursive: true });
}
fs.writeFileSync(outFile, JSON.stringify(signatures));
console.log('Wrote', outFile, 'checked=', filesArray.length);
