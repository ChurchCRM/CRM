#!/usr/bin/env node
/**
 * Dependabot supersede — when a new Dependabot PR opens, close the older open
 * Dependabot PRs it replaces. No model call; the rules are below.
 *
 * A PR is superseded when an older open Dependabot PR:
 *   - bumps the same single package to the same or an older version, or
 *   - is for the same group (same title with the group name and directory,
 *     version counts removed) and was opened before the new one.
 *
 * Grouped PRs are only closed by the same-group rule. A grouped PR can drop a
 * package the newer one no longer includes, so that case is left to a person.
 *
 * Each closed PR gets a comment saying which PR replaced it. Closing a
 * Dependabot PR does not ignore its version (Dependabot says so in its own
 * comment), so this only removes duplicates. The newer PR still carries the
 * current target.
 *
 * Runs from .github/workflows/dependabot-supersede.yml on pull_request_target.
 * It never checks out the PR head.
 *
 * Env: GITHUB_TOKEN, GITHUB_REPOSITORY (owner/name), PR_NUMBER.
 *      DRY_RUN=1 prints what would be closed.
 */
'use strict';

const MARKER = '<!-- dependabot-supersede -->';

/** Parse "bump <pkg> from <a> to <b>" or a group title. Returns null for titles it does not know. */
function parseTitle(title) {
  const single = /bump (\S+) from (\S+) to (\S+)/i.exec(title || '');
  if (single) {
    return { kind: 'single', name: single[1], to: single[3] };
  }
  const group = /bump (the .+? group|.+? group)(?: in (\S+))?/i.exec(title || '');
  if (group) {
    const dir = group[2] ? ` in ${group[2]}` : '';
    return { kind: 'group', key: `${group[1]}${dir}`.toLowerCase() };
  }
  return null;
}

/** Compare two version strings numerically on each dotted part. Returns <0, 0, >0. */
function compareVersions(a, b) {
  const pa = String(a).replace(/^v/, '').split(/[.+-]/).map((x) => parseInt(x, 10));
  const pb = String(b).replace(/^v/, '').split(/[.+-]/).map((x) => parseInt(x, 10));
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const x = Number.isNaN(pa[i]) ? 0 : pa[i] || 0;
    const y = Number.isNaN(pb[i]) ? 0 : pb[i] || 0;
    if (x !== y) return x - y;
  }
  return 0;
}

/**
 * newPr and openPrs use the GitHub shape: { number, title, created_at, user: { login } }.
 * Returns [{ pr, replacedBy, reason }] for each older PR the new one supersedes.
 */
function findSuperseded(newPr, openPrs) {
  const isDependabot = (p) => p.user && p.user.login === 'dependabot[bot]';
  const newInfo = parseTitle(newPr.title);
  if (!newInfo) return [];
  const superseded = [];
  for (const pr of openPrs) {
    if (pr.number === newPr.number || !isDependabot(pr)) continue;
    if (pr.created_at >= newPr.created_at) continue;
    const info = parseTitle(pr.title);
    if (!info || info.kind !== newInfo.kind) continue;
    if (newInfo.kind === 'single' && info.name === newInfo.name && compareVersions(info.to, newInfo.to) <= 0) {
      superseded.push({ pr, replacedBy: newPr.number, reason: `same package (${info.name}), older or equal target ${info.to}` });
    } else if (newInfo.kind === 'group' && info.key === newInfo.key) {
      superseded.push({ pr, replacedBy: newPr.number, reason: `same group (${info.key})` });
    }
  }
  return superseded;
}

// ---------------------------------------------------------------------------
// GitHub I/O (only runs when this file is the entry point)
// ---------------------------------------------------------------------------

async function main() {
  const REPO = process.env.GITHUB_REPOSITORY;
  const PR_NUMBER = Number(process.env.PR_NUMBER);
  const GH_TOKEN = process.env.GITHUB_TOKEN;
  const DRY_RUN = Boolean(process.env.DRY_RUN);
  if (!REPO || !PR_NUMBER || !GH_TOKEN) {
    console.error('GITHUB_REPOSITORY, PR_NUMBER and GITHUB_TOKEN are required');
    process.exit(2);
  }

  async function gh(pathname, { method = 'GET', body } = {}) {
    const res = await fetch(`https://api.github.com${pathname}`, {
      method,
      headers: {
        authorization: `Bearer ${GH_TOKEN}`,
        accept: 'application/vnd.github+json',
        'x-github-api-version': '2022-11-28',
        'user-agent': 'churchcrm-dependabot-supersede',
        ...(body ? { 'content-type': 'application/json' } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) throw new Error(`GitHub ${method} ${pathname} → ${res.status}`);
    return res.status === 204 ? null : res.json();
  }

  const newPr = await gh(`/repos/${REPO}/pulls/${PR_NUMBER}`);
  if (newPr.user.login !== 'dependabot[bot]') {
    console.log(`#${PR_NUMBER} is not a Dependabot PR; nothing to do.`);
    return;
  }

  const open = [];
  for (let page = 1; ; page++) {
    const batch = await gh(`/repos/${REPO}/pulls?state=open&per_page=100&page=${page}`);
    open.push(...batch);
    if (batch.length < 100) break;
  }

  const superseded = findSuperseded(newPr, open);
  if (superseded.length === 0) {
    console.log(`#${PR_NUMBER}: no older open Dependabot PR is superseded.`);
    return;
  }

  for (const s of superseded) {
    const body = [
      MARKER,
      `Closing: superseded by #${s.replacedBy} (${s.reason}).`,
      '',
      'The newer PR carries the current target. Closing this does not ignore the version, so Dependabot will not reopen it. A newer version will still get a PR.',
    ].join('\n');
    if (DRY_RUN) {
      console.log(`[dry-run] would close #${s.pr.number} "${s.pr.title}": ${s.reason}`);
      continue;
    }
    await gh(`/repos/${REPO}/issues/${s.pr.number}/comments`, { method: 'POST', body: { body } });
    await gh(`/repos/${REPO}/pulls/${s.pr.number}`, { method: 'PATCH', body: { state: 'closed' } });
    console.log(`closed #${s.pr.number}: ${s.reason}`);
  }
}

if (require.main === module) {
  main().catch((err) => {
    console.error(err.message);
    process.exit(1);
  });
}

module.exports = { parseTitle, compareVersions, findSuperseded };
