<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->group('/register', function (RouteCollectorProxy $group): void {
    $enableSelfReg = SystemConfig::getBooleanValue('bEnableSelfRegistration');

    if ($enableSelfReg) {
        $group->get('/', function (Request $request, Response $response, array $args): Response {
            $renderer = new PhpRenderer('templates/registration/');
            $familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();

            return $renderer->render($response, 'family-register.php', ['sRootPath' => SystemURLs::getRootPath(), 'familyRoles' => $familyRoles]);
        });
    }
});
