<?php
// Person APIs
use ChurchCRM\PersonQuery;

$app->group('/persons', function ()  {

  // search person by Name
  $this->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $data = "[" . $this->PersonService->getPersonsJSON($this->PersonService->search($query)) . "]";
    return $response->withJson($data);
  });
  
  $this->get('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $person = PersonQuery::create()->findPk($args['personId']);
    return $response->withRedirect($person->getPhoto());
  });
  
  $this->post('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $person =$args['personId'];
    $input = (object)$request->getParsedBody();
   
        $img = $input->imgBase64;
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $fileData = base64_decode($img);
        //saving
        $fileName = 'photo.png';
        //file_put_contents($fileName, $fileData);    
    
    echo json_encode(array("status"=>"success"));
  });
  
  $this->post('/{personId:[0-9]+}/addToCart', function($request, $response, $args)  {
    AddToPeopleCart($args['personId']);
  });
  
});
