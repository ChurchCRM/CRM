<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/map', function (RouteCollectorProxy $group): void {
    $group->get('/', 'getMapView');
    $group->get('', 'getMapView');
});

function getMapView(Request $request, Response $response, array $args): Response
{
    $params  = $request->getQueryParams();
    $groupId = isset($params['groupId']) ? (int) $params['groupId'] : null;

    // Colour palette indexed by classification ID (mod-wrapped for unknown IDs)
    $markerColors = [
        '#dc3545', // 0  unassigned — red
        '#0d6efd', // 1  blue
        '#198754', // 2  green
        '#fd7e14', // 3  orange
        '#6f42c1', // 4  purple
        '#0dcaf0', // 5  cyan
        '#ffc107', // 6  yellow
        '#d63384', // 7  pink
        '#20c997', // 8  teal
        '#6c757d', // 9  grey
    ];

    $classifications = Classification::getAll();
    $legendItems     = [
        ['id' => 0, 'label' => gettext('Unassigned'), 'color' => $markerColors[0]],
    ];
    foreach ($classifications as $cls) {
        $legendItems[] = [
            'id'    => $cls->getOptionId(),
            'label' => $cls->getOptionName(),
            'color' => $markerColors[$cls->getOptionId() % count($markerColors)],
        ];
    }

    $renderer = new PhpRenderer('templates/map/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Congregation Map'),
        'mapConfig'  => [
            'churchLat'    => (float) ChurchMetaData::getChurchLatitude(),
            'churchLng'    => (float) ChurchMetaData::getChurchLongitude(),
            'churchName'   => ChurchMetaData::getChurchName(),
            'hasLocation'  => ChurchMetaData::getChurchLatitude() !== '',
            'zoom'         => max(1, (int) SystemConfig::getValue('iMapZoom') ?: 10),
            'groupId'      => $groupId,
            'apiUrl'       => SystemURLs::getRootPath() . '/api/map/families',
            'legendItems'  => $legendItems,
            'markerColors' => $markerColors,
        ],
    ];

    return $renderer->render($response, 'map-view.php', $pageArgs);
}
