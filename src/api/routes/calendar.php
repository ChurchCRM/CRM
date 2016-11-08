<?php
// Routes


$app->group('/calendar', function () {

  $this->get('/events', function ($request, $response, $args) {
    $params = $request->getQueryParams();
    return $response->withJson($this->CalendarService->getEvents($params['start'], $params['end']));
  });

});
