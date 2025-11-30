<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseAuthRoleMiddleware implements MiddlewareInterface
{
    protected User $user;

    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $this->user = AuthenticationManager::getCurrentUser();
        } catch (\Throwable $ex) {
            $logger = LoggerUtils::getAppLogger();
            $logger->warning('User authentication failed in role middleware', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'exception' => $ex->getMessage()
            ]);
            
            // Check if this is a browser request (not API)
            if ($this->isBrowserRequest($request)) {
                return $this->redirectToAccessDenied('Authentication');
            }
            
            $response = new Response();
            $errorBody = json_encode(['error' => gettext('No logged in user'), 'code' => 401]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        if (!$this->hasRole()) {
            $logger = LoggerUtils::getAppLogger();
            $logger->warning('User lacks required role', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'user' => $this->user->getUserName(),
                'required_role' => $this->noRoleMessage()
            ]);
            
            // Check if this is a browser request (not API)
            if ($this->isBrowserRequest($request)) {
                return $this->redirectToAccessDenied($this->getRoleName());
            }
            
            $response = new Response();
            $errorBody = json_encode(['error' => $this->noRoleMessage(), 'code' => 403]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    /**
     * Check if request is from a browser (expects HTML) vs API client (expects JSON)
     */
    protected function isBrowserRequest(Request $request): bool
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

    /**
     * Redirect to the access-denied page
     */
    protected function redirectToAccessDenied(string $role): ResponseInterface
    {
        $response = new Response();
        $redirectUrl = SystemURLs::getRootPath() . '/v2/access-denied?role=' . urlencode($role);
        return $response->withStatus(302)->withHeader('Location', $redirectUrl);
    }

    abstract protected function hasRole();

    abstract protected function noRoleMessage(): string;
    
    /**
     * Get the role name for redirect (without "User must be" prefix)
     */
    protected function getRoleName(): string
    {
        return 'Admin'; // Default, subclasses should override
    }
}
