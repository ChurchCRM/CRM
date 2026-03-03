<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Validates request body parameters before passing to the route handler.
 *
 * Supports:
 *  - Required field checks (returns 400 if missing; or blank after trimming for string values)
 *  - Enum checks (returns 400 if value not in allowed list)
 *
 * Usage:
 *   ->add(new RequestParameterValidationMiddleware(
 *       required: ['name'],
 *       enums:    ['status' => ['active', 'inactive']]
 *   ))
 */
class RequestParameterValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param string[] $required Field names that must be present; string values must be non-blank after trimming.
     * @param array<string, string[]> $enums Map of field name → allowed values.
     */
    public function __construct(
        private readonly array $required = [],
        private readonly array $enums = []
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if ($body === null) {
            $body = [];
        } elseif (is_object($body)) {
            $body = (array) $body;
        } elseif (!is_array($body)) {
            return SlimUtils::renderJSON(
                new Response(),
                ['error' => 'Invalid request body; expected a JSON object'],
                400
            );
        }
        foreach ($this->required as $field) {
            $missing = !array_key_exists($field, $body)
                || (is_string($body[$field]) && trim($body[$field]) === '');
            if ($missing) {
                return SlimUtils::renderJSON(
                    new Response(),
                    ['error' => "$field is required"],
                    400
                );
            }
        }

        foreach ($this->enums as $field => $allowed) {
            $value = $body[$field] ?? '';
            if (!in_array($value, $allowed, true)) {
                $errorMsg = $value === ''
                    ? "$field is required. Allowed values: " . implode(', ', $allowed)
                    : "'$value' is not a valid value for $field. Allowed: " . implode(', ', $allowed);
                return SlimUtils::renderJSON(
                    new Response(),
                    ['error' => $errorMsg, 'allowed' => $allowed],
                    400
                );
            }
        }

        return $handler->handle($request);
    }
}
