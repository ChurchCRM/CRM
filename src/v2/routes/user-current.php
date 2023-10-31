<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Authentication\Exceptions\PasswordChangeException;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;

$app->group('/user/current', function () use ($app) {
    $app->get('/enroll2fa', 'enroll2fa');
    $app->get('/changepassword', 'changepassword');
    $app->post('/changepassword', 'changepassword');
});



function enroll2fa(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/user/');
    $curUser = AuthenticationManager::getCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser,
    ];

    if (LocalAuthentication::getIsTwoFactorAuthSupported()) {
        return $renderer->render($response, 'manage-2fa.php', $pageArgs);
    } else {
        return $renderer->render($response, 'unsupported-2fa.php', $pageArgs);
    }
}

function changepassword(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/');
    $authenticationProvider = AuthenticationManager::getAuthenticationProvider();
    $curUser = AuthenticationManager::getCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser,
    ];

    if ($authenticationProvider instanceof LocalAuthentication) {
        // ChangePassword only works with LocalAuthentication

        if ($request->getMethod() == "POST") {
            $loginRequestBody = (object)$request->getParsedBody();
            try {
                $curUser->userChangePassword($loginRequestBody->OldPassword, $loginRequestBody->NewPassword1);
                return $renderer->render($response, "common/success-changepassword.php", $pageArgs);
            } catch (PasswordChangeException $pwChangeExc) {
                $pageArgs['s'.$pwChangeExc->AffectedPassword.'PasswordError'] =  $pwChangeExc->getMessage();
            }
        }

        return $renderer->render($response, 'user/changepassword.php', $pageArgs);
    } elseif (empty($authenticationProvider->getPasswordChangeURL())) {
        // if the authentication provider includes a URL for self-service password change
        // then direct the user there
        // i.e. SSO will usually be a password change "portal," so we would redirect here.
        // but this will come later when we add more AuthenticationProviders
        RedirectUtils::absoluteRedirect($authenticationProvider->getPasswordChangeURL());
    } else {
        // we're not using LocalAuth, and the AuthProvider does not specify a password change url
        // so tell the user we can't help them
        return $renderer->render($response, 'common/unsupported-changepassword.php', $pageArgs);
    }
}
