<?php

$app->group('/cart', function () {

  $this->post('/', function ($request, $response, $args) {
        $cartPayload = (object)$request->getParsedBody();
        if ( isset ($cartPayload->Persons) && count($cartPayload->Persons) > 0 )
        {
         AddArrayToPeopleCart($cartPayload->Persons);
        }
        else
        {
          throw new \Exception(gettext("POST to cart requires a Persons array"),500);
        }
        return $response->withJson(['status' => "success"]);
    });

});
