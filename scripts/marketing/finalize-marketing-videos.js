#!/usr/bin/env node
'use strict';

/**
 * Copies each workflow's recorded video from Playwright's internal report
 * output to the deterministic path already claimed for it in
 * playwright/support/capture.ts (artifacts/videos/<device>/<name>.webm).
 *
 * Playwright only finalizes a test's video after its browser context closes
 * — which happens after the test function has already returned — so there
 * is no point inside a test where the real video file can be renamed. This
 * runs once, after the whole suite finishes, and reads the JSON reporter
 * output instead.
 *
 * A failed workflow has no video to finalize here; that is expected and not
 * an error on its own — `npx playwright test` already exits non-zero for a
 * failed workflow, which is what makes `npm run marketing:visuals` fail.
 */

const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const REPORT_PATH = path.join(__dirname, '..', '..', 'playwright', 'artifacts', 'report.json');
const VIDEOS_ROOT = path.join(__dirname, '..', '..', 'playwright', 'artifacts', 'videos');

function collectTests(suites, out) {
  for (const suite of suites) {
    for (const spec of suite.specs || []) {
      for (const specTest of spec.tests || []) {
        out.push({ title: spec.title, projectName: specTest.projectName, results: specTest.results || [] });
      }
    }
    if (suite.suites) {
      collectTests(suite.suites, out);
    }
  }
}

function main() {
  if (!fs.existsSync(REPORT_PATH)) {
    console.error(`No Playwright report found at ${REPORT_PATH} — did the test run execute?`);
    process.exit(1);
  }

  const report = JSON.parse(fs.readFileSync(REPORT_PATH, 'utf8'));
  const tests = [];
  collectTests(report.suites || [], tests);

  let copied = 0;
  for (const workflowTest of tests) {
    const passedResult = workflowTest.results.find((result) => result.status === 'passed');
    if (!passedResult) {
      continue;
    }

    const videoAttachment = (passedResult.attachments || []).find((attachment) => attachment.name === 'video');
    if (!videoAttachment || !videoAttachment.path) {
      continue;
    }

    // Skip if video file doesn't exist (test failed before video was recorded)
    if (!fs.existsSync(videoAttachment.path)) {
      console.warn(`⚠️  Video not found: ${videoAttachment.path}`);
      continue;
    }

    const destination = path.join(VIDEOS_ROOT, workflowTest.projectName, `${workflowTest.title}.webm`);
    fs.mkdirSync(path.dirname(destination), { recursive: true });
    fs.copyFileSync(videoAttachment.path, destination);
    copied += 1;
  }

  console.log(`Finalized ${copied} video artifact(s).`);
  joinSetupForSocial();
}

// The website keeps the three setup clips. Social gets one file: those clips
// in order. demo-data-import already continues onto the populated dashboards.
const SETUP_SOCIAL_PARTS = ['install-churchcrm', 'setup-church-info', 'demo-data-import'];

function joinSetupForSocial() {
  const dir = path.join(VIDEOS_ROOT, 'setup');
  const parts = SETUP_SOCIAL_PARTS.map((name) => path.join(dir, `${name}.webm`));
  if (!parts.every((file) => fs.existsSync(file))) {
    return;
  }

  const listPath = path.join(dir, 'setup-demo-dashboards.concat.txt');
  fs.writeFileSync(listPath, parts.map((file) => `file '${file}'`).join('\n') + '\n');
  const destination = path.join(dir, 'setup-demo-dashboards.webm');
  const result = spawnSync('ffmpeg', ['-y', '-f', 'concat', '-safe', '0', '-i', listPath, '-c', 'copy', destination], {
    stdio: 'inherit',
  });
  fs.rmSync(listPath, { force: true });
  if (result.status !== 0) {
    console.error('ffmpeg could not join the setup recordings into one social video.');
    process.exit(result.status || 1);
  }

  const metaDir = path.join(__dirname, '..', '..', 'playwright', 'artifacts', 'metadata', 'setup');
  const source = JSON.parse(fs.readFileSync(path.join(metaDir, 'demo-data-import.json'), 'utf8'));
  fs.writeFileSync(
    path.join(metaDir, 'setup-demo-dashboards.json'),
    `${JSON.stringify(
      {
        ...source,
        workflow: 'setup-demo-dashboards',
        title: 'From Setup to Demo Dashboards',
        category: 'Setup',
        purpose:
          'Social cut: install ChurchCRM, set the church, import the sample data, and show that data on the module dashboards',
        usedIn: ['website'],
        video: 'setup-demo-dashboards.webm',
        artifact: null,
      },
      null,
      2,
    )}\n`,
  );
  console.log('Joined the setup recordings into setup-demo-dashboards.webm');
}

main();
