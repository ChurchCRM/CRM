<?php

use ChurchCRM\Service\TelemetryService;
use Slim\App;
use Slim\Routing\RoutingResults;

$_telemetryRoute = explode('?', $_SERVER['PHP_SELF'] ?? 'unknown', 2)[0];

// Templates have no PSR request in scope. The pattern, unlike the URL, carries no IDs or tokens.
$_telemetryApp = $GLOBALS['app'] ?? null;
if ($_telemetryApp instanceof App) {
    $_telemetryResolver = $_telemetryApp->getRouteResolver();
    $_telemetryMatch = $_telemetryResolver->computeRoutingResults(
        (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH),
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
    );
    if ($_telemetryMatch->getRouteStatus() === RoutingResults::FOUND) {
        $_telemetryRoute = $_telemetryApp->getBasePath()
            . $_telemetryResolver->resolveRoute($_telemetryMatch->getRouteIdentifier())->getPattern();
    }
}

TelemetryService::capturePageView($_telemetryRoute);
