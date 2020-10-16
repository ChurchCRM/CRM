<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\dto\SystemURLs;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Service\DashboardService;

$app->group('', function () {
    $this->get('/dashboard', 'viewDashboard');
});


function viewDashboard(Request $request, Response $response, array $args)
{
    $renderer = new PhpRenderer('templates/root/');

//    $dashboardService = new DashboardService();




    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Welcome to').' '. ChurchMetaData::getChurchName()
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
