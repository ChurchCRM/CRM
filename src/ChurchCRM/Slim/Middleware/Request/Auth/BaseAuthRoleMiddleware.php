<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\AuthenticationManager;
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
            $response = new Response();
            $errorBody = json_encode(['error' => $this->noRoleMessage(), 'code' => 403]);
            $response->getBody()->write($errorBody);
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    abstract protected function hasRole();

    abstract protected function noRoleMessage();
}
