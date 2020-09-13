<?php

use ChurchCRM\dto\SystemURLs;
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

    $poLocalesFile = file_get_contents(SystemURLs::getDocumentRoot() . "/locale/poeditor.json");
    $poLocales = json_decode($poLocalesFile, true);
    $rawPOData = $poLocales["result"]["languages"];
    foreach ($rawPOData as $poLocale) {
        if ($localeInfo->getPoLocaleId() == $poLocale["code"]) {
            $data["poPerComplete"] = $poLocale["percentage"];
            $data["poLastUpdated"] = $poLocale["updated"];
            break;
        }
    }



    return $response->withJson($data);
}
