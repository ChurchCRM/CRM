#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * OpenAPI specification generator for ChurchCRM.
 *
 * Generates OpenAPI (Swagger) documentation from route file annotations.
 * This is a build/documentation tool and should not be shipped with production code.
 *
 * Usage: php generate.php <info-file> [paths...] [--output FILE] [--format yaml|json] [--exclude PATH]... [--debug]
 *
 * Argument order: flags may appear before, between or after the positional
 * arguments — the first positional is the *-info.php file, the rest are the
 * files/directories to scan. This script deliberately does NOT use getopt():
 * getopt() stops parsing at the first non-option argument, so the flags the
 * composer scripts pass after the scan paths were silently discarded and the
 * spec went to stdout instead of --output (issue #9824). Everything after a
 * bare `--` is treated as a positional path.
 *
 * Dependencies: the generator needs `zircote/swagger-php`, which is a
 * **dev dependency** of src/composer.json. `npm run build` runs
 * `composer install --no-dev`, so on a freshly built tree run
 * `composer install` (without --no-dev) in src/ before generating.
 *
 * Examples:
 *   php generate.php openapi-public-info.php ../src/api/routes/public/ --output public-api.yaml
 *   php generate.php --output private-api.yaml openapi-private-info.php ../src/api/routes/
 *
 * @license MIT
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

// Resolve vendor autoloader
$vendorAutoload = __DIR__ . '/../../src/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    fwrite(STDERR, "vendor/autoload.php not found at: $vendorAutoload\nRun `composer install` in the src/ directory first.\n");
    exit(1);
}
require_once $vendorAutoload;

use ChurchCRM\Api\OpenAPI\ChurchCRMDocBlockAnalyser;
use OpenApi\Generator;
use OpenApi\Loggers\DefaultLogger;
use Symfony\Component\Finder\Finder;

$usage = <<<USAGE
Usage: php generate.php <info-file> [paths...] [options]

Options (may be given before, between or after the positional arguments):
  -o, --output FILE        Write the spec to FILE instead of stdout
  -f, --format yaml|json   Output format (default: yaml)
  -e, --exclude PATH       Exclude PATH from the scan (repeatable)
  -d, --debug              List the scanned files on stderr
  --                       Treat every following argument as a path

USAGE;

$fail = static function (string $message) use ($usage): void {
    fwrite(STDERR, "Error: $message\n\n$usage");
    exit(1);
};

// ---------------------------------------------------------------------------
// Parse command-line arguments (see the header note: no getopt() here).
// ---------------------------------------------------------------------------
/** @var string[] $paths */
$paths = [];
/** @var string[] $excludes */
$excludes = [];
$output = null;
$format = 'yaml';
$debug = false;
$positionalsOnly = false;

$takeValue = static function (string $flag, ?string $inline, int &$index) use ($argv, $argc, $fail): string {
    if ($inline !== null) {
        if ($inline === '') {
            $fail("missing value for $flag");
        }

        return $inline;
    }
    if ($index + 1 >= $argc) {
        $fail("missing value for $flag");
    }

    return $argv[++$index];
};

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];

    if ($positionalsOnly || $arg === '' || $arg === '-' || !str_starts_with($arg, '-')) {
        $paths[] = $arg;
        continue;
    }

    if ($arg === '--') {
        $positionalsOnly = true;
        continue;
    }

    // Split "--name=value" (and "-o=value") into flag + inline value.
    $flag = $arg;
    $inline = null;
    if (str_contains($arg, '=')) {
        [$flag, $inline] = explode('=', $arg, 2);
    }

    // Short flags may carry their value attached: "-oFILE".
    if ($inline === null && strlen($flag) > 2 && !str_starts_with($flag, '--')) {
        $inline = substr($flag, 2);
        $flag = substr($flag, 0, 2);
    }

    switch ($flag) {
        case '-o':
        case '--output':
            $output = $takeValue($flag, $inline, $i);
            break;

        case '-f':
        case '--format':
            $format = strtolower($takeValue($flag, $inline, $i));
            break;

        case '-e':
        case '--exclude':
            $excludes[] = $takeValue($flag, $inline, $i);
            break;

        case '-d':
        case '--debug':
            if ($inline !== null) {
                $fail("$flag does not take a value");
            }
            $debug = true;
            break;

        case '-h':
        case '--help':
            fwrite(STDOUT, $usage);
            exit(0);

        default:
            $fail("unknown option: $arg");
    }
}

if (empty($paths)) {
    $fail('no info file or scan paths given');
}

if (!in_array($format, ['yaml', 'json'], true)) {
    $fail("unsupported --format '$format' (expected yaml or json)");
}

// Resolve absolute paths (relative to this script's directory or src/)
$srcDir = __DIR__ . '/../../src';

$resolvePath = static function (string $path) use ($srcDir): string {
    // If already absolute, use as-is
    if ($path === '' || $path[0] === '/') {
        return $path;
    }
    // Try relative to src/ first
    $attempt = $srcDir . '/' . $path;
    if (file_exists($attempt)) {
        return $attempt;
    }
    // Try relative to current directory
    return realpath($path) ?: $path;
};

// Collect files to scan. A missing info file or scan path is a hard error:
// silently scanning nothing is what made issue #9824 invisible for so long.
/** @var string[] $excludeResolved */
$excludeResolved = [];
foreach ($excludes as $exclude) {
    $resolved = $resolvePath($exclude);
    if (!file_exists($resolved)) {
        $fail("--exclude path does not exist: $exclude (resolved to $resolved)");
    }
    $excludeResolved[] = $resolved;
}

/** @var string[] $filesToScan */
$filesToScan = [];

foreach ($paths as $path) {
    $rawPath = $resolvePath($path);

    if (!file_exists($rawPath)) {
        $fail("path does not exist: $path (resolved to $rawPath)");
    }

    if (is_file($rawPath)) {
        $filesToScan[] = $rawPath;
        continue;
    }

    $finder = new Finder();
    $finder->files()->name('*.php')->in($rawPath)->followLinks();

    foreach ($excludeResolved as $excl) {
        if (str_starts_with($excl, $rawPath)) {
            $rel = ltrim(substr($excl, strlen($rawPath)), DIRECTORY_SEPARATOR);
            if ($rel !== '') {
                $finder->exclude($rel);
            }
        }
    }

    foreach ($finder as $file) {
        $filesToScan[] = $file->getPathname();
    }
}

if (empty($filesToScan)) {
    $fail('no PHP files matched the given paths');
}

if ($debug) {
    fwrite(STDERR, sprintf("Scanning %d file(s):\n", count($filesToScan)));
    foreach ($filesToScan as $file) {
        fwrite(STDERR, "  $file\n");
    }
}

// Generate OpenAPI spec
$generator = new Generator(new DefaultLogger());
$generator->setAnalyser(new ChurchCRMDocBlockAnalyser());

$openapi = $generator->generate($filesToScan);

if ($openapi === null) {
    fwrite(STDERR, "Failed to generate OpenAPI spec.\n");
    exit(1);
}

// Output
if ($output !== null) {
    // If output is relative, resolve it against src/ (where composer scripts run from)
    if (!str_starts_with($output, '/')) {
        $output = $srcDir . '/' . $output;
    }

    if (is_dir($output)) {
        $output = rtrim($output, '/') . '/openapi.' . $format;
    }

    // Normalize the path to remove .. and .
    $outputDir = realpath(dirname($output));
    if ($outputDir === false) {
        $fail('output directory does not exist: ' . dirname($output));
    }
    $output = $outputDir . '/' . basename($output);

    $openapi->saveAs($output, $format);
    clearstatcache(true, $output);
    fwrite(STDERR, sprintf("✓ OpenAPI spec written to: %s (%d bytes, %d file(s) scanned)\n", $output, (int) filesize($output), count($filesToScan)));
} else {
    echo ($format === 'json' ? $openapi->toJson() : $openapi->toYaml()) . "\n";
}

exit(0);
