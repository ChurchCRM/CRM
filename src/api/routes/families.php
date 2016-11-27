<?php
// Routes

use ChurchCRM\FamilyQuery;
use ChurchCRM\PhotoUtils;

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
  
  $this->post('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
    $familyId =$args['familyId'];
    $input = (object)$request->getParsedBody();
    PhotoUtils::setPhotosDir(dirname(dirname(__DIR__))."/Images");
    PhotoUtils::deletePhotos("Family", $familyId);
    $upload = PhotoUtils::setImageFromBase64("Family", $familyId, $input->imgBase64);
    
    $response->withJSON(array("status"=>"success","upload"=>$upload));
  });
  
   $this->delete('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
    $family = FamilyQuery::create()->findPk($args['familyId']);
    return json_encode(array("status"=>$family->deletePhoto()));
  });
  
});
