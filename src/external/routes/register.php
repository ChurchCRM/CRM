<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use Slim\Views\PhpRenderer;
use Slim\Routing\RouteCollectorProxy;
$app->group('/register', function (RouteCollectorProxy $group) {
    $enableSelfReg = SystemConfig::getBooleanValue('bEnableSelfRegistration');

    if ($enableSelfReg) {
        $group->get('/', function  (Request $request, Response $response, array $args) {
            $renderer = new PhpRenderer('templates/registration/');
            $familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();

            return $renderer->render($response, 'family-register.php', ['sRootPath' => SystemURLs::getRootPath(), 'familyRoles' => $familyRoles]);
        });
    }
});
