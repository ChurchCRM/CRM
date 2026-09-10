<?php

use ChurchCRM\Plugins\MigrationExample\Model\EntryQuery;

$app->get('/migration-example/entries', static function ($request, $response) {
    $response->getBody()->write((string) EntryQuery::create()->count());
    return $response;
});
