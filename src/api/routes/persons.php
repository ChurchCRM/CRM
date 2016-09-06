<?php
// Person APIs
use ChurchCRM\PersonQuery;
use ChurchCRM\Person;
Use Slim;
$app->group('/persons', function ()  {

  // search person by Name
  $this->get('/search/{query}', function ($request, $response, $args) {
    $query = $args['query'];
    $data = "[" . $this->PersonService->getPersonsJSON($this->PersonService->search($query)) . "]";
    return $response->withJson($data);
  });
  
  $this->get('/{personId:[0-9]+}/photo', function($request, $response, $args)  {
    $person = PersonQuery::create()->findOneById($args['personId']);
    
    $path = $person->getPhoto(dirname(dirname(dirname(__FILE__))), false);
   
    $image = file_get_contents($path);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $this->response->write($image);
    return $this->response->withHeader('Content-Type', 'content-type: ' . $finfo->buffer($image));
  });
});
