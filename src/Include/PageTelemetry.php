<?php

use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Slim\Middleware\PageRouteMiddleware;

$_telemetryRoute = PageRouteMiddleware::getRoutePattern()
    ?? explode('?', $_SERVER['PHP_SELF'] ?? 'unknown', 2)[0];
TelemetryService::capturePageView($_telemetryRoute);
