<?php

use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Utils\GeoUtils;

$app->group('/family/{familyId:[0-9]+}', function () {
    $this->get('', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        return $response->withJSON($family->toJSON());
    });

    $this->get('/geolocation', function ($request, $response, $args) {
        $family = $request->getAttribute("family");
        $familyAddress = $family->getAddress();
        $familyLatLong = GeoUtils::getLatLong($familyAddress);
        $familyDrivingInfo = GeoUtils::DrivingDistanceMatrix($familyAddress, ChurchMetaData::getChurchAddress());
        $geoLocationInfo = array_merge($familyDrivingInfo, $familyLatLong);

        return $response->withJson($geoLocationInfo);

    });

})->add(new FamilyAPIMiddleware());

