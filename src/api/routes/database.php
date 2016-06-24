<?php
// Routes

$app->group('/database', function () {

  $this->post('/backup', function ($request, $response, $args) {
    $input = (object)$request->getParsedBody();
    $backup = $this->SystemService->getDatabaseBackup($input);
    echo json_encode($backup);
  });

  $this->post('/restore', function ($request, $response, $args) {
    $restore = $this->SystemService->restoreDatabaseFromBackup();
    echo json_encode($restore);
  });

  $this->get('/download/{filename}', function ($request, $response, $args) {
    $filename = $args['filename'];
    $this->SystemService->download($filename);
  });
});
