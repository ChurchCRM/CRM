/**
 * capture.config.js — shared constants for all capture scenes
 *
 * Credentials mirror cypress/configs/docker.config.ts (admin.username / admin.password).
 * Override via environment variables — never hardcode secrets in scene files.
 *
 * Environment variables:
 *   CAPTURE_BASE_URL           – app base URL (default: http://localhost)
 *   CAPTURE_ADMIN_USERNAME     – admin username (default: admin)
 *   CAPTURE_ADMIN_PASSWORD     – admin password (default: changeme)
 *   CAPTURE_OUTPUT_ROOT        – default output root for ad-hoc runs (default: ./out)
 */

'use strict';

const BASE_URL = process.env.CAPTURE_BASE_URL || 'http://localhost';

/** Default viewport: mobile/vertical (matches primary target for Shorts/Reels/TikTok). */
const DEFAULT_VIEWPORT = { width: 390, height: 844 };

/** Default output root for ad-hoc local runs. CLI --out-dir overrides this. */
const OUTPUT_ROOT = process.env.CAPTURE_OUTPUT_ROOT || './out';

/**
 * Default credentials — sourced from environment, falling back to the same
 * values as cypress/configs/docker.config.ts so local Docker stacks work
 * out of the box without any configuration.
 */
const DEFAULT_CREDENTIALS = {
  username: process.env.CAPTURE_ADMIN_USERNAME || 'admin',
  password: process.env.CAPTURE_ADMIN_PASSWORD || 'changeme',
};

module.exports = { BASE_URL, DEFAULT_VIEWPORT, OUTPUT_ROOT, DEFAULT_CREDENTIALS };
