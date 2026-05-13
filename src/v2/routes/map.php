<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\Classification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\view\PageHeader;
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

    // Build legend — role-based when a group is specified, classification-based otherwise
    $legendType  = 'classifications';
    $groupName   = null;
    $legendTitle = gettext('Legend');

    if ($groupId !== null && $groupId > 0) {
        $group = GroupQuery::create()->findPk($groupId);
        if ($group !== null) {
            $groupName   = $group->getName();
            $legendType  = 'roles';
            $legendTitle = gettext('Member Roles');
            $legendItems = [];
            $i = 0;
            foreach (ListOptionQuery::create()->filterById($group->getRoleListId())->orderByOptionSequence()->find() as $role) {
                $legendItems[] = [
                    'id'    => (int) $role->getOptionId(),
                    'label' => $role->getOptionName(),
                    'color' => $markerColors[$i % count($markerColors)],
                ];
                $i++;
            }
        }
    }

    if ($legendType === 'classifications') {
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
    }

    $renderer = new PhpRenderer('templates/map/');

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => $groupName !== null ? gettext('Group Map') : gettext('Congregation Map'),
        'sPageSubtitle'      => $groupName !== null
            ? sprintf(gettext('Showing members of: %s'), $groupName)
            : gettext('View all families on an interactive map'),
        'aBreadcrumbs'       => $groupName !== null
            ? PageHeader::breadcrumbs([
                [gettext('Groups'), '/groups/dashboard'],
                [gettext('Map')],
            ])
            : PageHeader::breadcrumbs([
                [gettext('People'), '/people/dashboard'],
                [gettext('Map')],
            ]),
        'sSettingsCollapseId' => 'mapAdminSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Family Geographic'), 'url' => '/GeoPage.php', 'icon' => 'fa-globe', 'adminOnly' => false],
            ['label' => gettext('Map Settings'), 'collapse' => '#mapAdminSettings', 'icon' => 'fa-sliders', 'adminOnly' => true],
        ]),
        'mapConfig'        => [
            'churchLat'    => ChurchMetaData::getChurchLatitude(),
            'churchLng'    => ChurchMetaData::getChurchLongitude(),
            'churchName'   => ChurchMetaData::getChurchName(),
            'hasLocation'  => ChurchMetaData::hasChurchLocation(),
            'zoom'         => max(1, SystemConfig::getIntValue('iMapZoom') ?: 10),
            'groupId'      => $groupId,
            'groupName'    => $groupName,
            'legendType'   => $legendType,
            'legendTitle'  => $legendTitle,
            'apiUrl'       => SystemURLs::getRootPath() . '/api/map/families',
            'legendItems'  => $legendItems,
            'markerColors' => $markerColors,
        ],
    ];

    return $renderer->render($response, 'map-view.php', $pageArgs);
}
