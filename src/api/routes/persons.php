<?php
// Person APIs
use ChurchCRM\PersonQuery;
use ChurchCRM\util\PhotoUtils;

$app->group('/persons', function ()  {

  // search person by Name
  $this->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $data = "[" . $this->PersonService->getPersonsJSON($this->PersonService->search($query)) . "]";
    return $response->withJson($data);
  });
  
  $this->get('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $person = PersonQuery::create()->findPk($args['personId']);
    $photo = $person->getPhoto();
    if ( $photo->type=="localFile" ) 
    {
      return $response->write(file_get_contents($photo->path));
    }
    else
    {
      return $response->withRedirect($photo->path);
    }
  });
  
  $this->post('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $personId =$args['personId'];
    $input = (object)$request->getParsedBody();
    PhotoUtils::deletePhotos("Person", $personId);
    $upload = PhotoUtils::setImageFromBase64("Person", $personId, $input->imgBase64);
    
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
