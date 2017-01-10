<?php

// Person APIs
use ChurchCRM\PersonQuery;
use ChurchCRM\util\PhotoUtils;

$app->group('/persons', function () {

  // search person by Name
  $this->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $data = "[" . $this->PersonService->getPersonsJSON($this->PersonService->search($query)) . "]";
    return $response->withJson($data);
  });
  
  $this->get('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $person = PersonQuery::create()->findPk($args['personId']);
    if ($person->isPhotoLocal()) 
    {
      return $response->write($person->getPhotoBytes())->withHeader('Content-type', $person->getPhotoContentType());
    }
    else if( $person->isPhotoRemote())
    {
      return $response->withRedirect($person->getPhotoURI());
    }
    else
    {
      return $response->withStatus(404);
    }
  });
  
   $this->get('/{personId:[0-9]+}/thumbnail', function($request, $response, $args)  {
    $person = PersonQuery::create()->findPk($args['personId']);
    if ( $person->isPhotoLocal()) 
    {
      return $response->write($person->getThumbnailBytes())->withHeader('Content-type', $person->getPhotoContentType());
    }
    else if( $person->isPhotoRemote())
    {
      return $response->withRedirect($person->getThumbnailURI());
    }
    else
    {
      return $response->withStatus(404);
    }
  });
  
  $this->post('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $personId =$args['personId'];
    $input = (object)$request->getParsedBody();
    $person = PersonQuery::create()->findPk($args['personId']);
    $person->setImageFromBase64($input->imgBase64);
    $response->withJSON(array("status"=>"success","upload"=>$upload));
  });
  
  $this->delete('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $person = PersonQuery::create()->findPk($args['personId']);
    return json_encode(array("status"=>$person->deletePhoto()));
  });
  
  $this->post('/{personId:[0-9]+}/addToCart', function($request, $response, $args)  {
    AddToPeopleCart($args['personId']);
  });
  
});
