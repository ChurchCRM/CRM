<?php

declare(strict_types=1);

/**
 * @license MIT
 */

namespace ChurchCRM\Api\OpenAPI;

use OpenApi\Analysers\AnalyserInterface;
use OpenApi\Analysers\DocBlockParser;
use OpenApi\Analysis;
use OpenApi\Context;
use OpenApi\Generator;
use PhpParser\Comment;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/**
 * Custom analyser for standalone function docblocks.
 *
 * Swagger-php v6+ uses reflection, which only works on autoloadable classes.
 * ChurchCRM's API routes are organized as standalone functions with @OA\ docblock annotations.
 * This analyser extracts those annotations from route files using PHP parser.
 *
 * This follows the swagger-php AnalyserInterface contract and integrates cleanly
 * with the standard Generator pipeline.
 *
 * @see https://github.com/zircote/swagger-php/blob/master/src/Analysers/AnalyserInterface.php
 */
final class ChurchCRMDocBlockAnalyser implements AnalyserInterface
{
    private ?DocBlockParser $docBlockParser = null;
    private ?Generator $generator = null;

    public function setGenerator(Generator $generator): static
    {
        $this->generator = $generator;
        $this->docBlockParser = new DocBlockParser($generator->getAliases());

        return $this;
    }

    public function fromFile(string $filename, Context $context): Analysis
    {
        if ($this->docBlockParser === null || $this->generator === null) {
            throw new \RuntimeException('Generator must be set before calling fromFile()');
        }

        $analysis = new Analysis([], $context);
        $source = file_get_contents($filename);

        if ($source === false) {
            $context->logger->warning("Cannot read file: $filename");

            return $analysis;
        }

        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $stmts = $parser->parse($source);
        } catch (\Throwable $e) {
            $context->logger->warning("Parse error in $filename: " . $e->getMessage());

            return $analysis;
        }

        if ($stmts === null) {
            return $analysis;
        }

        // Collect docblocks: first check file-level comments, then traverse AST nodes
        $docBlocks = [];
        $seen = [];

        // File-level docblocks (e.g., top-of-file @OA\ blocks in info files)
        // are attached to the first statement if it exists
        if (!empty($stmts) && isset($stmts[0])) {
            foreach ($stmts[0]->getAttribute('comments', []) as $comment) {
                if ($comment instanceof Comment\Doc) {
                    $text = $comment->getText();
                    if (!isset($seen[$text])) {
                        $seen[$text] = true;
                        $docBlocks[] = ['text' => $text, 'line' => $comment->getStartLine()];
                    }
                }
            }
        }

        // Extract all docblock comments from all nodes via AST traversal
        $traverser = new NodeTraverser();
        $visitor = new class($docBlocks, $seen) extends NodeVisitorAbstract {
            /** @var array<array{text: string, line: int}> */
            public array $docBlocks;
            /** @var array<string, true> */
            private array $seen;

            public function __construct(&$docBlocks, &$seen)
            {
                $this->docBlocks = &$docBlocks;
                $this->seen = &$seen;
            }

            public function enterNode(\PhpParser\Node $node): null
            {
                foreach ($node->getAttribute('comments', []) as $comment) {
                    if (!$comment instanceof Comment\Doc) {
                        continue;
                    }

                    $text = $comment->getText();
                    // Deduplicate: same comment object can be attached to multiple nodes
                    if (isset($this->seen[$text])) {
                        continue;
                    }

                    $this->seen[$text] = true;
                    $this->docBlocks[] = [
                        'text' => $text,
                        'line' => $comment->getStartLine(),
                    ];
                }

                return null;
            }
        };

        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        // Parse OpenAPI annotations from extracted docblocks
        foreach ($docBlocks as $entry) {
            $blockContext = new Context([
                'filename' => $filename,
                'line' => $entry['line'],
                'logger' => $context->logger,
            ]);

            $annotations = $this->docBlockParser->fromComment($entry['text'], $blockContext);
            if (!empty($annotations)) {
                $analysis->addAnnotations($annotations, $blockContext);
            }
        }

        return $analysis;
    }
}
