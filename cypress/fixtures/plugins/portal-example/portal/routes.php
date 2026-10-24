<?php

/*
 * Portal routes of the Portal Example fixture plugin. ChurchCRM includes this
 * file twice: with $portalArea === 'pages' under /portal/plugins/portal-example
 * and with $portalArea === 'api' under /api/portal/plugins/portal-example. Both
 * sit behind the portal session. $routes (PortalPluginRoutes) takes each route's
 * capability as its third argument.
 */

use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\Plugins\PortalExample\PortalExamplePlugin;
use ChurchCRM\Portal\Auth\PortalContext;
use ChurchCRM\Portal\Permissions\PortalAuthorizationService;
use ChurchCRM\Portal\Permissions\RequirePortalCapability;
use ChurchCRM\Portal\PortalTwig;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$myGroupIds = static fn (): array => (new PortalAuthorizationService())
    ->scopeIdsForPerson((int) PortalContext::principal()?->personId, PortalExamplePlugin::ROSTER);

if ($portalArea === 'pages') {
    $routes->get('/rosters', function (Request $request, Response $response) use ($myGroupIds): Response {
        return PortalTwig::renderPluginPage($response, 'portal-example', 'rosters.html.twig', [
            'pageTitle' => 'Rosters',
            'groups' => GroupQuery::create()->filterById($myGroupIds())->orderByName()->find(),
        ], 'portal-example-rosters');
    }, PortalExamplePlugin::ROSTER);

    $routes->get('/rosters/{groupId:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupId = (int) $args['groupId'];
        $names = [];
        foreach (Person2group2roleP2g2rQuery::create()->filterByGroupId($groupId)->find() as $membership) {
            $names[] = $membership->getPerson()?->getFullName();
        }

        return PortalTwig::renderPluginPage($response, 'portal-example', 'roster.html.twig', [
            'pageTitle' => (string) GroupQuery::create()->findPk($groupId)?->getName(),
            'names' => $names,
        ], 'portal-example-rosters');
    }, RequirePortalCapability::onRouteArgument(PortalExamplePlugin::ROSTER, 'groupId'));

    $routes->get('/notices', function (Request $request, Response $response): Response {
        return PortalTwig::renderPluginPage($response, 'portal-example', 'notices.html.twig', [
            'pageTitle' => 'Notice board',
        ], 'portal-example-notices');
    }, PortalExamplePlugin::NOTICES);

    $routes->get('/undeclared', function (Request $request, Response $response): Response {
        return $response;
    }, 'plugin.portal-example.undeclared');
}

if ($portalArea === 'api') {
    $routes->get('/groups', function (Request $request, Response $response) use ($myGroupIds): Response {
        return SlimUtils::renderJSON($response, ['groupIds' => $myGroupIds()]);
    }, PortalExamplePlugin::ROSTER);

    $routes->get('/notices', function (Request $request, Response $response): Response {
        return SlimUtils::renderJSON($response, ['notices' => []]);
    }, PortalExamplePlugin::NOTICES);
}
