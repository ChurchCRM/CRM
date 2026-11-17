#!/usr/bin/env node

/**
 * Smoke-test a production image: non-root, no compiler, required extensions, then a live port.
 * Usage: node tests/docker/test-production-image.js IMAGE apache|fpm
 */

const { spawnSync } = require('child_process');

const image = process.argv[2];
const flavor = process.argv[3];
const ports = { apache: '8080', fpm: '9000' };

if (!image || !ports[flavor]) {
    console.error('Usage: tests/docker/test-production-image.js IMAGE apache|fpm');
    process.exit(1);
}

let container = '';

function removeContainer() {
    if (!container) {
        return;
    }
    const id = container;
    const removed = spawnSync('docker', ['rm', '-f', id], { encoding: 'utf8' });
    if (removed.error || removed.status !== 0) {
        console.error(`container left running: ${id}`);
        console.error(removed.stderr || removed.error?.message || '');
        return;
    }
    container = '';
}

function stop(code) {
    removeContainer();
    process.exit(code);
}

process.on('SIGINT', () => stop(130));
process.on('SIGTERM', () => stop(143));

function docker(args, options = {}) {
    const result = spawnSync('docker', args, { encoding: 'utf8', ...options });
    if (result.error) {
        console.error(result.error.message);
        stop(1);
    }
    return result;
}

const inspect = docker([
    'run', '--rm', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges', image, 'sh', '-ec',
    `test "$(id -u)" -ne 0
for tool in gcc g++ make; do
  if command -v "$tool" >/dev/null 2>&1; then
    echo "build tool present: $tool" >&2
    exit 1
  fi
done
test ! -d /usr/include/linux
test ! -d /usr/local/include/php
test -f /var/www/html/.htaccess
test -f /var/www/html/vendor/autoload.php
test ! -e /var/www/html/apache/default.conf
test ! -e /var/www/html/Include/Config.php
php -r 'foreach (["bcmath", "curl", "exif", "gd", "gettext", "iconv", "intl", "mbstring", "mysqli", "Zend OPcache", "pdo_mysql", "xml", "zip"] as $extension) {
    if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: $extension\\n"); exit(1); }
}
if (extension_loaded("xdebug")) { exit(1); }'`,
]);
if (inspect.status !== 0) {
    process.stderr.write(inspect.stderr || '');
    process.exit(inspect.status || 1);
}

const started = docker([
    'run', '-d', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges', image,
]);
if (started.status !== 0) {
    process.stderr.write(started.stderr || '');
    process.exit(started.status || 1);
}
container = started.stdout.trim();

const port = ports[flavor];
for (let attempt = 1; attempt <= 30; attempt++) {
    const open = docker([
        'exec', container, 'php', '-r',
        '$s = @fsockopen("127.0.0.1", (int)$argv[1]); if (!$s) { exit(1); } fclose($s);',
        port,
    ]);
    if (open.status === 0) {
        if (flavor === 'apache') {
            const http = docker([
                'exec', container, 'php', '-r',
                '$context = stream_context_create(["http" => ["follow_location" => 0, "ignore_errors" => true]]); file_get_contents("http://127.0.0.1:8080/", false, $context); if (!preg_match("~^HTTP/\\S+ (200|302|303) ~", $http_response_header[0] ?? "")) { exit(1); }',
            ]);
            if (http.status !== 0) {
                removeContainer();
                process.exit(http.status || 1);
            }
        }
        removeContainer();
        process.exit(0);
    }
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 1000);
}

const logs = docker(['logs', container]);
process.stderr.write(logs.stdout || '');
process.stderr.write(logs.stderr || '');
removeContainer();
process.exit(1);
