<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Family;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Person;
use Slim\Views\PhpRenderer;

$app->group('/register', function () {

    $enableSelfReg = SystemConfig::getBooleanValue('bEnableSelfRegistration');

    if ($enableSelfReg) {
        $this->get('/', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates/registration/');
            return $renderer->render($response, 'family-register.php', ['sRootPath' => SystemURLs::getRootPath()]);
        });

        $this->post('/', function ($request, $response, $args) {
            $renderer = new PhpRenderer('templates/registration/');

            $body = $request->getParsedBody();

            $family = [];
            $family["Name"] = $body['familyName'];
            $family["Address1"] = $body['familyAddress1'];
            $family["City"] = $body['familyCity'];
            $family["State"] = $body['familyState'];
            $family["Country"] = $body['familyCountry'];
            $family["Zip"] = $body['familyZip'];
            $family["HomePhone"] = $body['familyHomePhone'];

            $familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();

            $pageObjects = ['sRootPath' => SystemURLs::getRootPath(), 'family' => $family, 'familyCount' => $body['familyCount'], 'familyRoles' => $familyRoles];

            return $renderer->render($response, 'family-register-members.php', $pageObjects);
        });

    }
});
