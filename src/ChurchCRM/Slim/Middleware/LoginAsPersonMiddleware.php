<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\LoginAsPerson;
use ChurchCRM\Portal\PortalExtension;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\RedirectUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Every write request during Login as Person (member sign-in design §6.5): refused
 * while `bLoginAsPersonCanWrite` is off, and recorded in `login_as_person_action_lpa`
 * either way. Stop itself is neither.
 *
 * Added right after AuthMiddleware in every Slim entry point that authenticates
 * (MvcAppFactory, api/index.php, plugins/index.php). Legacy pages are staff pages,
 * which `AuthenticationManager::ensureAuthentication()` refuses during Login as Person.
 */
final class LoginAsPersonMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        if (!LoginAsPerson::isWriteMethod($method) || LoginAsPerson::isStopPath($path) || $request->getHeaderLine('x-api-key') !== '') {
            return $handler->handle($request);
        }
        $historyId = LoginAsPerson::historyId();
        if ($historyId === null) {
            return $handler->handle($request);
        }

        if (!LoginAsPerson::allowsChanges()) {
            LoginAsPerson::recordAction($historyId, $method, $path, 403);

            return $this->refuse($request);
        }

        // A handler that ends with RedirectUtils::redirect() exits before it returns.
        $recorded = false;
        register_shutdown_function(static function () use (&$recorded, $historyId, $method, $path): void {
            if (!$recorded) {
                $status = http_response_code();
                LoginAsPerson::recordAction($historyId, $method, $path, is_int($status) ? $status : null);
            }
        });

        $response = $handler->handle($request);
        $recorded = true;
        LoginAsPerson::recordAction($historyId, $method, $path, $response->getStatusCode());

        return $response;
    }

    private function refuse(ServerRequestInterface $request): ResponseInterface
    {
        $message = gettext('Changes are turned off during Login as Person.');
        if (!$this->isBrowserRequest($request)) {
            return SlimUtils::renderErrorJSON(new Response(), $message, [], 403);
        }

        PortalExtension::addFlash('warning', $message);
        $back = RedirectUtils::stripAndValidatePath((string) parse_url($request->getHeaderLine('Referer'), PHP_URL_PATH));

        return (new Response())
            ->withStatus(303)
            ->withHeader('Location', SystemURLs::getRootPath() . (preg_match('#^/portal(/|$)#', $back) === 1 ? $back : '/portal/'));
    }
}
