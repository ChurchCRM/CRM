#!/usr/bin/env node
'use strict';
/**
 * Syncs a GitHub Release to the local changelog structure.
 *
 * Fetches the release title and body from the GitHub API, writes
 * changelog/<tag>.md, and inserts a new row in CHANGELOG.md.
 *
 * Usage:
 *   node scripts/release-changelog.js <tag> [--force]
 *
 *   --force  Overwrite changelog/<tag>.md even if it already exists.
 *
 * Env vars:
 *   GH_TOKEN / GITHUB_TOKEN   Optional — higher API rate limits
 *   ANTHROPIC_API_KEY          Optional — AI-generated one-line highlights
 *
 * Examples:
 *   node scripts/release-changelog.js 7.3.0
 *   node scripts/release-changelog.js 7.2.2 --force
 *   GH_TOKEN=ghp_xxx node scripts/release-changelog.js 6.8.0
 */

const fs    = require('fs');
const path  = require('path');
const https = require('https');

const REPO_ROOT = path.resolve(__dirname, '..');
const REPO      = 'ChurchCRM/CRM';

// ---------------------------------------------------------------------------
// HTTP helper
// ---------------------------------------------------------------------------

function request(url, options, body) {
  return new Promise((resolve, reject) => {
    const parsed = new URL(url);
    const opts = {
      hostname: parsed.hostname,
      path:     parsed.pathname + parsed.search,
      method:   options.method || 'GET',
      headers:  options.headers || {},
    };

    const bodyStr = body ? JSON.stringify(body) : null;
    if (bodyStr) opts.headers['content-length'] = Buffer.byteLength(bodyStr);

    const req = https.request(opts, (res) => {
      let data = '';
      res.on('data', chunk => (data += chunk));
      res.on('end', () => {
        if (res.statusCode >= 400) {
          reject(new Error(`HTTP ${res.statusCode} for ${url}: ${data}`));
        } else {
          try { resolve(JSON.parse(data)); } catch { resolve(data); }
        }
      });
    });

    req.on('error', reject);
    if (bodyStr) req.write(bodyStr);
    req.end();
  });
}

// ---------------------------------------------------------------------------
// GitHub API
// ---------------------------------------------------------------------------

async function fetchRelease(tag) {
  const token = process.env.GH_TOKEN || process.env.GITHUB_TOKEN;
  const headers = {
    Accept:                 'application/vnd.github+json',
    'User-Agent':           'ChurchCRM-release-script',
    'X-GitHub-Api-Version': '2022-11-28',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
  return request(
    `https://api.github.com/repos/${REPO}/releases/tags/${encodeURIComponent(tag)}`,
    { headers },
  );
}

// ---------------------------------------------------------------------------
// Optional AI highlights (falls back to H2 extraction)
// ---------------------------------------------------------------------------

async function generateHighlights(tag, body) {
  const apiKey = process.env.ANTHROPIC_API_KEY;

  if (!apiKey) {
    const h2s = [...body.matchAll(/^## (.+)$/gm)]
      .map(m => m[1].replace(/^\p{Emoji_Presentation}+\s*/u, '').trim())
      .filter(Boolean)
      .slice(0, 3);
    return h2s.length ? h2s.join(', ') : 'See release notes';
  }

  const prompt =
    `Summarize the ChurchCRM ${tag} release in one short line (max 100 chars). ` +
    `Give a comma-separated list of 3–5 key changes. No markdown, no quotes, no trailing period.\n\n` +
    `Release notes:\n${body}`;

  try {
    const data = await request(
      'https://api.anthropic.com/v1/messages',
      {
        method:  'POST',
        headers: {
          'x-api-key':         apiKey,
          'anthropic-version': '2023-06-01',
          'content-type':      'application/json',
        },
      },
      { model: 'claude-haiku-4-5-20251001', max_tokens: 120, messages: [{ role: 'user', content: prompt }] },
    );
    return data.content[0].text.trim().replace(/^["']|["']$/g, '');
  } catch (err) {
    console.warn(`Anthropic API error: ${err.message} — falling back to H2 extraction`);
    const saved = process.env.ANTHROPIC_API_KEY;
    delete process.env.ANTHROPIC_API_KEY;
    const result = await generateHighlights(tag, body);
    process.env.ANTHROPIC_API_KEY = saved;
    return result;
  }
}

// ---------------------------------------------------------------------------
// File operations
// ---------------------------------------------------------------------------

function writeChangelogFile(tag, title, body, publishedAt, force) {
  const dest = path.join(REPO_ROOT, 'changelog', `${tag}.md`);

  if (fs.existsSync(dest) && !force) {
    console.log(`changelog/${tag}.md already exists — skipping (use --force to overwrite).`);
    return;
  }

  const dt          = new Date(publishedAt);
  const releaseDate = dt.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

  const content = [
    `# ${title}`,
    '',
    `**Release Date**: ${releaseDate}`,
    '',
    '---',
    '',
    body.trim(),
    '',
  ].join('\n');

  fs.writeFileSync(dest, content);
  console.log(`Wrote changelog/${tag}.md`);
}

function updateChangelogIndex(tag, publishedAt, highlights) {
  const indexPath = path.join(REPO_ROOT, 'CHANGELOG.md');
  const content   = fs.readFileSync(indexPath, 'utf8');

  if (content.includes(`[${tag}]`)) {
    console.log(`${tag} already in CHANGELOG.md — skipping.`);
    return;
  }

  const dt        = new Date(publishedAt);
  const monthYear = dt.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  const newRow    = `| [${tag}](./changelog/${tag}.md) | ${monthYear} | ${highlights} |`;

  const lines  = content.split('\n');
  const sepIdx = lines.findIndex(l => /^\|[-| :]+\|/.test(l));
  if (sepIdx === -1) throw new Error('Could not find table separator in CHANGELOG.md');

  lines.splice(sepIdx + 1, 0, newRow);
  fs.writeFileSync(indexPath, lines.join('\n'));
  console.log(`Added ${tag} row to CHANGELOG.md`);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

async function main() {
  const args  = process.argv.slice(2);
  const tag   = args.find(a => !a.startsWith('--'));
  const force = args.includes('--force');

  if (!tag) {
    console.error('Usage: node scripts/release-changelog.js <tag> [--force]');
    process.exit(1);
  }

  console.log(`Fetching release ${tag} from GitHub...`);
  const release     = await fetchRelease(tag);
  const title       = release.name || tag;
  const body        = release.body || '';
  const publishedAt = release.published_at;

  writeChangelogFile(tag, title, body, publishedAt, force);

  console.log('Generating highlights...');
  const highlights = await generateHighlights(tag, body);
  console.log(`  → ${highlights}`);

  updateChangelogIndex(tag, publishedAt, highlights);
  console.log('Done.');
}

main().catch(err => { console.error(err.message); process.exit(1); });
