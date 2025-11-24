<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Utils\CSRFUtils;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * CSRF Protection Middleware
 * 
 * Validates CSRF tokens for state-changing requests (POST, PUT, DELETE, PATCH).
 * Automatically extracts and validates CSRF tokens from request body.
 */
class CSRFMiddleware implements MiddlewareInterface
{
    private string $formId;

    /**
     * @param string $formId Unique identifier for the form/route
     */
    public function __construct(string $formId)
    {
        $this->formId = $formId;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        
        // Only validate CSRF tokens for state-changing methods
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $body = $request->getParsedBody();

            // First try: token in parsed body (standard form posts)
            $valid = false;
            if (is_array($body) && CSRFUtils::verifyRequest($body, $this->formId)) {
                $valid = true;
            }

            // Second try: token in common headers (for AJAX/fetch submissions)
            if (!$valid) {
                $headerToken = $request->getHeaderLine('X-CSRF-Token') ?: $request->getHeaderLine('X-XSRF-TOKEN');
                if (!empty($headerToken) && CSRFUtils::validateToken($headerToken, $this->formId)) {
                    $valid = true;
                }
            }

            if (!$valid) {
                throw new HttpForbiddenException($request, 'Invalid or missing CSRF token');
            }
        }
        
        return $handler->handle($request);
    }
}
