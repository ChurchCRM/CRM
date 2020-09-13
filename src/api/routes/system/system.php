<?php

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\Service\TaskService;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\dto\LocaleInfo;

$app->group('/system', function () {
    $this->get('/notification', 'getUiNotificationAPI');
    $this->post('/background/csp-report', 'logCSPReportAPI');
    $this->get('/locale/{localeCode}', 'getLocaleInfo');
});

function logCSPReportAPI(Request $request, Response $response, array $args)
{
    $input = json_decode($request->getBody());
    $log = json_encode($input, JSON_PRETTY_PRINT);
    LoggerUtils::getCSPLogger()->info($log);
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

function getLocaleInfo(Request $request, Response $response, array $args)
{
    $localeInfo = new LocaleInfo($args['localeCode']);

    $data["name"] = $localeInfo->getName();
    $data["code"] = $localeInfo->getLocale();
    $data["countryFlagCode"] = strtolower($localeInfo->getCountryCode());
    $data["poPerComplete"] = "TBD";

    return $response->withJson($data);
}
