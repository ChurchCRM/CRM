<?php

/**
 * scripts/plugin-scan.php — community plugin self-audit tool.
 *
 * Usage:
 *   php scripts/plugin-scan.php <plugin-dir>
 *   php scripts/plugin-scan.php --json <plugin-dir>
 *
 * Runs the same checks a ChurchCRM maintainer would run during the
 * plugin-security-scan review, against a plugin directory on disk.
 * Intended to be run by the plugin author on their own workstation
 * BEFORE opening an approved-plugins.json PR.
 *
 * Exit codes:
 *   0  — no errors, zero or more warnings
 *   1  — at least one error
 *   2  — invalid invocation
 *
 * Each finding has a severity:
 *   error    — will block approval (dangerous sink, missing manifest field,
 *              disallowed extension, path traversal, …)
 *   warning  — should be investigated (outbound call, DB write outside the
 *              config sandbox, …)
 *   info     — informational (capability inventory, string counts, …)
 *
 * The scanner MUST NOT touch the network or execute any plugin code.
 * It only reads files from disk and matches patterns. Any finding that
 * requires runtime context is flagged as an "info" and delegated to
 * the reviewer.
 */

declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────
//  Constants — declared at the top of the file so they are defined
//  before the IIFE at the bottom runs. Top-level `const` in PHP is
//  processed at the statement's position, not hoisted.
// ──────────────────────────────────────────────────────────────────

const MAX_FILE_BYTES = 2 * 1024 * 1024; // 2 MB per-file read cap

const ALLOWED_EXTENSIONS = [
    'php', 'js', 'mjs', 'ts', 'json', 'css', 'html', 'twig', 'md', 'txt',
    'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
    'woff', 'woff2', 'ttf', 'eot',
    'yml', 'yaml', 'xml', 'xliff', 'po', 'mo',
    'sql', 'lock',
];

const DENIED_EXTENSIONS = ['phar', 'phtml', 'pht', 'sh', 'bat', 'cmd', 'exe', 'so', 'dll'];

/**
 * Sinks ripgrep'd for in plugin-security-scan.md. Each entry is a
 * regex (without delimiters) plus a severity and description. Matches
 * inside strings are grounds for at least a warning; the reviewer
 * decides whether each hit is justified.
 */
const DANGEROUS_SINKS = [
    ['\\beval\\s*\\(', 'error', 'eval() is never allowed in plugins.'],
    ['\\bcreate_function\\s*\\(', 'error', 'create_function() is a deprecated alias for eval().'],
    ['\\bassert\\s*\\(\\s*["\']', 'error', 'assert() with a string argument evaluates the string.'],
    ['preg_replace\\s*\\([^)]*\\/e', 'error', '/e modifier on preg_replace() is an eval sink.'],
    ['\\bpopen\\s*\\(', 'error', 'popen() invokes a shell.'],
    ['\\bpassthru\\s*\\(', 'error', 'passthru() invokes a shell.'],
    ['\\bshell_exec\\s*\\(', 'error', 'shell_exec() invokes a shell.'],
    ['\\bsystem\\s*\\(', 'error', 'system() invokes a shell.'],
    ['\\bproc_open\\s*\\(', 'error', 'proc_open() invokes a shell.'],
    ['\\bpcntl_exec\\s*\\(', 'error', 'pcntl_exec() invokes a shell.'],
    ['\\bextract\\s*\\(\\s*\\$_', 'error', 'extract() on $_POST/$_GET/$_REQUEST is a variable injection sink.'],
    ['\\bparse_str\\s*\\(\\s*\\$_', 'error', 'parse_str() on user input is a variable injection sink.'],
    ['\\bunserialize\\s*\\(', 'warning', 'unserialize() is dangerous on attacker-reachable input; document its source.'],
    ['\\bbase64_decode\\s*\\(', 'warning', 'base64_decode() of a bundled blob is a common obfuscation pattern; justify it.'],
    ['\\bfile_put_contents\\s*\\(', 'warning', 'file_put_contents() — must stay inside the plugin directory.'],
    ['\\bchmod\\s*\\(', 'warning', 'chmod() outside the plugin dir is not allowed.'],
    ['\\bchown\\s*\\(', 'warning', 'chown() is almost never needed in a plugin.'],
    ['\\bcurl_init\\s*\\(', 'info', 'Outbound HTTP call — declare network.outbound.'],
    ['file_get_contents\\s*\\([\'"]https?:', 'info', 'Outbound HTTP call — declare network.outbound.'],
    ['fsockopen\\s*\\(', 'warning', 'Raw socket. Document the target host.'],
    ['\\b_\\s*\\(', 'warning', "Plain _() translates from the core 'messages' textdomain. Use dgettext('{pluginId}', '…') instead."],
    ['\\bgettext\\s*\\(', 'warning', "Plain gettext() translates from the core 'messages' textdomain. Use dgettext('{pluginId}', '…') instead."],
];

(function (): void {
    $argv = $_SERVER['argv'] ?? [];
    array_shift($argv);

    $json = false;
    $pluginDir = null;
    foreach ($argv as $arg) {
        if ($arg === '--json') {
            $json = true;
            continue;
        }
        if (str_starts_with($arg, '--')) {
            usage();
            exit(2);
        }
        if ($pluginDir === null) {
            $pluginDir = $arg;
        }
    }

    if ($pluginDir === null) {
        usage();
        exit(2);
    }

    $pluginDir = rtrim((string) realpath($pluginDir) ?: $pluginDir, '/');
    if (!is_dir($pluginDir)) {
        fwrite(STDERR, "plugin-scan: not a directory: {$pluginDir}\n");
        exit(2);
    }

    $findings = [];
    $summary = [
        'dir' => $pluginDir,
        'files' => 0,
        'bytes' => 0,
    ];

    checkManifest($pluginDir, $findings);
    walkPluginFiles($pluginDir, $findings, $summary);
    reportNetworkAndFsReach($pluginDir, $findings);

    $hasError = countBySeverity($findings, 'error') > 0;

    if ($json) {
        echo json_encode([
            'summary' => $summary,
            'findings' => $findings,
            'stats' => [
                'errors' => countBySeverity($findings, 'error'),
                'warnings' => countBySeverity($findings, 'warning'),
                'infos' => countBySeverity($findings, 'info'),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        printHumanReport($pluginDir, $summary, $findings);
    }

    exit($hasError ? 1 : 0);
})();

// ──────────────────────────────────────────────────────────────────
//  Manifest validation (plugin.json)
// ──────────────────────────────────────────────────────────────────

function checkManifest(string $pluginDir, array &$findings): void
{
    $path = $pluginDir . '/plugin.json';
    if (!is_file($path)) {
        $findings[] = finding('error', 'manifest.missing', 'plugin.json', 0, 'plugin.json is required but missing.');
        return;
    }

    $raw = (string) file_get_contents($path);
    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $findings[] = finding('error', 'manifest.json', 'plugin.json', 0, 'plugin.json is not valid JSON: ' . $e->getMessage());
        return;
    }
    if (!is_array($data)) {
        $findings[] = finding('error', 'manifest.shape', 'plugin.json', 0, 'plugin.json must be a JSON object.');
        return;
    }

    $required = ['id', 'name', 'version', 'mainClass'];
    foreach ($required as $key) {
        if (empty($data[$key]) || !is_string($data[$key])) {
            $findings[] = finding('error', 'manifest.field', 'plugin.json', 0, "plugin.json is missing required field: {$key}");
        }
    }

    if (isset($data['id']) && is_string($data['id']) && !preg_match('/^[a-z0-9][a-z0-9-]*$/', $data['id'])) {
        $findings[] = finding('error', 'manifest.id', 'plugin.json', 0, 'plugin.json id must be kebab-case.');
    }

    $type = $data['type'] ?? 'community';
    if ($type !== 'community' && $type !== 'core') {
        $findings[] = finding('error', 'manifest.type', 'plugin.json', 0, 'plugin.json type must be "community" or "core".');
    }
    if ($type !== 'community') {
        $findings[] = finding('info', 'manifest.type', 'plugin.json', 0, 'This scanner is designed for community plugins. Core plugin reviews follow plugin-migration.md instead.');
    }

    // mainClass file should actually exist on disk.
    if (!empty($data['mainClass']) && is_string($data['mainClass'])) {
        $class = $data['mainClass'];
        $shortClass = substr($class, strrpos($class, '\\') + 1);
        $candidate = $pluginDir . '/src/' . $shortClass . '.php';
        if (!is_file($candidate)) {
            $findings[] = finding('warning', 'manifest.mainClass', 'plugin.json', 0,
                "mainClass {$class} is declared but src/{$shortClass}.php does not exist. " .
                "Make sure the PSR-4 layout matches the namespace."
            );
        }
    }

    // routesFile, if declared, must be inside the plugin dir and exist.
    if (!empty($data['routesFile']) && is_string($data['routesFile'])) {
        $routesFile = $data['routesFile'];
        if (str_contains($routesFile, '..') || str_starts_with($routesFile, '/')) {
            $findings[] = finding('error', 'manifest.routesFile', 'plugin.json', 0, 'routesFile must be a relative path inside the plugin directory.');
        } elseif (!is_file($pluginDir . '/' . $routesFile)) {
            $findings[] = finding('warning', 'manifest.routesFile', 'plugin.json', 0, "routesFile {$routesFile} does not exist on disk.");
        }
    }

    // Risk / permissions hints.
    $findings[] = finding('info', 'manifest.summary', 'plugin.json', 0,
        'Manifest ok: id=' . ($data['id'] ?? '?') .
        ' version=' . ($data['version'] ?? '?') .
        ' type=' . $type
    );
}

// ──────────────────────────────────────────────────────────────────
//  File walk — extension allowlist, dangerous PHP sinks,
//  hidden files, unsafe paths, bundle size.
// ──────────────────────────────────────────────────────────────────

function walkPluginFiles(string $pluginDir, array &$findings, array &$summary): void
{
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir()) {
            $segments = explode('/', str_replace('\\', '/', substr($item->getPathname(), strlen($pluginDir) + 1)));
            foreach ($segments as $seg) {
                if ($seg === '..' || $seg === '.') {
                    $findings[] = finding('error', 'path.traversal', $item->getPathname(), 0, 'Directory contains traversal segment.');
                }
            }
            continue;
        }

        $rel = substr($item->getPathname(), strlen($pluginDir) + 1);
        $rel = str_replace('\\', '/', $rel);
        $basename = basename($rel);

        $summary['files']++;
        $summary['bytes'] += (int) $item->getSize();

        // Hidden files. Allow a short whitelist.
        if ($basename !== '' && $basename[0] === '.' && !in_array($basename, ['.editorconfig', '.gitattributes'], true)) {
            $findings[] = finding('error', 'path.hidden', $rel, 0, 'Hidden file not allowed in plugin zip.');
            continue;
        }

        // Extension allowlist / denylist.
        $ext = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        if ($ext === '') {
            if (!in_array(strtoupper($basename), ['LICENSE', 'README', 'CHANGELOG', 'NOTICE'], true)) {
                $findings[] = finding('error', 'ext.missing', $rel, 0, 'File has no extension.');
            }
        } elseif (in_array($ext, DENIED_EXTENSIONS, true)) {
            $findings[] = finding('error', 'ext.denied', $rel, 0, "Disallowed file extension .{$ext} — the installer rejects this.");
        } elseif (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
            $findings[] = finding('warning', 'ext.unknown', $rel, 0, "Extension .{$ext} is not on the plugin allowlist. Remove or justify.");
        }

        // Sink scan — PHP and JS files only.
        if ($ext === 'php' || $ext === 'js' || $ext === 'mjs' || $ext === 'ts') {
            scanSinks($pluginDir . '/' . $rel, $rel, $findings);
        }
    }
}

function scanSinks(string $absPath, string $relPath, array &$findings): void
{
    $size = (int) @filesize($absPath);
    if ($size === 0 || $size > MAX_FILE_BYTES) {
        return;
    }
    $content = (string) @file_get_contents($absPath);
    if ($content === '') {
        return;
    }

    // Track /* ... */ block comments across lines — matches inside
    // comments are false positives (e.g. "use dgettext(...) instead"
    // in a docblock). We do a simple line-level state machine.
    $inBlockComment = false;
    $lines = explode("\n", $content);
    foreach ($lines as $lineNumber => $line) {
        $trimmed = ltrim($line);

        // Entire-line fast skip for obvious comments.
        if ($inBlockComment) {
            if (str_contains($line, '*/')) {
                $inBlockComment = false;
            }
            continue;
        }
        if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
            continue;
        }
        if (str_starts_with($trimmed, '/*')) {
            if (!str_contains($line, '*/')) {
                $inBlockComment = true;
            }
            continue;
        }

        // Strip inline comments from the end of the line.
        $stripped = preg_replace('/(^|\\s)(\\/\\/|#).*$/', '', $line);
        if (!is_string($stripped)) {
            $stripped = $line;
        }
        // Strip inline /* ... */ blocks that open and close on the same line.
        $stripped = preg_replace('/\\/\\*.*?\\*\\//', ' ', $stripped);
        if (!is_string($stripped)) {
            $stripped = $line;
        }

        foreach (DANGEROUS_SINKS as [$regex, $severity, $description]) {
            if (preg_match('/' . $regex . '/', $stripped)) {
                $findings[] = finding(
                    $severity,
                    'sink',
                    $relPath,
                    $lineNumber + 1,
                    $description
                );
            }
        }
    }
}

// ──────────────────────────────────────────────────────────────────
//  Outbound hostname + filesystem reach enumeration
// ──────────────────────────────────────────────────────────────────

function reportNetworkAndFsReach(string $pluginDir, array &$findings): void
{
    $hosts = [];
    $fsTargets = [];

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $item) {
        /** @var SplFileInfo $item */
        if (!$item->isFile()) {
            continue;
        }
        $ext = strtolower((string) pathinfo($item->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['php', 'js', 'mjs', 'ts'], true)) {
            continue;
        }
        if ((int) $item->getSize() > MAX_FILE_BYTES) {
            continue;
        }
        $content = (string) @file_get_contents($item->getPathname());

        if (preg_match_all('#https?://([A-Za-z0-9.\\-]+)#', $content, $matches)) {
            foreach ($matches[1] as $host) {
                $hosts[$host] = true;
            }
        }

        if (preg_match_all('/file_put_contents\\s*\\(\\s*["\']([^"\']+)["\']/', $content, $matches)) {
            foreach ($matches[1] as $target) {
                $fsTargets[$target] = true;
            }
        }
    }

    if (!empty($hosts)) {
        $findings[] = finding(
            'info',
            'network.hosts',
            '(summary)',
            0,
            'Outbound hostnames referenced in source: ' . implode(', ', array_keys($hosts)) .
            '. Every one must be named in riskSummary and covered by network.outbound.'
        );
    }
    if (!empty($fsTargets)) {
        $findings[] = finding(
            'warning',
            'fs.targets',
            '(summary)',
            0,
            'Literal file_put_contents targets: ' . implode(', ', array_keys($fsTargets)) .
            '. All must resolve inside the plugin directory — declare fs.write otherwise.'
        );
    }
}

// ──────────────────────────────────────────────────────────────────
//  Reporting helpers
// ──────────────────────────────────────────────────────────────────

function finding(string $severity, string $code, string $file, int $line, string $message): array
{
    return compact('severity', 'code', 'file', 'line', 'message');
}

function countBySeverity(array $findings, string $severity): int
{
    $n = 0;
    foreach ($findings as $f) {
        if ($f['severity'] === $severity) {
            $n++;
        }
    }
    return $n;
}

function printHumanReport(string $pluginDir, array $summary, array $findings): void
{
    echo "\n=== plugin-scan ===\n";
    echo "dir:    {$pluginDir}\n";
    echo "files:  {$summary['files']}\n";
    echo "bytes:  {$summary['bytes']}\n\n";

    $errors = countBySeverity($findings, 'error');
    $warnings = countBySeverity($findings, 'warning');
    $infos = countBySeverity($findings, 'info');

    foreach (['error', 'warning', 'info'] as $sev) {
        $label = strtoupper($sev);
        $prefix = $sev === 'error' ? '✗' : ($sev === 'warning' ? '!' : 'i');
        foreach ($findings as $f) {
            if ($f['severity'] !== $sev) {
                continue;
            }
            $loc = $f['line'] > 0 ? ($f['file'] . ':' . $f['line']) : $f['file'];
            printf("%s [%s]  %s  %s\n", $prefix, $label, $loc, $f['message']);
        }
    }

    echo "\n";
    echo "errors:   {$errors}\n";
    echo "warnings: {$warnings}\n";
    echo "infos:    {$infos}\n";
    echo "\n";
    if ($errors > 0) {
        echo "Result: FAIL — fix every error before submitting the plugin.\n";
    } elseif ($warnings > 0) {
        echo "Result: PASS with warnings — review each warning and justify it in your PR.\n";
    } else {
        echo "Result: PASS — no errors, no warnings.\n";
    }
}

function usage(): void
{
    echo <<<USAGE
Usage: php scripts/plugin-scan.php [--json] <plugin-dir>

Runs the plugin-security-scan review checklist against a plugin
directory on disk. Use it on your own plugin BEFORE opening a PR
against src/plugins/approved-plugins.json — it catches the same
issues the maintainers would catch during review.

Exit codes:
  0  — no errors
  1  — at least one error
  2  — invalid invocation

Options:
  --json   emit machine-readable JSON instead of the human report

Examples:
  php scripts/plugin-scan.php src/plugins/community/my-plugin
  php scripts/plugin-scan.php --json src/plugins/community/my-plugin | jq

USAGE;
}
