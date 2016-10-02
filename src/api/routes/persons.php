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
  
  $this->post('/{personId:[0-9]+}/addToCart', function($request, $response, $args)  {
    AddToPeopleCart($args['personId']);
  });
  
});
