<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;

// Routes
$app->post('/issues', function ($request, $response, $args) use ($app) {
    $data = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $issueDescription =     
        "Collected Value Title |  Data \r\n" .
        "----------------------|----------------\r\n" .
        'Page Name |' . $data->pageName . "\r\n" .
        'Screen Size |' . $data->screenSize->height . 'x' . $data->screenSize->width . "\r\n" .
        'Window Size |' . $data->windowSize->height . 'x' . $data->windowSize->width . "\r\n" .
        'Page Size |' . $data->pageSize->height . 'x' . $data->pageSize->width . "\r\n" .
        'Platform Information | ' . php_uname($mode = 'a') . "\r\n" .
        'PHP Version | ' . phpversion() . "\r\n" .
        'SQL Version | ' . SystemService::getDBServerVersion() . "\r\n" .
        'ChurchCRM Version |' . $_SESSION['sSoftwareInstalledVersion'] . "\r\n" .
        'Reporting Browser |' . $_SERVER['HTTP_USER_AGENT'] . "\r\n" .
        'Prerequisite Status |' . SystemService::getPrerequisiteStatus() . "\r\n" ;

    return $response->withJson(["issueBody" => $issueDescription]);
});
