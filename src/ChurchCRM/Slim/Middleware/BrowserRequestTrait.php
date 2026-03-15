<?php

namespace ChurchCRM\Slim\Middleware;

use Psr\Http\Message\ServerRequestInterface;

trait BrowserRequestTrait
{
    /**
     * Check if request is from a browser (expects HTML) vs API client (expects JSON).
     */
    protected function isBrowserRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        // API routes should always return JSON
        if (str_contains($path, '/api/')) {
            return false;
        }

        // Check Accept header - browsers typically send text/html
        $acceptHeader = $request->getHeaderLine('Accept');
        if (!empty($acceptHeader)) {
            // If client explicitly wants JSON, it's an API request
            if (str_contains($acceptHeader, 'application/json') && !str_contains($acceptHeader, 'text/html')) {
                return false;
            }
            // If client accepts HTML, treat as browser
            if (str_contains($acceptHeader, 'text/html')) {
                return true;
            }
        }

        // Check X-Requested-With header (AJAX requests)
        if ($request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        // Default to browser for non-API routes
        return true;
    }
}
