<?php

use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Portal\Auth\PortalSessionMiddleware;
use ChurchCRM\Portal\PortalApiMiddleware;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use Slim\Routing\RouteCollectorProxy;

/*
 * `/api/portal/plugins/{id}/…` — the Member Portal API of active plugins
 * (member sign-in design §8.5), behind the same gate as the rest of
 * `/api/portal`: a portal session, no API keys, CSRF on every write. Each
 * plugin route names its capability with RequirePortalCapability.
 */
$app->group('/portal', function (RouteCollectorProxy $group): void {
    PluginManager::registerPortalRoutes($group, PluginManager::PORTAL_API);
})->add(new CSRFMiddleware())->add(new PortalApiMiddleware())->add(new PortalSessionMiddleware());
