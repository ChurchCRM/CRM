#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * OpenAPI specification generator for ChurchCRM.
 *
 * Generates OpenAPI (Swagger) documentation from route file annotations.
 * This is a build/documentation tool and should not be shipped with production code.
 *
 * Usage: php openapi-generate.php [paths...] [--output file] [--format yaml|json] [--exclude path]
 *
 * Examples:
 *   php openapi-generate.php openapi-public-info.php ../src/api/routes/public/ --output public-api.yaml
 *   php openapi-generate.php openapi-private-info.php ../src/api/routes/ --output private-api.yaml
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

// Parse command-line arguments
$options = getopt('o:f:e::d', ['output:', 'format:', 'exclude:', 'debug']);
$paths = [];

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];

    if (in_array($arg, ['-d', '--debug'])) {
        continue;
    }
    if (in_array($arg, ['-o', '--output', '-f', '--format', '-e', '--exclude'])) {
        $i++; // Skip next arg (the value)
        continue;
    }

    if (!str_starts_with($arg, '-')) {
        $paths[] = $arg;
    }
}

if (empty($paths)) {
    fwrite(STDERR, "Usage: php openapi-generate.php [paths...] [--output file] [--format yaml|json] [--exclude path]\n");
    exit(1);
}

// Resolve absolute paths (relative to this script's directory or src/)
$scriptDir = __DIR__;
$srcDir = __DIR__ . '/../../src';

$resolvePath = static function (string $path) use ($srcDir): string {
    // If already absolute, use as-is
    if ($path[0] === '/') {
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

/** @var string[] $resolvedPaths */
$resolvedPaths = array_map($resolvePath, $paths);

/** @var string[] $excludeResolved */
$excludeResolved = [];
if (!empty($options['e']) || !empty($options['exclude'])) {
    $excludes = array_filter(array_merge(
        (array)($options['e'] ?? []),
        (array)($options['exclude'] ?? [])
    ));
    $excludeResolved = array_map($resolvePath, $excludes);
}

// Collect files to scan
$filesToScan = [];

foreach ($resolvedPaths as $rawPath) {
    if (!file_exists($rawPath)) {
        fwrite(STDERR, "Warning: path does not exist: $rawPath\n");
        continue;
    }

    if (is_file($rawPath)) {
        $filesToScan[] = $rawPath;
    } else {
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
}

// Generate OpenAPI spec
$debug = !empty($options['d']);
$generator = new Generator(new DefaultLogger());
$generator->setAnalyser(new ChurchCRMDocBlockAnalyser());

$openapi = $generator->generate($filesToScan);

if ($openapi === null) {
    fwrite(STDERR, "Failed to generate OpenAPI spec.\n");
    exit(1);
}

// Output
$format = strtolower($options['f'] ?? $options['format'] ?? 'yaml');
$output = $options['o'] ?? $options['output'] ?? null;

if ($output) {
    // If output is relative, make it relative to the script directory or docs/
    if (!str_starts_with($output, '/')) {
        $output = $scriptDir . '/' . $output;
    }
    
    if (is_dir($output)) {
        $output = rtrim($output, '/') . '/openapi.' . $format;
    }
    
    $openapi->saveAs($output, $format);
    fwrite(STDERR, "✓ OpenAPI spec written to: $output\n");
} else {
    echo ($format === 'json' ? $openapi->toJson() : $openapi->toYaml()) . "\n";
}

exit(0);
