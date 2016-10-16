<?php
// Routes

$app->group('/systemupgrade', function () {

  $this->get('/downloadlatestrelease', function () {
    $upgradeFile = $this->SystemService->downloadLatestRelease();
    echo json_encode($upgradeFile);
  });

  $this->post('/doupgrade', function ($request, $response, $args) {
    $input = (object)$request->getParsedBody();
    $upgradeResult = $this->SystemService->doUpgrade($input->fullPath,$input->sha1);
    echo json_encode($upgradeResult);
  });
});
