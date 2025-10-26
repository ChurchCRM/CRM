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
            
            // Verify CSRF token
            if (!is_array($body) || !CSRFUtils::verifyRequest($body, $this->formId)) {
                throw new HttpForbiddenException($request, 'Invalid or missing CSRF token');
            }
        }
        
        return $handler->handle($request);
    }
}
