<?php

use ChurchCRM\dto\SystemURLs;

$app->get('/register/', function ($request, $response) {
    return $response->withStatus(302)->withHeader('Location', SystemURLs::getRootPath() . '/portal/register');
});
