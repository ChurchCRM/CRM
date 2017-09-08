<?php

// Routes

$app->group('/calendar', function () {
    $this->get('/events', function ($request, $response, $args) {
        $params = $request->getQueryParams();
        
        $events = ChurchCRM\EventQuery::create()->find();
          
        return $response->withJson($events->toArray());
    });
});
