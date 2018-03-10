<?php

use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/system', function () {
    $this->post('/csp-report', 'logCSPReportAPI');
    $this->get('/notification', 'getUiNotificationAPI');
});

function logCSPReportAPI(Request $request, Response $response, array $args)
{
    $input = json_decode($request->getBody());
    $log = json_encode($input, JSON_PRETTY_PRINT);
    LoggerUtils::getAppLogger()->warn($log);
}

function getUiNotificationAPI(Request $request, Response $response, array $args)
{
    $notifications = [];
    array_push($notifications, new \ChurchCRM\dto\Notification\UiNotification("my title", "info"));
    return $response->withJson($notifications);
}
