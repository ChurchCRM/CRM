<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Slim\Middleware\Api\FamilyMiddleware;
use ChurchCRM\Utils\GeoUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/map', function (RouteCollectorProxy $group): void {
    $group->get('/families', 'getMapFamilies');
    $group->get('/families/', 'getMapFamilies');
    $group->get('/neighbors/{familyId:[0-9]+}', 'getMapNeighbors')->add(FamilyMiddleware::class);
    $group->get('/neighbors/{familyId:[0-9]+}/', 'getMapNeighbors')->add(FamilyMiddleware::class);
});

/**
 * @OA\Get(
 *     path="/map/families",
 *     summary="Get geocoded map items — families, group members, or cart persons",
 *     tags={"Map"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA\Parameter(
 *         name="groupId",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="integer"),
 *         description="When omitted or null: all active geocoded families. When 0: persons in the session cart. When > 0: members of that group."
 *     ),
 *     @OA\Response(response=200, description="Array of map items",
 *         @OA\JsonContent(type="array", @OA\Items(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="type", type="string", enum={"family","person"}),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="salutation", type="string"),
 *             @OA\Property(property="address", type="string"),
 *             @OA\Property(property="latitude", type="number", format="float"),
 *             @OA\Property(property="longitude", type="number", format="float"),
 *             @OA\Property(property="classificationId", type="integer"),
 *             @OA\Property(property="profileUrl", type="string")
 *         ))
 *     )
 * )
 */
function getMapFamilies(Request $request, Response $response, array $args): Response
{
    $params  = $request->getQueryParams();
    $groupId = isset($params['groupId']) ? (int) $params['groupId'] : null;

    $items = [];

    if ($groupId !== null && $groupId === 0) {
        // Cart view — return persons currently in the people cart (session)
        $cartIds = $_SESSION['aPeopleCart'] ?? [];
        if (!empty($cartIds)) {
            $persons = PersonQuery::create()
                ->filterById($cartIds)
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
        }
    } elseif ($groupId !== null && $groupId > 0) {
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
        $families = FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByLatitude(0, Criteria::NOT_EQUAL)
            ->filterByLongitude(0, Criteria::NOT_EQUAL)
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

/**
 * @OA
t+ *     path="/map/neighbors",
 *     summary="Get nearest neighbor families for a given familyId",
 *     tags={"Map"},
 *     security={{"ApiKeyAuth":{}}},
 *     @OA
 *     Parameter(name="familyId", in="query", required=true, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="maxNeighbors", in="query", required=false, @OA\Schema(type="integer")),
 *     @OA\Parameter(name="maxDistance", in="query", required=false, @OA\Schema(type="number", format="float")),
 *     @OA\Response(response=200, description="Array of neighbor families")
 */
function getMapNeighbors(Request $request, Response $response, array $args): Response
{
    $params       = $request->getQueryParams();
    $maxNeighbors = isset($params['maxNeighbors']) ? (int)$params['maxNeighbors'] : 15;
    $maxDistance  = isset($params['maxDistance']) ? (float)$params['maxDistance'] : 10.0;

    /** @var \ChurchCRM\model\ChurchCRM\Family $selectedFamily */
    $selectedFamily = $request->getAttribute('family');
    if (empty($selectedFamily)) {
        return SlimUtils::renderJSON($response->withStatus(400), ["error" => "family not provided"]);
    }

    $familyId = (int)$selectedFamily->getId();
    $selLat = (float)$selectedFamily->getLatitude();
    $selLng = (float)$selectedFamily->getLongitude();
    if ($selLat === 0.0 && $selLng === 0.0) {
        return SlimUtils::renderJSON($response->withStatus(400), ["error" => "selected family has no coordinates"]);
    }

    $families = FamilyQuery::create()
        ->filterByDateDeactivated(null)
        ->filterByLatitude(0, Criteria::NOT_EQUAL)
        ->filterByLongitude(0, Criteria::NOT_EQUAL)
        ->find();

    $items = [];

    foreach ($families as $family) {
        $fid = $family->getId();
        if ($fid === $familyId) {
            continue;
        }

        $lat = (float)$family->getLatitude();
        $lng = (float)$family->getLongitude();
        // compute numeric distance (in configured units)
        try {
            $a = new \Location\Coordinate($selLat, $selLng);
            $b = new \Location\Coordinate($lat, $lng);
            $vincenty = new \Location\Distance\Vincenty();
            $meters = $vincenty->getDistance($a, $b);
            $km = $meters / 1000.0;
            $distance = (strtoupper(SystemConfig::getValue('sDistanceUnit')) === 'MILES') ? $km * 0.6213712 : $km;
        } catch (\Throwable $e) {
            // fallback to GeoUtils formatted distance and try to cast
            $distanceText = GeoUtils::latLonDistance($selLat, $selLng, $lat, $lng);
            $distance = (float)str_replace(',', '', $distanceText);
        }

        // filter by maxDistance
        if ($distance > $maxDistance) {
            continue;
        }

        $bearing = GeoUtils::latLonBearing($selLat, $selLng, $lat, $lng);

        // gather people in family
        $persons = [];
        $people = PersonQuery::create()->filterByFamId($fid)->find();
        foreach ($people as $p) {
            $persons[] = [
                'id' => $p->getId(),
                'firstName' => $p->getFirstName(),
                'lastName' => $p->getLastName(),
                'classificationId' => (int)$p->getClsId(),
            ];
        }

        $items[] = [
            'id' => $fid,
            'type' => 'family',
            'name' => $family->getName(),
            'address' => $family->getAddress(),
            'latitude' => $lat,
            'longitude' => $lng,
            'distance' => $distance,
            'distanceText' => GeoUtils::latLonDistance($selLat, $selLng, $lat, $lng),
            'bearing' => $bearing,
            'persons' => $persons,
            'profileUrl' => SystemURLs::getRootPath() . '/v2/family/' . $fid,
        ];
    }

    // sort by numeric distance
    usort($items, function ($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });

    // limit to maxNeighbors
    $items = array_slice($items, 0, max(0, $maxNeighbors));

    return SlimUtils::renderJSON($response, $items);
}
