<?php
// Routes

$app->group('/database', function () use ($app) {
  $systemService = $app->SystemService;
  $app->post('/backup', function () use ($app, $systemService) {
    $input = getJSONFromApp($app);
    $backup = $systemService->getDatabaseBackup($input);
    echo json_encode($backup);
  });

  $app->post('/restore', function () use ($app, $systemService) {

    $request = $app->request();
    $body = $request->getBody();
    $restore = $systemService->restoreDatabaseFromBackup();
    echo json_encode($restore);
  });

  $app->get('/download/:filename', function ($filename) use ($app, $systemService) {

    $systemService->download($filename);
  });
});
