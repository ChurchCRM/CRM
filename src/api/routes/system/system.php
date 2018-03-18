<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Service\TaskService;

$app->group('/system', function () {
    $this->get('/notification', 'getUiNotificationAPI');
});

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
