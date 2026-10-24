<?php

namespace ChurchCRM\Authentication;

use ChurchCRM\Authentication\Requests\LocalUsernamePasswordRequest;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

/**
 * The staff sign-in page, served at the `/admin` root (member sign-in design
 * §7): username and password, then the two-factor step. Members sign in at
 * `/portal/login`. `/session/begin` redirects here.
 */
final class StaffSignInPage
{
    public static function isRequestFor(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();
        $adminRoot = SystemURLs::getRootPath() . '/admin';

        return $path === $adminRoot || $path === $adminRoot . '/';
    }

    public static function handle(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // An explicit username may be passed in the query (the sign-in link in user emails).
        $rawUserName = $request->getQueryParams()['username'] ?? '';

        $pageArgs = [
            'sRootPath'            => SystemURLs::getRootPath(),
            'localAuthNextStepURL' => AuthenticationManager::getStaffSignInURL(),
            'forgotPasswordURL'    => AuthenticationManager::getForgotPasswordURL(),
            'prefilledUserName'    => InputUtils::sanitizeText(is_string($rawUserName) ? $rawUserName : ''),
        ];

        if ($request->getMethod() === 'POST') {
            // A malformed form post can omit either field, and the request is strictly typed.
            $loginRequestBody = $request->getParsedBody() ?? [];

            $userPassRequest = new LocalUsernamePasswordRequest(
                (string) ($loginRequestBody['User'] ?? ''),
                (string) ($loginRequestBody['Password'] ?? '')
            );
            // Redirects and exits on success and on the two-factor step.
            $authenticationResult = AuthenticationManager::authenticate($userPassRequest);
            $pageArgs['sErrorText'] = $authenticationResult->message;
        }

        $renderer = new PhpRenderer(SystemURLs::getDocumentRoot() . '/session/templates/');

        return $renderer->render($response, 'begin-session.php', $pageArgs);
    }
}
