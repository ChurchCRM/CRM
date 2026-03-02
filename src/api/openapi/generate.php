#!/usr/bin/env php
<?php

/**
 * Custom OpenAPI generator for ChurchCRM — swagger-php v6 compatible.
 *
 * swagger-php v6 removed the --legacy flag and its ReflectionAnalyser only processes
 * autoloadable classes.  ChurchCRM's route files use @OA\ docblock annotations on
 * standalone functions and anonymous closures, which the built-in analyser cannot reach.
 *
 * This script replicates the v4 --legacy token-based scanning behaviour by using
 * nikic/php-parser (already a dependency of swagger-php v6) to extract every docblock
 * comment from every PHP node, regardless of whether it is a class, named function,
 * anonymous closure, or free-floating comment.  It then feeds those annotations into
 * the swagger-php v6 generator pipeline to produce a valid OpenAPI spec.
 *
 * Usage (mirrors the removed `bin/openapi --legacy` interface):
 *   php generate.php [path ...] --output <file> --format <yaml|json> --exclude <path>
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

// Resolve the vendor autoloader regardless of the CWD when the script is invoked.
$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    fwrite(STDERR, "vendor/autoload.php not found. Run `composer install` first.\n");
    exit(1);
}
require_once $vendorAutoload;

use OpenApi\Analysers\DocBlockParser;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Generator;
use OpenApi\Loggers\ConsoleLogger;
use PhpParser\Error as ParserError;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

// ---------------------------------------------------------------------------
// Argument parsing (subset of bin/openapi options)
// ---------------------------------------------------------------------------

$options = [
    'output'  => null,
    'format'  => 'yaml',
    'exclude' => [],
    'debug'   => false,
    'version' => null,
];
$paths = [];
$error = null;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    switch ($arg) {
        case '--output':
        case '-o':
            $options['output'] = $argv[++$i] ?? null;
            break;
        case '--format':
        case '-f':
            $options['format'] = $argv[++$i] ?? 'yaml';
            break;
        case '--exclude':
        case '-e':
            $options['exclude'][] = $argv[++$i] ?? '';
            break;
        case '--debug':
        case '-d':
            $options['debug'] = true;
            break;
        case '--version':
            $options['version'] = $argv[++$i] ?? null;
            break;
        default:
            if (str_starts_with($arg, '-')) {
                $error = "Unknown option: $arg";
            } else {
                $paths[] = $arg;
            }
    }
}

if ($error !== null || empty($paths)) {
    fwrite(STDERR, $error ? "Error: $error\n" : "Error: specify at least one source path.\n");
    fwrite(STDERR, "Usage: php generate.php [path ...] [--output file] [--format yaml|json] [--exclude path]\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Node visitor: collects every docblock comment from every AST node.
// ---------------------------------------------------------------------------

class AllDocBlockCollector extends NodeVisitorAbstract
{
    /** @var array<array{comment: string, line: int}> */
    public array $docBlocks = [];

    /** @var array<string, true> Already-seen comment texts (dedup across nodes). */
    private array $seen = [];

    public function enterNode(Node $node): null
    {
        // Use getAttribute('comments') instead of getDocComment() so that
        // orphaned /** */ blocks are also captured.  This matters when two
        // adjacent doc blocks appear before a statement, e.g.:
        //   /** @OA\Post(...) */
        //   /** Plain description */
        //   $group->post(...);
        // In that case getDocComment() returns only the last block, but
        // getAttribute('comments') returns both.
        foreach ($node->getAttribute('comments', []) as $comment) {
            if (!$comment instanceof \PhpParser\Comment\Doc) {
                continue;
            }
            $text = $comment->getText();
            // Deduplicate: the same comment object can be attached to multiple
            // nodes when the AST is traversed (parent + child).
            if (isset($this->seen[$text])) {
                continue;
            }
            $this->seen[$text] = true;
            $this->docBlocks[] = [
                'comment' => $text,
                'line'    => $comment->getStartLine(),
            ];
        }

        return null;
    }
}

// ---------------------------------------------------------------------------
// Build a list of PHP files to scan.
// ---------------------------------------------------------------------------

$phpParser = (new ParserFactory())->createForNewestSupportedVersion();
$logger    = new ConsoleLogger($options['debug']);

/**
 * Resolve a path relative to the current working directory.
 */
$resolvePath = static function (string $path): string {
    return realpath($path) ?: $path;
};

/** @var string[] $excludeResolved */
$excludeResolved = array_map($resolvePath, $options['exclude']);

/** @var \SplFileInfo[] $filesToScan */
$filesToScan = [];

foreach ($paths as $rawPath) {
    $resolved = $resolvePath($rawPath);
    if (!$resolved || !file_exists($resolved)) {
        $logger->warning("Skipping invalid path: $rawPath");
        continue;
    }
    if (is_file($resolved)) {
        $filesToScan[] = new \SplFileInfo($resolved);
    } else {
        $finder = new Finder();
        $finder->files()->name('*.php')->in($resolved)->followLinks();
        foreach ($excludeResolved as $excl) {
            if (str_starts_with($excl, $resolved)) {
                // Make exclude relative to the search root.
                $rel = ltrim(substr($excl, strlen($resolved)), DIRECTORY_SEPARATOR);
                if ($rel !== '') {
                    $finder->exclude($rel);
                }
            }
        }
        foreach ($finder as $file) {
            $filesToScan[] = $file;
        }
    }
}

// ---------------------------------------------------------------------------
// Scan each file and extract @OA\ docblock annotations.
// ---------------------------------------------------------------------------

$generator = new Generator($logger);
if ($options['version'] !== null) {
    $generator->setVersion($options['version']);
}

$rootContext   = new Context(['version' => $generator->getVersion(), 'logger' => $logger]);
$analysis      = new Analysis([], $rootContext);
$docBlockParser = new DocBlockParser($generator->getAliases());

foreach ($filesToScan as $fileInfo) {
    $filename = $fileInfo->getPathname();

    // Skip excluded paths.
    foreach ($excludeResolved as $excl) {
        if (str_starts_with($filename, $excl)) {
            continue 2;
        }
    }

    $source = file_get_contents($filename);
    if ($source === false) {
        $logger->warning("Cannot read file: $filename");
        continue;
    }

    try {
        $stmts = $phpParser->parse($source);
    } catch (ParserError $e) {
        $logger->warning("Parse error in $filename: " . $e->getMessage());
        continue;
    }

    if ($stmts === null) {
        continue;
    }

    $traverser = new NodeTraverser();
    $visitor   = new AllDocBlockCollector();
    $traverser->addVisitor($visitor);
    $traverser->traverse($stmts);

    foreach ($visitor->docBlocks as $entry) {
        $context = new Context([
            'filename' => $filename,
            'line'     => $entry['line'],
            'logger'   => $logger,
        ]);

        $annotations = $docBlockParser->fromComment($entry['comment'], $context);
        if (!empty($annotations)) {
            $analysis->addAnnotations($annotations, $context);
        }
    }
}

// ---------------------------------------------------------------------------
// Run the swagger-php v6 processor pipeline on the pre-built analysis.
// ---------------------------------------------------------------------------

$openapi = $generator->generate([], $analysis);

if ($openapi === null) {
    fwrite(STDERR, "Failed to generate OpenAPI spec.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Output.
// ---------------------------------------------------------------------------

$format = strtolower($options['format']);
if ($options['output'] !== null) {
    $outputPath = $options['output'];
    if (is_dir($outputPath)) {
        $outputPath .= '/openapi.yaml';
    }
    $openapi->saveAs($outputPath, $format);
} else {
    echo ($format === 'json' ? $openapi->toJson() : $openapi->toYaml()) . "\n";
}

exit($logger->loggedMessageAboveNotice() ? 1 : 0);
