#!/usr/bin/env node
'use strict';
/**
 * Prints the version that follows a release.
 *
 * Usage: node scripts/release/next-version.js <released-tag>
 * Env:   GH_TOKEN, GH_REPO (default ChurchCRM/CRM)
 *
 * Once the "Start <next> release" PR merges, master's package.json holds the
 * next version. Until then the PR's build/<next> branch does, so Publish does
 * not wait for that merge.
 */
const { execFileSync } = require('node:child_process');

const parse = (v) => v.split('.').map(Number);

function cmp(a, b) {
  const [x, y] = [parse(a), parse(b)];
  return x[0] - y[0] || x[1] - y[1] || x[2] - y[2];
}

function pickNextVersion(tag, masterVersion, branchNames) {
  if (masterVersion !== tag) return masterVersion;
  const candidates = branchNames
    .map((n) => /^build\/(\d+\.\d+\.\d+)$/.exec(n)?.[1])
    .filter((v) => v && cmp(v, tag) > 0)
    .sort(cmp);
  return candidates.at(-1) ?? null;
}

function gh(path, jq) {
  return execFileSync('gh', ['api', '--paginate', path, '--jq', jq], { encoding: 'utf8' }).trim();
}

if (require.main === module) {
  const tag = process.argv[2];
  if (!tag) {
    console.error('Usage: next-version.js <released-tag>');
    process.exit(1);
  }
  const repo = process.env.GH_REPO || 'ChurchCRM/CRM';
  const masterVersion = JSON.parse(
    Buffer.from(gh(`repos/${repo}/contents/package.json?ref=master`, '.content'), 'base64').toString(),
  ).version;
  const branches = gh(`repos/${repo}/branches?per_page=100`, '.[].name').split('\n');
  const next = pickNextVersion(tag, masterVersion, branches);
  if (!next) {
    console.error(`::error::master is still ${tag} and no build/<next> branch exists. Run Release & Start Next first.`);
    process.exit(1);
  }
  console.log(next);
}

module.exports = { pickNextVersion };
