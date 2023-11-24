<?php

use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system', function (RouteCollectorProxy $group) {
    $group->get('/notification', 'getUiNotificationAPI');
    $group->post('/background/csp-report', 'logCSPReportAPI');
});

function logCSPReportAPI(Request $request, Response $response, array $args)
{
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $log = json_encode($input, JSON_PRETTY_PRINT);
    LoggerUtils::getCSPLogger()->debug($log);
}

function getUiNotificationAPI(Request $request, Response $response, array $args)
{
    if (NotificationService::isUpdateRequired()) {
        NotificationService::updateNotifications();
    }
    $notifications = [];
    foreach (NotificationService::getNotifications() as $notification) {
        $uiNotification = new UiNotification($notification->title, 'bell', $notification->link, '', 'danger', '8000', 'bottom', 'left');
        array_push($notifications, $uiNotification);
    }

    $taskSrv = new TaskService();
    $notifications = array_merge($notifications, $taskSrv->getTaskNotifications());
    return SlimUtils::renderJSON($response, ['notifications' => $notifications]);
}
