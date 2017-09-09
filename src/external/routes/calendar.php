<?php

// Routes

$app->group('/calendar', function () {
    $this->get('/events', function ($request, $response, $args) {
        if (!ChurchCRM\dto\SystemConfig::getBooleanValue("bEnableExternalCalendarAPI"))
        {
          throw new \Exception(gettext("External Calendar API is disabled")  , 400);
        }
        $events = ChurchCRM\EventQuery::create()->find();
        return $response->withJson($events->toArray());
    });
});
