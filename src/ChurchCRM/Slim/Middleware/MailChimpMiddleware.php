<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\MailChimpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MailChimpMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $mailchimpService = new MailChimpService();

        if (!$mailchimpService->isActive()) {
            return $response->withStatus(412)->withJson(['message' =>  gettext('Mailchimp is not active')]);
        }
        $request = $request->withAttribute('mailchimpService', $mailchimpService);

        return $next($request, $response);
    }
}
