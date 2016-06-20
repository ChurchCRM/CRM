<?php
// Routes


$app->group('/data/seed', function () {
  $this->get('/families/{count}', function ($request, $response, $args) {
    $query = $args['count'];
    $data = $this->DataSeedService->generateFamilies($query);
    return $response->write($data);
  });
  $this->post('/sundaySchoolClasses/', function ($request, $response, $args) {
    return $response->withJson($this->DataSeedService->generateSundaySchoolClasses());
  });
});
