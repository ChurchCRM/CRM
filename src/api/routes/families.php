<?php
// Routes


$app->group('/families', function () use ($app) {
  $app->get('/search/:query', function ($query) use ($app) {

    echo $app->FamilyService->getFamiliesJSON($app->FamilyService->search($query));
  });
  $app->get('/lastedited', function ($query) use ($app) {

    $app->FamilyService->lastEdited();
  });
  $app->get('/byCheckNumber/:tScanString', function ($tScanString) use ($app) {

    echo $app->FinancialService->getMemberByScanString($tScanString);
  });
  $app->get('/byEnvelopeNumber/:tEnvelopeNumber', function ($tEnvelopeNumber) use ($app) {

    echo $app->FamilyService->getFamilyStringByEnvelope($tEnvelopeNumber);
  });
});
