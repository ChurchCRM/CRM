<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\BrowserRequestTrait;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FundraiserEnabledMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!SystemConfig::getBooleanValue('bEnabledFundraiser')) {
            LoggerUtils::getAppLogger()->info('Fundraiser access blocked: feature is disabled', [
                'path' => $request->getUri()->getPath(),
            ]);

            if ($this->isBrowserRequest($request)) {
                $response = new Response();
                return $response
                    ->withStatus(302)
                    ->withHeader('Location', SystemURLs::getRootPath() . '/');
            }

            $response = new Response();
            $body     = json_encode(['error' => gettext('Fundraiser is disabled'), 'code' => 403])
                ?: '{"error":"Fundraiser is disabled","code":403}';
            $response->getBody()->write($body);
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
