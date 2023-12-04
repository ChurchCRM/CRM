<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Slim\Request\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class MailChimpMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $mailchimpService = new MailChimpService();

        if (!$mailchimpService->isActive()) {
            $response = new Response();
            return SlimUtils::renderJSON($response, ['message' =>  gettext('Mailchimp is not active')], 412);
        }
        $request = $request->withAttribute('mailchimpService', $mailchimpService);

        return $handler->handle($request);
    }
}
