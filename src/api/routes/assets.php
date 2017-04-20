<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\AssetQuery;

$app->group('/assets', function () {
  $this->get('/', function ($request, $response, $args) {
      $assets = AssetQuery::create()->find();

       return $response->withJSON($assets->toJSON());
    });
});

?>
