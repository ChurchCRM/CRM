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

    /**
     * delete. This will empty the cart
     */
    $this->delete('/', function ($request, $response, $args) {
        $sMessage = gettext('Your cart is empty');
        if(sizeof($_SESSION['aPeopleCart'])>0) {
            $_SESSION['aPeopleCart'] = [];
            $sMessage = gettext('Your cart has been successfully emptied');
        }
        return $response->withJson([
            'status' => "success",
            'message' =>$sMessage
        ]);

    });

});
