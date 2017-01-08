<?php

// Routes
use ChurchCRM\FamilyQuery;
use ChurchCRM\util\PhotoUtils;

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
  
    $this->get('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
      $family = FamilyQuery::create()->findPk($args['familyId']);
      if ( $family->isPhotoLocal() ) 
      {
        return $response->write($family->getPhotoBytes());
      }
      return $response->withStatus(404);
    });
    
    $this->get('/{familyId:[0-9]+}/thumbnail', function($request, $response, $args)  {
      $family = FamilyQuery::create()->findPk($args['familyId']);
      if ( $family->isPhotoLocal()) 
      {
        return $response->write($family->getThumbnailBytes())->withHeader('Content-type', $family->getPhotoContentType());
      }
    });
  
    $this->post('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
      $input = (object)$request->getParsedBody();
      $family = FamilyQuery::create()->findPk($args['familyId']);
      $family->setImageFromBase64($input->imgBase64);

      $response->withJSON(array("status"=>"success","upload"=>$upload));
    });
  
    $this->delete('/{familyId:[0-9]+}/photo', function($request, $response, $args)  {
     $family = FamilyQuery::create()->findPk($args['familyId']);
     return json_encode(array("status"=>$family->deletePhoto()));
   });

});
