#!/usr/bin/env node

/**
 * Biome ships its actual binary as a per-platform optional dependency
 * (@biomejs/cli-<platform>-<arch>). node_modules/ is not always installed
 * fresh on the machine that runs it — e.g. a repo checkout shared between
 * hosts of different OS/arch, or a stale CI cache — so the optional
 * dependency for the *current* platform can be missing even though
 * @biomejs/biome itself resolved fine. When that happens biome's own bin
 * script throws a raw MODULE_NOT_FOUND with no hint of the fix. Detect that
 * case here and install the missing platform package before biome runs.
 */

const { execFileSync, execSync } = require('child_process');

const { platform, arch } = process;

function isMusl() {
    try {
        const out = execSync('ldd --version', { stdio: ['pipe', 'pipe', 'pipe'] }).toString();
        return out.includes('musl');
    } catch (err) {
        return String(err.stderr || '').includes('musl');
    }
}

const PLATFORM_PACKAGES = {
    win32: { x64: '@biomejs/cli-win32-x64', arm64: '@biomejs/cli-win32-arm64' },
    darwin: { x64: '@biomejs/cli-darwin-x64', arm64: '@biomejs/cli-darwin-arm64' },
    linux: { x64: '@biomejs/cli-linux-x64', arm64: '@biomejs/cli-linux-arm64' },
    'linux-musl': { x64: '@biomejs/cli-linux-x64-musl', arm64: '@biomejs/cli-linux-arm64-musl' },
};

const platformKey = platform === 'linux' && isMusl() ? 'linux-musl' : platform;
const pkg = PLATFORM_PACKAGES[platformKey]?.[arch];

// Unknown platform/arch: let biome's own bin script produce the real error.
if (!pkg) {
    process.exit(0);
}

try {
    require.resolve(`${pkg}/package.json`);
} catch {
    const biomeVersion = require('@biomejs/biome/package.json').version;
    console.warn(`[ensure-biome] ${pkg} not found for this platform — installing ${pkg}@${biomeVersion}...`);
    execFileSync('npm', ['install', `${pkg}@${biomeVersion}`, '--no-save', '--no-audit', '--no-fund'], {
        stdio: 'inherit',
    });
}
