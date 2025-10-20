<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\MailChimpService;
use ChurchCRM\Slim\SlimUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class MailChimpMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
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
