<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MailChimpMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $mailchimpService = new MailChimpService();

        if (!$mailchimpService->isActive()) {
            return SlimUtils::renderJSON($response, ['message' =>  gettext('Mailchimp is not active')], 412);
        }
        $request = $request->withAttribute('mailchimpService', $mailchimpService);

        return $next($request, $response);
    }
}
