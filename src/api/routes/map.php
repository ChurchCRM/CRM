<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/map', function (RouteCollectorProxy $group): void {
    $group->get('/families', 'getMapFamilies');
    $group->get('/families/', 'getMapFamilies');
});

/**
 * GET /api/map/families
 *
 * Returns a list of geocoded families (or group members) as map items.
 *
 * Query params:
 *   groupId (int, optional) — when > 0, returns persons from that group instead of all families
 *
 * Each item:
 *   id, type, name, salutation, address, latitude, longitude, classificationId, profileUrl
 */
function getMapFamilies(Request $request, Response $response, array $args): Response
{
    $params  = $request->getQueryParams();
    $groupId = isset($params['groupId']) ? (int) $params['groupId'] : null;

    $items = [];

    if ($groupId !== null && $groupId > 0) {
        // Return geocoded members of a specific group
        $persons = PersonQuery::create()
            ->usePerson2group2roleP2g2rQuery()
                ->filterByGroupId($groupId)
            ->endUse()
            ->find();

        foreach ($persons as $person) {
            $latLng = $person->getLatLng();
            if (empty($latLng['Latitude']) && empty($latLng['Longitude'])) {
                continue;
            }
            $items[] = [
                'id'               => $person->getId(),
                'type'             => 'person',
                'name'             => $person->getFullName(),
                'salutation'       => $person->getFullName(),
                'address'          => $person->getAddress(),
                'latitude'         => (float) $latLng['Latitude'],
                'longitude'        => (float) $latLng['Longitude'],
                'classificationId' => (int) $person->getClsId(),
                'profileUrl'       => SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $person->getId(),
            ];
        }
    } else {
        // Return all active families that have been geocoded
        $dirRoleHead = SystemConfig::getValue('sDirRoleHead');

        $families = FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByLatitude(0, Criteria::NOT_EQUAL)
            ->filterByLongitude(0, Criteria::NOT_EQUAL)
            ->usePersonQuery('per')
                ->filterByFmrId($dirRoleHead)
            ->endUse()
            ->find();

        foreach ($families as $family) {
            $headPeople       = $family->getHeadPeople();
            $classificationId = !empty($headPeople) ? (int) $headPeople[0]->GetClsId() : 0;

            $items[] = [
                'id'               => $family->getId(),
                'type'             => 'family',
                'name'             => $family->getName(),
                'salutation'       => $family->getSalutation(),
                'address'          => $family->getAddress(),
                'latitude'         => (float) $family->getLatitude(),
                'longitude'        => (float) $family->getLongitude(),
                'classificationId' => $classificationId,
                'profileUrl'       => SystemURLs::getRootPath() . '/v2/family/' . $family->getId(),
            ];
        }
    }

    return SlimUtils::renderJSON($response, $items);
}
