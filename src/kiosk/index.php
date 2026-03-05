<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\KioskDevice;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;


// Get base path by combining $sRootPath from Config.php with /kiosk endpoint
$basePath = SlimUtils::getBasePath('/kiosk');

// Determine if this is an admin route or a device route
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminRoute = str_contains($requestUri, '/kiosk/admin') || str_contains($requestUri, '/kiosk/api');

// For device routes, handle kiosk device initialization
if (!$isAdminRoute) {
    $windowOpen = new \DateTimeImmutable(SystemConfig::getValue('sKioskVisibilityTimestamp')) > new \DateTimeImmutable();

    if (isset($_COOKIE['kioskCookie'])) {
        $g = hash('sha256', $_COOKIE['kioskCookie']);
        $Kiosk = KioskDeviceQuery::create()
              ->findOneByGUIDHash($g);

        if ($Kiosk === null) {
            // Kiosk was deleted - only allow re-registration if window is open
            if ($windowOpen) {
                $Kiosk = new KioskDevice();
                $Kiosk->setGUIDHash($g);
                $Kiosk->setAccepted(false);
                $Kiosk->save();
            } else {
                // Window closed - clear cookie and show registration disabled page
                setcookie('kioskCookie', '', ['expires' => time() - 3600]);
                http_response_code(401);
                $sRootPath = SystemURLs::getRootPath();
                require __DIR__ . '/templates/registration-closed.php';
                exit;
            }
        }
    } elseif ($windowOpen) {
        // No cookie and registration window is open - create new kiosk
        $guid = uniqid();
        setcookie('kioskCookie', $guid, ['expires' => 2_147_483_647]);
        // Populate $_COOKIE for current request since setcookie() doesn't
        $_COOKIE['kioskCookie'] = $guid;
        $Kiosk = new KioskDevice();
        $Kiosk->setGUIDHash(hash('sha256', $guid));
        $Kiosk->setAccepted(false);
        $Kiosk->save();
    } else {
        // No cookie and registration window is closed - show registration disabled page
        http_response_code(401);
        $sRootPath = SystemURLs::getRootPath();
        require __DIR__ . '/templates/registration-closed.php';
        exit;
    }
}

// Helper function to retrieve kiosk device from cookie (instead of container)
// Captures the already-initialized $Kiosk to handle newly created devices in the current request
$getKioskFromCookie = function () use ($Kiosk): ?KioskDevice {
    return $Kiosk ?? null;
};

// Create app (no container needed - Slim 4 works fine without one)
$app = AppFactory::create();
$app->setBasePath($basePath);

// Add Slim error middleware for proper error handling and logging
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
SlimUtils::setupErrorLogger($errorMiddleware);

// Custom error handler
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $logger = LoggerUtils::getAppLogger();

    if ($exception instanceof HttpNotFoundException) {
        $logger->info('Kiosk 404 redirect', ['path' => $request->getUri()->getPath()]);
        $response = $app->getResponseFactory()->createResponse(302);

        return $response->withHeader('Location', SystemURLs::getRootPath() . '/kiosk/admin');
    }

    // Log full error details server-side for debugging
    $logger->error('Kiosk error', [
        'exception' => $exception::class,
        'message'   => $exception->getMessage(),
        'file'      => $exception->getFile(),
        'line'      => $exception->getLine(),
    ]);

    // Return generic message to client
    $response = $app->getResponseFactory()->createResponse(500);

    return SlimUtils::renderJSON($response, [
        'success' => false,
        'error'   => gettext('An unexpected error occurred. Please contact your administrator.'),
    ]);
});

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new CorsMiddleware());
$app->add(VersionMiddleware::class);

// Initialize plugin system so Notification::send() can access Vonage SMS and OpenLP plugins
PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');

// Device routes (no auth middleware - uses kiosk cookie)
// Pass helper function to device routes
$deviceGetKiosk = $getKioskFromCookie;
require __DIR__ . '/routes/device.php';

// Admin routes (requires admin auth)
require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/api/kiosks.php';

// Run app
$app->run();
