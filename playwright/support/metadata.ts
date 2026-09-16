import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

import { LOCALE, SEED_VERSION } from './env';

let cachedCommit: string | undefined;

/**
 * Git commit SHA of the checkout under test. Falls back to a placeholder
 * (never "latest" — see playwright/README.md) if git metadata is unavailable,
 * e.g. when running against a packaged release zip with no .git directory.
 */
function getCommit(): string {
  if (cachedCommit) {
    return cachedCommit;
  }
  try {
    cachedCommit = execSync('git rev-parse HEAD', { encoding: 'utf8' }).trim();
  } catch {
    cachedCommit = 'unknown';
  }
  return cachedCommit;
}

export interface ArtifactMetadata {
  workflow: string;
  purpose: string;
  device: string;
  viewport: { width: number; height: number };
  /** Null for video-only captures (currently just the `setup` project). */
  screenshot: string | null;
  video: string | null;
}

/**
 * Writes the metadata JSON file alongside a captured screenshot/video, per
 * the artifact metadata schema in the bootstrap spec.
 */
export function writeMetadata(destination: string, data: ArtifactMetadata): void {
  fs.mkdirSync(path.dirname(destination), { recursive: true });

  const metadata = {
    workflow: data.workflow,
    purpose: data.purpose,
    product: 'ChurchCRM',
    commit: getCommit(),
    locale: LOCALE,
    device: data.device,
    viewport: data.viewport,
    timestamp: new Date().toISOString(),
    seed: SEED_VERSION,
    artifact: data.screenshot ? path.basename(data.screenshot) : null,
    video: data.video ? path.basename(data.video) : null,
  };

  fs.writeFileSync(destination, `${JSON.stringify(metadata, null, 2)}\n`);
}
