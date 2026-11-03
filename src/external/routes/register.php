<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$app->get('/register/', function ($request, $response) {
    if (!SystemConfig::getBooleanValue('bEnableSelfRegistration')) {
        return $response->withStatus(404);
    }

    return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/portal/register');
});
