<?php
// Routes


$app->group('/issues', function () use ($app) {
  $systemService = $app->SystemService;
  $app->post('/', function () use ($app, $systemService) {
    $request = $app->request();
    $body = $request->getBody();
    $input = json_decode($body);
    $app->SystemService->reportIssue($input);
  });

});
