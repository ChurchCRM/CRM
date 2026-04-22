<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\VersionUtils;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Limited access page for users with no admin permissions (GHSA-5w59-32c8-933v)
$app->get('/limited-access', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../templates/');
    $userName = '';
    $churchName = ChurchMetaData::getChurchName();
    $verifyUrl = '';

    // Try to get the user info from the active session
    try {
        if (AuthenticationManager::validateUserSessionIsActive(false)) {
            $user = AuthenticationManager::getCurrentUser();
            $person = $user->getPerson();
            $userName = $person ? ($person->getFirstName() . ' ' . $person->getLastName()) : $user->getUserName();

            // If user has a family, generate a verify link
            $familyId = $person ? $person->getFamId() : 0;
            if ($familyId > 0) {
                $token = new \ChurchCRM\model\ChurchCRM\Token();
                $token->build('verifyFamily', $familyId);
                $token->save();
                $verifyUrl = SystemURLs::getRootPath() . '/external/verify/' . $token->getToken();
            }
        }
    } catch (\Throwable $e) {
        // Session might be invalid — that's OK, just show the page without user info
    }

    return $renderer->render($response, 'limited-access.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'userName' => $userName,
        'churchName' => $churchName,
        'verifyUrl' => $verifyUrl,
    ]);
});

 $app->group('/system', function (RouteCollectorProxy $group): void {
    $renderer = new PhpRenderer(__DIR__ . '/../templates/');

    $group->get('/db-upgrade', function (Request $request, Response $response, array $args) use ($renderer): Response {
        // Check for auto-upgrade error stored in session by Bootstrapper
        $errorMessage = null;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!empty($_SESSION['dbUpgradeError'])) {
            $errorMessage = $_SESSION['dbUpgradeError'];
            unset($_SESSION['dbUpgradeError']);
        }

        $dbVersion = VersionUtils::getDBVersion();
        $softwareVersion = VersionUtils::getInstalledVersion();

        // Issue 3 fix: if versions match and no upgrade error, there is nothing to show here.
        // Redirect to root so a direct visit to this URL is not misleading.
        if (empty($errorMessage) && version_compare($softwareVersion, $dbVersion, '>=')) {
            return $response->withHeader('Location', SystemURLs::getRootPath() . '/')->withStatus(302);
        }

        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('Version Mismatch'),
            'dbVersion' => $dbVersion,
            'softwareVersion' => $softwareVersion,
            'errorMessage' => $errorMessage,
        ];
        return $renderer->render($response, 'system-db-update.php', $pageArgs);
    });
});
