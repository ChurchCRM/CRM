#!/usr/bin/env node
/**
 * Dependabot PR fact-check — posts one deterministic, facts-only comment per
 * Dependabot PR. NO model call. NO LLM in GitHub Actions, by policy: Actions
 * logs on a public repo are world-readable, and a model API key would be a
 * secret exposed to every job on every trigger, including forked-PR runs.
 *
 * Runs from .github/workflows/dependabot-review.yml on `pull_request_target`, so it
 * executes in the base repository's context. It NEVER checks out or executes the
 * PR head: everything about the PR is read through the GitHub API.
 *
 * What it does — pure extraction, no judgment call and nothing sent anywhere
 * outside GitHub's own API:
 *   1. Reads the PR (must be authored by dependabot[bot]) and its changed files.
 *   2. Lists every package in the bump (grouped PRs list several), classifies
 *      major vs minor/patch from the version strings, and counts lockfile
 *      transitive version changes from the patch.
 *   3. Matches each package against the repo's open Dependabot alerts, when
 *      the token can read them.
 *   4. Flags a bumped `@types/*` package that is a deprecated stub per its own
 *      package-lock.json entry (see git-workflow.md → Dependabot Workflow).
 *   5. Upserts one marker comment per PR; a rebase (new head SHA) updates it.
 *
 * The written verdict — "does this break anything, should it merge" — needs
 * judgment this script does not have. That review is done by a person, or by
 * a maintainer running the community agent / Claude Code directly against
 * the PR — never by this workflow.
 *
 * It never approves, never merges, never labels.
 *
 * Env: GITHUB_TOKEN, GITHUB_REPOSITORY (owner/name), PR_NUMBER.
 *      DRY_RUN=1 prints the comment instead of posting.
 */
'use strict';

const REPO = process.env.GITHUB_REPOSITORY;
const PR_NUMBER = Number(process.env.PR_NUMBER);
const GH_TOKEN = process.env.GITHUB_TOKEN;
const DRY_RUN = Boolean(process.env.DRY_RUN);
const MARKER = '<!-- dependabot-review -->';

if (!REPO || !PR_NUMBER || !GH_TOKEN) {
  console.error('GITHUB_REPOSITORY, PR_NUMBER and GITHUB_TOKEN are required');
  process.exit(2);
}

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------

async function gh(pathname, { method = 'GET', body, raw = false } = {}) {
  const res = await fetch(`https://api.github.com${pathname}`, {
    method,
    headers: {
      authorization: `Bearer ${GH_TOKEN}`,
      accept: raw ? 'application/vnd.github.raw+json' : 'application/vnd.github+json',
      'x-github-api-version': '2022-11-28',
      'user-agent': 'churchcrm-dependabot-review',
      ...(body ? { 'content-type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    const err = new Error(`GitHub ${method} ${pathname} → ${res.status}`);
    err.status = res.status;
    throw err;
  }
  return raw ? res.text() : res.json();
}

async function ghOptional(pathname) {
  try {
    return await gh(pathname);
  } catch (err) {
    return { __error: err.status || err.message };
  }
}

// ---------------------------------------------------------------------------
// Fact extraction
// ---------------------------------------------------------------------------

function majorOf(version) {
  const m = String(version || '').replace(/^v/, '').match(/^(\d+)/);
  return m ? m[1] : null;
}

function bumpClass(from, to) {
  const f = majorOf(from);
  const t = majorOf(to);
  if (f === null || t === null) return 'unknown';
  return f === t ? 'minor-or-patch' : 'major';
}

/** Every package in the PR: grouped PRs list them in the body, single bumps in the title. */
function parsePackages(title, body) {
  const pkgs = new Map();
  const rx = /Updates `([^`]+)` from ([^\s]+) to ([^\s`]+)/g;
  let m;
  while ((m = rx.exec(body || '')) !== null) {
    pkgs.set(m[1], { name: m[1], from: m[2], to: m[3] });
  }
  if (pkgs.size === 0) {
    const t = (title || '').match(/bump (\S+) from (\S+) to (\S+)/i);
    if (t) pkgs.set(t[1], { name: t[1], from: t[2], to: t[3] });
  }
  return [...pkgs.values()].map((p) => ({ ...p, bump: bumpClass(p.from, p.to) }));
}

/** From a lockfile patch: version pairs visible in the diff hunks. */
function lockfileChanges(patch) {
  if (!patch) return null;
  const versions = [];
  let pendingFrom = null;
  for (const line of patch.split('\n')) {
    const minus = line.match(/^-\s*"version":\s*"([^"]+)"/);
    const plus = line.match(/^\+\s*"version":\s*"([^"]+)"/);
    if (minus) pendingFrom = minus[1];
    else if (plus && pendingFrom !== null) {
      versions.push({ from: pendingFrom, to: plus[1], bump: bumpClass(pendingFrom, plus[1]) });
      pendingFrom = null;
    }
  }
  return {
    version_changes: versions.length,
    major_changes: versions.filter((v) => v.bump === 'major').length,
    sample: versions.slice(0, 20),
  };
}

/** A bumped @types/* stub that the real package has made obsolete should be removed, not bumped. */
async function deprecatedTypeStubs(headSha, packages) {
  const types = packages.filter((p) => p.name.startsWith('@types/'));
  if (types.length === 0) return [];
  let lock;
  try {
    lock = JSON.parse(await gh(`/repos/${REPO}/contents/package-lock.json?ref=${headSha}`, { raw: true }));
  } catch {
    return [{ note: 'could not read head package-lock.json to check for deprecated stubs' }];
  }
  return types
    .map((p) => {
      const entry = lock.packages && lock.packages[`node_modules/${p.name}`];
      return entry && entry.deprecated ? { name: p.name, deprecated: entry.deprecated } : null;
    })
    .filter(Boolean);
}

// ---------------------------------------------------------------------------
// Comment body — deterministic markdown, no free text generated anywhere
// ---------------------------------------------------------------------------

function renderComment(facts) {
  const lines = [];
  lines.push('### Dependabot fact-check (automated, no model — facts only)');
  lines.push('_Extracted from PR metadata and the lockfile diff. This never merges, approves or judges risk — a maintainer or the community agent reviews before merging._');
  lines.push('');
  lines.push('**Packages:**');
  if (facts.packages.length === 0) {
    lines.push('- could not parse a package from the title/body');
  } else {
    for (const p of facts.packages) {
      const alertNote = p.alert && typeof p.alert === 'object'
        ? ` — **${p.alert.severity || 'unknown severity'} alert** (${p.alert.ghsa_id || 'no GHSA id'})`
        : p.alert === 'unknown (alerts not readable with this token)'
          ? ' — alert status unknown (token cannot read Dependabot alerts)'
          : '';
      lines.push(`- \`${p.name}\` ${p.from} → ${p.to} — **${p.bump}**${alertNote}`);
    }
  }
  if (facts.lockfiles.length) {
    lines.push('');
    lines.push('**Lockfile transitive changes:**');
    for (const l of facts.lockfiles) {
      if (l.note) { lines.push(`- ${l.file}: ${l.note}`); continue; }
      lines.push(`- ${l.file}: ${l.version_changes} version change(s), ${l.major_changes} major`);
    }
  }
  if (facts.deprecated_type_stubs.length) {
    lines.push('');
    lines.push('**Deprecated `@types/*` stub(s):**');
    for (const d of facts.deprecated_type_stubs) {
      lines.push(`- ${d.name ? `\`${d.name}\`: ${d.deprecated}` : d.note}`);
    }
  }
  lines.push('');
  lines.push(`**Files changed:** ${facts.files_changed.length}`);
  lines.push('');
  lines.push('_No review verdict is generated here. Read the release notes for any major bump before merging (see `.agents/skills/churchcrm/git-workflow.md` → Dependabot Workflow), or ask the community agent / Claude Code to review this PR directly._');
  return lines.join('\n');
}

// ---------------------------------------------------------------------------
// Comment upsert
// ---------------------------------------------------------------------------

async function upsertComment(headSha, body) {
  const comments = await gh(`/repos/${REPO}/issues/${PR_NUMBER}/comments?per_page=100`);
  const existing = comments.find((c) => typeof c.body === 'string' && c.body.includes(MARKER));
  const headMarker = `<!-- head:${headSha} -->`;
  if (existing && existing.body.includes(headMarker)) {
    console.log(`Fact-check for ${headSha.slice(0, 9)} already posted — nothing to do.`);
    return;
  }
  const full = `${MARKER}\n${headMarker}\n${body}`;
  if (existing) {
    await gh(`/repos/${REPO}/issues/comments/${existing.id}`, { method: 'PATCH', body: { body: full } });
    console.log(`Updated fact-check comment ${existing.id} for ${headSha.slice(0, 9)}.`);
  } else {
    await gh(`/repos/${REPO}/issues/${PR_NUMBER}/comments`, { method: 'POST', body: { body: full } });
    console.log(`Posted fact-check comment for ${headSha.slice(0, 9)}.`);
  }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

(async () => {
  const pr = await gh(`/repos/${REPO}/pulls/${PR_NUMBER}`);
  if (!/^dependabot(\[bot\])?$/.test(pr.user && pr.user.login)) {
    console.log(`PR #${PR_NUMBER} is by ${pr.user && pr.user.login}, not Dependabot — skipping.`);
    return;
  }
  if (pr.state !== 'open') {
    console.log(`PR #${PR_NUMBER} is ${pr.state} — skipping.`);
    return;
  }

  const files = await gh(`/repos/${REPO}/pulls/${PR_NUMBER}/files?per_page=100`);
  const packages = parsePackages(pr.title, pr.body);
  const lockfiles = files
    .filter((f) => /(^|\/)(package-lock\.json|composer\.lock)$/.test(f.filename))
    .map((f) => ({ file: f.filename, ...(lockfileChanges(f.patch) || { note: 'patch too large to inspect' }) }));

  const alerts = await ghOptional(`/repos/${REPO}/dependabot/alerts?state=open&per_page=100`);
  const alertList = Array.isArray(alerts)
    ? alerts.map((a) => ({
        package: a.security_vulnerability && a.security_vulnerability.package && a.security_vulnerability.package.name,
        severity: a.security_advisory && a.security_advisory.severity,
        ghsa_id: a.security_advisory && a.security_advisory.ghsa_id,
      }))
    : null;
  for (const p of packages) {
    p.alert = alertList
      ? alertList.find((a) => (a.package || '').toLowerCase() === p.name.toLowerCase()) || null
      : 'unknown (alerts not readable with this token)';
  }

  const facts = {
    packages,
    lockfiles,
    deprecated_type_stubs: await deprecatedTypeStubs(pr.head.sha, packages),
    files_changed: files.map((f) => f.filename),
  };

  const body = renderComment(facts);
  if (DRY_RUN) {
    console.log(body);
    return;
  }
  await upsertComment(pr.head.sha, body);
})().catch((err) => {
  console.error(err.stack || err.message);
  process.exit(1);
});
