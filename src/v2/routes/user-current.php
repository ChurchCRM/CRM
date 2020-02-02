<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Authentication\AuthenticationProviders\LocalAuthentication;
use ChurchCRM\Utils\RedirectUtils;
use Slim\Views\PhpRenderer;

$app->group('/user/current', function () {
    $this->get('/enroll2fa', 'enroll2fa');
    $this->get('/changepassword', 'changepassword');
});



function enroll2fa(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/user/');
    $curUser = AuthenticationManager::GetCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser,
    ];

    if (LocalAuthentication::GetIsTwoFactorAuthSupported()) {
        return $renderer->render($response, 'manage-2fa.php', $pageArgs);
    }
    else {
        return $renderer->render($response, 'unsupported-2fa.php', $pageArgs); 
    }
}

function changepassword(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/user/');
    $curUser = AuthenticationManager::GetCurrentUser();
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'user' => $curUser,
    ];

    if (AuthenticationManager::GetAuthenticationProvider() instanceof LocalAuthentication) {
        // ChangePassword only works with LocalAuthentication
        return $renderer->render($response, 'changepassword.php', $pageArgs);
    }
    elseif (empty(AuthenticationManager::GetAuthenticationProvider()->GetPasswordChangeURL())) {
        // if the authentication provider includes a URL for self-service password change
        // then direct the user there
        // i.e. SSO will usually be a password change "portal," so we would redirect here.
        // but this will come later when we add more AuthenticationProviders
        RedirectUtils::AbsoluteRedirect(AuthenticationManager::GetAuthenticationProvider()->GetPasswordChangeURL());
    }
    else {
        // we're not using LocalAuth, and the AuthProvider does not specify a password change url
        // so tell the user we can't help them
        return $renderer->render($response, 'unsupported-changepassword.php', $pageArgs); 
    }
}
