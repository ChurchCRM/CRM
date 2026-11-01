<?php

namespace ChurchCRM\Slim\Middleware\Request\Auth;

use ChurchCRM\Authentication\StaffSignInPage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminRoleAuthMiddleware extends BaseAuthRoleMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // The /admin root alone is open to everyone: it is the staff sign-in page,
        // and it sends staff who are not administrators to their own dashboard
        // (member sign-in design §7). Every other /admin page stays admin-only.
        if (StaffSignInPage::isRequestFor($request)) {
            return $handler->handle($request);
        }

        return parent::process($request, $handler);
    }

    protected function hasRole(): bool
    {
        return $this->user->isAdmin();
    }

    protected function noRoleMessage(): string
    {
        return gettext('User must be an Admin');
    }

    protected function getRoleName(): string
    {
        return 'Admin';
    }
}
