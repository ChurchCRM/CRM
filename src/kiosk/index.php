<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\KioskDevice;
use ChurchCRM\model\ChurchCRM\KioskDeviceQuery;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\CorsMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\VersionMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Get base path by combining $sRootPath from Config.php with /kiosk endpoint
$basePath = SlimUtils::getBasePath('/kiosk');

$container = new ContainerBuilder();

// Determine if this is an admin route or a device route
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isAdminRoute = str_contains($requestUri, '/kiosk/admin') || str_contains($requestUri, '/kiosk/api');

// For device routes, handle kiosk device initialization
$Kiosk = null;
if (!$isAdminRoute) {
    if (isset($_COOKIE['kioskCookie'])) {
        $g = hash('sha256', $_COOKIE['kioskCookie']);
        $Kiosk = KioskDeviceQuery::create()
              ->findOneByGUIDHash($g);

        if ($Kiosk === null) {
            // Kiosk was deleted - create a new one to allow re-registration
            // Keep the same cookie so the device can re-register seamlessly
            $Kiosk = new KioskDevice();
            $Kiosk->setGUIDHash($g);
            $Kiosk->setAccepted(false);
            $Kiosk->save();
        }
    } else {
        // No cookie - create a new kiosk registration
        // Always allow device registration (admin must still approve)
        $guid = uniqid();
        setcookie('kioskCookie', $guid, ['expires' => 2_147_483_647]);
        $Kiosk = new KioskDevice();
        $Kiosk->setGUIDHash(hash('sha256', $guid));
        $Kiosk->setAccepted(false);
        $Kiosk->save();
    }

    // Store kiosk in container for device routes
    if ($Kiosk !== null) {
        $container->set('kiosk', $Kiosk);
    }
}

// Compile container and create app
$container->compile();
AppFactory::setContainer($container);
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

// Device routes (no auth middleware - uses kiosk cookie)
require __DIR__ . '/routes/device.php';

// Admin routes (requires admin auth)
require __DIR__ . '/routes/admin.php';
require __DIR__ . '/routes/api/kiosks.php';

// Run app
$app->run();
