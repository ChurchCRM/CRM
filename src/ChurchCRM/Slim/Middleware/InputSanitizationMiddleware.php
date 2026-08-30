<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Utils\InputUtils;
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
 *  - 'int'  → filter_var(FILTER_VALIDATE_INT) (validates integer, coerces to int)
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

        if (is_array($body)) {
            foreach ($this->fieldMap as $field => $type) {
                if (isset($body[$field])) {
                    $body[$field] = match ($type) {
                        'int'   => InputUtils::filterInt($body[$field]),
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
