<?php

$app->group('/timerjobs', function () {
  $this->post('/run', function () {
      $this->SystemService->runTimerJobs();
    });
    
    $this->get('/run', function () {
      //$this->SystemService->runTimerJobs();
    });
});

?>