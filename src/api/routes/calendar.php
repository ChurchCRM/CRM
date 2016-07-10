<?php
// Routes


$app->group('/calendar', function () {

  $this->get('/events', function ($request, $response, $args) {
    return $this->CalendarService->getEvents();
  });

});
