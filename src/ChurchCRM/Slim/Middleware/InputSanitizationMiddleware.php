<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sanitizes and validates request body fields before passing to the route handler.
 *
 * Fields are sanitized in-place; only fields that are present in the body
 * are affected. Missing fields are left absent (not set to empty string).
 *
 * Supported sanitization types:
 *  - 'text' → InputUtils::sanitizeText() (trims and strips HTML tags)
 *  - 'html' → InputUtils::sanitizeHTML() (allows safe HTML, strips scripts)
 *  - 'int'  → filter_var(FILTER_VALIDATE_INT) — field MUST be present and a valid integer;
 *             returns HTTP 400 if absent or not a valid integer
 *
 * Usage:
 *   ->add(new InputSanitizationMiddleware([
 *       'title'   => 'text',
 *       'content' => 'html',
 *       'level'   => 'int',
 *   ]))
 */
class InputSanitizationMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, 'text'|'html'|'int'> $fieldMap Map of field name → sanitization type.
     */
    public function __construct(private readonly array $fieldMap) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if ($body === null) {
            $body = [];
        }

        if (is_array($body)) {
            foreach ($this->fieldMap as $field => $type) {
                if ($type === 'int') {
                    // Integer fields are strictly required and validated:
                    // absent or non-integer values result in HTTP 400.
                    if (!array_key_exists($field, $body)) {
                        return SlimUtils::renderJSON(
                            new Response(),
                            ['error' => "$field is required"],
                            400
                        );
                    }
                    $validated = filter_var(trim((string) $body[$field]), FILTER_VALIDATE_INT);
                    if ($validated === false) {
                        return SlimUtils::renderJSON(
                            new Response(),
                            ['error' => "Invalid integer value for $field"],
                            400
                        );
                    }
                    $body[$field] = $validated;
                } elseif (isset($body[$field])) {
                    $body[$field] = match ($type) {
                        'html'  => InputUtils::sanitizeHTML($body[$field]),
                        default => InputUtils::sanitizeText($body[$field]),
                    };
                }
            }
            $request = $request->withParsedBody($body);
        }

        return $handler->handle($request);
    }
}
