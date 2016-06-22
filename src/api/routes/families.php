<?php
// Routes


$app->group('/families', function () {

  $this->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    echo $this->FamilyService->getFamiliesJSON($this->FamilyService->search($query));
  });

  $this->get('/lastedited', function ($request, $response, $args) {
    $this->FamilyService->lastEdited();
  });

  $this->get('/byCheckNumber/{scanString}', function ($request, $response, $args) {
    $scanString = $args['scanString'];
    echo $this->FinancialService->getMemberByScanString($scanString);
  });

  $this->get('/byEnvelopeNumber/{envelopeNumber:[0-9]+}', function ($request, $response, $args) {
    $envelopeNumber = $args['envelopeNumber'];
    echo $this->FamilyService->getFamilyStringByEnvelope($envelopeNumber);
  });
});
