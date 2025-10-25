<?php

use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system', function (RouteCollectorProxy $group): void {
    $group->get('/notification', 'getUiNotificationAPI');
    $group->post('/background/csp-report', 'logCSPReportAPI');
});

function logCSPReportAPI(Request $request, Response $response, array $args): Response
{
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $log = json_encode($input, JSON_PRETTY_PRINT);
    LoggerUtils::getCSPLogger()->debug($log);

    return SlimUtils::renderSuccessJSON($response);
}

function getUiNotificationAPI(Request $request, Response $response, array $args): Response
{
    if (NotificationService::isUpdateRequired()) {
        NotificationService::updateNotifications();
    }
    $notifications = [];
    foreach (NotificationService::getNotifications() as $notification) {
        $link = $notification->link ?? '';
        $message = $notification->message ?? '';
        $title = $notification->title ?? '';
        $icon = $notification->icon ?? 'info-circle';
        $type = $notification->type ?? 'info';
        $timeout = $notification->timeout ?? 4000;
        $placement = $notification->placement ?? 'bottom';
        $align = $notification->align ?? 'right';
        $uiNotification = new UiNotification($title, $icon, $link, $message, $type, $timeout, $placement, $align);
        $notifications[] = $uiNotification;
    }

    $taskSrv = new TaskService();
    $notifications = array_merge($notifications, $taskSrv->getTaskNotifications());
    return SlimUtils::renderJSON($response, ['notifications' => $notifications]);
}
