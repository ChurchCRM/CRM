<?php

use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Utils\LoggerUtils;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/system', function () {
    $this->get('/notification', 'getUiNotificationAPI');
    $this->post('/background/csp-report', 'logCSPReportAPI');
});

function logCSPReportAPI(Request $request, Response $response, array $args)
{
    $input = json_decode($request->getBody());
    $log = json_encode($input, JSON_PRETTY_PRINT);
    LoggerUtils::getCSPLogger()->debug($log);
}

function getUiNotificationAPI(Request $request, Response $response, array $args)
{
    if (NotificationService::isUpdateRequired())
    {
        NotificationService::updateNotifications();
    }
    $notifications = [];
    foreach (NotificationService::getNotifications() as $notification) {
        $uiNotification = new UiNotification($notification->title, "bell", $notification->link, "", "danger", "8000", "bottom", "left");
        array_push($notifications, $uiNotification);
    }

    $taskSrv = new TaskService();
    $notifications = array_merge($notifications, $taskSrv->getTaskNotifications());

    return $response->withJson(["notifications" => $notifications]);
}
