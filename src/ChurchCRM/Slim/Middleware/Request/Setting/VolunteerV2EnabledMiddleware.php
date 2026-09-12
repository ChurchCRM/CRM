<?php

namespace ChurchCRM\Slim\Middleware\Request\Setting;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Slim\Middleware\BrowserRequestTrait;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Blocks every V2 Volunteer Management surface unless the rollout state
 * (SystemConfig `sVolunteerVersion`, see #9704) is 'v2' or 'both'.
 *
 * This is the server-side half of the rollout: hiding the menu entry is not a
 * gate. It guards both the /volunteer MVC module and the /api/volunteer API
 * group, so an administrator cannot reach a disabled experience by typing the
 * URL, and neither can an API client.
 *
 * Deliberately NOT a subclass of BaseAuthSettingMiddleware: that base reads the
 * setting with getBooleanValue() (the rollout state is a three-value choice,
 * not a boolean) and answers with an empty body carrying the reason in the HTTP
 * reason phrase, which no API client of ours parses.
 *
 * Response shape follows FundraiserEnabledMiddleware, the closest precedent for
 * "this feature is switched off": a 302 to the root path for browser requests
 * and a 403 JSON error for API clients. It deliberately does NOT redirect to
 * /v2/access-denied — that page tells the visitor they are missing a role,
 * which would misinform an administrator who has every role and has simply not
 * enabled the module yet.
 */
class VolunteerV2EnabledMiddleware implements MiddlewareInterface
{
    use BrowserRequestTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!User::isVolunteerV2Enabled()) {
            LoggerUtils::getAppLogger()->info('Volunteer V2 access blocked: rollout state does not include V2', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'sVolunteerVersion' => User::getVolunteerVersion(),
            ]);

            if ($this->isBrowserRequest($request)) {
                return (new Response())
                    ->withStatus(302)
                    ->withHeader('Location', SystemURLs::getRootPath() . '/');
            }

            // Wording note: SlimUtils::renderErrorJSON() replaces any message
            // matching its credential-redaction regex with a generic one, so
            // this message must avoid the words "user", "token" and "host".
            return SlimUtils::renderErrorJSON(
                new Response(),
                gettext('Volunteer Management V2 is not enabled'),
                [],
                403,
                null,
                $request
            );
        }

        return $handler->handle($request);
    }
}
