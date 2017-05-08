<?php
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\dto\Cart;

$app->group('/cart', function () {
  
    $this->get('/',function($request,$response,$args) {
      return $response->withJSON(['PeopleCart' =>  $_SESSION['aPeopleCart']]);
    });

    $this->post('/', function ($request, $response, $args) {
          $cartPayload = (object)$request->getParsedBody();
          if ( isset ($cartPayload->Persons) && count($cartPayload->Persons) > 0 )
          {
            Cart::AddPersonArray($cartPayload->Persons);
          }
          elseif ( isset ($cartPayload->Family) )
          {
            Cart::AddFamily($cartPayload->Family);
          }
          elseif ( isset ($cartPayload->Group) )
          {
            Cart::AddGroup($cartPayload->Group);
          }
          else
          {
            throw new \Exception(gettext("POST to cart requires a Persons array"),500);
          }
          return $response->withJson(['status' => "success"]);
      });
      
    $this->post('/emptyToGroup', function($request, $response, $args) {
        $cartPayload = (object)$request->getParsedBody();
        $iGroupID = $cartPayload->groupID;
        $iGroupRole = $cartPayload->groupRoleID;
        $iCount = 0;
        $Group = GroupQuery::create()->findOneById($iGroupID);
        while ($element = each($_SESSION['aPeopleCart'])) {
            $personGroupRole = \ChurchCRM\Person2group2roleP2g2rQuery::create()
                    ->filterByGroupId($iGroupID)
                    ->filterByPersonId($_SESSION['aPeopleCart'][$element['key']])
                    ->filterByRoleId($iGroupRole)
                    ->findOneOrCreate()
                    ->setPersonId($_SESSION['aPeopleCart'][$element['key']])
                    ->setRoleId($iGroupRole)
                    ->setGroupId($iGroupID)
                    ->save();
            
          
            $iCount += 1;
        }
        $_SESSION['aPeopleCart'] = [];
        return $response->withJson([
            'status' => "success",
            'message' => $iCount.' records(s) successfully added to selected Group.'
        ]);
    });

    /**
     * delete. This will empty the cart
     */
    $this->delete('/', function ($request, $response, $args) {
      
        $cartPayload = (object)$request->getParsedBody();
        if ( isset ($cartPayload->Persons) && count($cartPayload->Persons) > 0 )
        {
          RemoveArrayFromPeopleCart($cartPayload->Persons);
        }
        else
        {
          $sMessage = gettext('Your cart is empty');
          if(sizeof($_SESSION['aPeopleCart'])>0) {
              $_SESSION['aPeopleCart'] = [];
              $sMessage = gettext('Your cart has been successfully emptied');
          }
        }
        return $response->withJson([
            'status' => "success",
            'message' =>$sMessage
        ]);

    });

});
