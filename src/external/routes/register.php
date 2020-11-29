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
            $familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();

            return $renderer->render($response, 'family-register.php', ['sRootPath' => SystemURLs::getRootPath(), 'familyRoles' => $familyRoles]);
        });
    }
});
