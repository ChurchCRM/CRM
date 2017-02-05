<?php

$app->group('/cart', function () {

  $this->post('/', function ($request, $response, $args) {
        $cartPayload = (object)$request->getParsedBody();
        if ( isset ($cartPayload->Persons) )
        {
         AddArrayToPeopleCart($cartPayload->Persons);
        }
        echo json_encode(['status' => "success"]);
    });

});
