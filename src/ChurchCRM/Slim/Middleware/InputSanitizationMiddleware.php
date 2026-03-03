<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sanitizes request body fields before passing to the route handler.
 *
 * Fields are sanitized in-place; only fields that are present in the body
 * are affected. Missing fields are left absent (not set to empty string).
 *
 * Supported sanitization types:
 *  - 'text' → InputUtils::sanitizeText() (trims and strips HTML tags)
 *  - 'html' → InputUtils::sanitizeHTML() (allows safe HTML, strips scripts)
 *
 * Usage:
 *   ->add(new InputSanitizationMiddleware([
 *       'title'   => 'text',
 *       'content' => 'html',
 *   ]))
 */
class InputSanitizationMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, 'text'|'html'> $fieldMap Map of field name → sanitization type.
     */
    public function __construct(private readonly array $fieldMap) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (is_array($body)) {
            foreach ($this->fieldMap as $field => $type) {
                if (isset($body[$field])) {
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
