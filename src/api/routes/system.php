<?php

use ChurchCRM\dto\SystemURLs;
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


$app->group('/system', function () {
  $this->post('/csp-report', function ($request, $response, $args) {
          $input = json_decode($request->getBody());
          $log  = json_encode($input, JSON_PRETTY_PRINT);
          $this->Logger->warn($log);
  });
});
