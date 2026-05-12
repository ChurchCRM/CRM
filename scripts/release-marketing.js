#!/usr/bin/env node
'use strict';
/**
 * Generates social media marketing posts for a ChurchCRM release.
 *
 * Fetches the release from GitHub, sends the notes to Claude, and
 * prints ready-to-use posts for X, Facebook, and LinkedIn to stdout.
 *
 * Usage:
 *   node scripts/release-marketing.js <tag>
 *
 * Env vars:
 *   ANTHROPIC_API_KEY          Required
 *   GH_TOKEN / GITHUB_TOKEN    Optional — higher API rate limits
 *
 * Examples:
 *   node scripts/release-marketing.js 7.3.0
 *   node scripts/release-marketing.js 7.3.0 > /tmp/7.3.0-posts.md
 */

const https = require('https');

const REPO = 'ChurchCRM/CRM';

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
// Claude API
// ---------------------------------------------------------------------------

async function generatePosts(tag, title, body, releaseUrl) {
  const apiKey = process.env.ANTHROPIC_API_KEY;
  if (!apiKey) {
    console.error('Error: ANTHROPIC_API_KEY is required.');
    process.exit(1);
  }

  const system =
    'You are a social media marketer for ChurchCRM — a free, open-source church management system. ' +
    'ChurchCRM is known for its extensible plugin architecture, multilingual support, and active open-source community. ' +
    'Posts should be warm, community-focused, and highlight how ChurchCRM empowers churches of all sizes. ' +
    'Always emphasize that it is free and open-source.';

  const userPrompt =
    `Generate social media posts for the ChurchCRM ${tag} release.\n\n` +
    `Release title: ${title}\n` +
    `Release URL: ${releaseUrl}\n\n` +
    `Release notes:\n${body}\n\n` +
    `Create three posts:\n\n` +
    `1. **X (Twitter)** — max 280 characters, punchy hook, 2–3 relevant hashtags ` +
    `(#ChurchCRM #OpenSource #ChurchTech). Highlight expandability/plugin system if relevant. Include the release URL.\n\n` +
    `2. **Facebook** — 2–3 short paragraphs, warm community tone. Highlight a key feature or improvement, ` +
    `mention how churches can extend or customise the system. End with the release URL.\n\n` +
    `3. **LinkedIn** — professional tone, 2–3 paragraphs. Focus on technical innovation, ` +
    `open-source community contributions, and organisational impact for ministry teams. End with the release URL.\n\n` +
    `Format exactly as:\n### X\n<post>\n\n### Facebook\n<post>\n\n### LinkedIn\n<post>`;

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
    {
      model:      'claude-haiku-4-5-20251001',
      max_tokens: 1200,
      system,
      messages:   [{ role: 'user', content: userPrompt }],
    },
  );

  return data.content[0].text.trim();
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

async function main() {
  const args = process.argv.slice(2);
  const tag  = args.find(a => !a.startsWith('--'));

  if (!tag) {
    console.error('Usage: node scripts/release-marketing.js <tag>');
    process.exit(1);
  }

  process.stderr.write(`Fetching release ${tag} from GitHub...\n`);
  const release    = await fetchRelease(tag);
  const title      = release.name || tag;
  const body       = release.body || '';
  const releaseUrl = release.html_url;

  process.stderr.write('Generating social media posts via Claude...\n');
  const posts = await generatePosts(tag, title, body, releaseUrl);

  // Output to stdout — pipe to a file or let the action capture it
  console.log(`## ChurchCRM ${tag} — Social Media Posts`);
  console.log('');
  console.log(`> Source release: ${releaseUrl}`);
  console.log('> Review and edit before publishing.');
  console.log('');
  console.log('---');
  console.log('');
  console.log(posts);
}

main().catch(err => { console.error(err.message); process.exit(1); });
