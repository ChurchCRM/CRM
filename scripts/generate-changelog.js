#!/usr/bin/env node

/**
 * Generate CHANGELOG.md from GitHub releases using the GitHub API
 * Usage: npm run changelog
 * 
 * This script uses the release.yml configuration to generate release notes
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

const GITHUB_OWNER = 'ChurchCRM';
const GITHUB_REPO = 'CRM';
const GITHUB_TOKEN = process.env.GITHUB_TOKEN;

// Color codes for console output
const colors = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  red: '\x1b[31m',
};

function log(color, ...args) {
  console.log(`${color}${args.join(' ')}${colors.reset}`);
}

/**
 * Make HTTPS request to GitHub API
 */
function githubRequest(endpoint) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: 'api.github.com',
      path: `/repos/${GITHUB_OWNER}/${GITHUB_REPO}${endpoint}`,
      method: 'GET',
      headers: {
        'User-Agent': 'ChurchCRM-Changelog-Generator',
        'Accept': 'application/vnd.github.v3+json',
      },
    };

    if (GITHUB_TOKEN) {
      options.headers['Authorization'] = `token ${GITHUB_TOKEN}`;
    }

    https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        if (res.statusCode === 200) {
          resolve(JSON.parse(data));
        } else {
          reject(new Error(`GitHub API error: ${res.statusCode} ${data}`));
        }
      });
    }).on('error', reject).end();
  });
}

/**
 * Generate changelog by fetching releases from GitHub
 */
async function generateChangelog() {
  try {
    log(colors.blue, '📝 Generating CHANGELOG.md...\n');

    // Fetch all releases
    log(colors.yellow, '⏳ Fetching releases from GitHub...');
    let releases = [];
    let page = 1;
    let hasMore = true;

    while (hasMore) {
      const data = await githubRequest(`/releases?page=${page}&per_page=30`);
      if (data.length === 0) {
        hasMore = false;
      } else {
        releases = releases.concat(data);
        page++;
      }
    }

    if (releases.length === 0) {
      log(colors.red, '❌ No releases found');
      return;
    }

    log(colors.green, `✓ Found ${releases.length} releases\n`);

    // Generate markdown
    let changelog = '# Changelog\n\n';
    changelog += 'All notable changes to this project will be documented in this file.\n\n';
    changelog += '_For archived versions prior to v5.0.0, see the [legacy changelog](https://github.com/ChurchCRM/CRM/releases)._\n\n';

    releases.forEach((release) => {
      const date = new Date(release.published_at);
      const dateStr = date.toISOString().split('T')[0];
      const version = release.tag_name;
      const body = release.body || '(No release notes)';

      changelog += `## [${version}](https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/releases/tag/${version}) - ${dateStr}\n\n`;
      changelog += `${body}\n\n`;
    });

    // Write to file
    const changelogPath = path.join(__dirname, '..', 'CHANGELOG.md');
    fs.writeFileSync(changelogPath, changelog);

    log(colors.green, `✓ CHANGELOG.md generated successfully`);
    log(colors.green, `✓ File: ${changelogPath}`);
    log(colors.green, `✓ Total releases: ${releases.length}\n`);

  } catch (error) {
    log(colors.red, `❌ Error: ${error.message}`);
    process.exit(1);
  }
}

// Run if called directly
if (require.main === module) {
  generateChangelog();
}

module.exports = { generateChangelog };
