<?php
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2r;

$app->group('/cart', function () {
  
    $this->get('/',function($request,$response,$args) {
      return $response->withJSON(['PeopleCart' =>  $_SESSION['aPeopleCart']]);
    });

    $this->post('/', function ($request, $response, $args) {
          $cartPayload = (object)$request->getParsedBody();
          if ( isset ($cartPayload->Persons) && count($cartPayload->Persons) > 0 )
          {
           AddArrayToPeopleCart($cartPayload->Persons);
          }
          elseif ( isset ($cartPayload->Family) )
          {
            AddFamilyToPeopleCart($cartPayload->Family);
          }
          elseif ( isset ($cartPayload->Group) )
          {
            AddGroupToPeopleCart($cartPayload->Group);
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
            $personGroupRole = new Person2group2roleP2g2r();
            $personGroupRole->setPersonId($_SESSION['aPeopleCart'][$element['key']]);
            $personGroupRole->setRoleId($iGroupRole);
            $Group->addPerson2group2roleP2g2r($personGroupRole);
            $Group->save();
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
