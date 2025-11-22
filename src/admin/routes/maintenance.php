<?php

use ChurchCRM\dto\SystemURLs;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/maintenance', function (RouteCollectorProxy $group): void {
    
    $group->get('', function (Request $request, Response $response): Response {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        
        $pageArgs = [
            'sRootPath' => SystemURLs::getRootPath(),
            'sPageTitle' => gettext('System Maintenance'),
        ];
        
        return $renderer->render($response, 'maintenance.php', $pageArgs);
    });

});
