<?php
// Routes
use ChurchCRM\Group;
use ChurchCRM\GroupQuery;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\ListOption;

$app->group('/groups', function () {

  $this->get('/',function () {
    echo GroupQuery::create()->find()->toJSON();
  });
  
  $this->get('/groupsInCart',function() {
    $groupsInCart = Array();
    $groups = GroupQuery::create()->find();
    foreach ($groups as $group)
    {
      if ($group->checkAgainstCart())
      {
        array_push($groupsInCart,$group->getId());
      } 
    }
   echo json_encode(['groupsInCart' => $groupsInCart]);    
  });
  
  $this->post('/', function ($request, $response, $args) {
    $groupSettings = (object)$request->getParsedBody();
    $group = new Group();
    if($groupSettings->isSundaySchool)
    {
      $group->makeSundaySchool();
    }
    $group->setName($groupSettings->groupName);
    $group->save();
    echo $group->toJSON();
  });

  $this->post('/{groupID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $input = (object)$request->getParsedBody();
    $group = GroupQuery::create()->findOneById($groupID);
    $group->setName($input->groupName);
    $group->setType($input->groupType);
    $group->setDescription($input->description);
    $group->save();
    echo $group->toJSON();
    
  });
  
   $this->get('/{groupID:[0-9]+}', function ($request, $response, $args) {
    echo GroupQuery::create()->findOneById($args['groupID'])->toJSON();
            
  });
  
  $this->get('/{groupID:[0-9]+}/cartStatus', function ($request, $response, $args) {
   
    echo GroupQuery::create()->findOneById($args['groupID'])->checkAgainstCart();
   });
  

  $this->delete('/{groupID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    GroupQuery::create()->findOneById($groupID)->delete();
    echo json_encode(array("status"=>"success"));
  });
  
   $this->get('/{groupID:[0-9]+}/members', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $members = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($groupID);
    echo $members->toJSON();
   
  });
  
  $this->delete('/{groupID:[0-9]+}/removeuser/{userID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $userID = $args['userID'];
    $groupID = $args['groupID'];
    $group = GroupQuery::create()->findOneById($groupID);
    $groupRoleMemberships = $group->getPerson2group2roleP2g2rs();
    foreach ($groupRoleMemberships as $groupRoleMembership)
    {
      if ($groupRoleMembership->getPersonId() == $userID)
      {
        $groupRoleMembership->delete();
      }
    }
    echo json_encode(array("success" => "true"));
   
  });
  $this->post('/{groupID:[0-9]+}/adduser/{userID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $userID = $args['userID'];
    $group = GroupQuery::create()->findOneById($groupID);
    $p2g2r = new ChurchCRM\Person2group2roleP2g2r();
    $p2g2r->setGroupId($groupID);
    $p2g2r->setPersonId($userID);
    $p2g2r->setRoleId($group->getDefaultRole());
    $p2g2r->save();
    $members = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByPersonId($userID)
            ->findByGroupId($groupID);
    echo $members->toJSON();
  });


  $this->post('/{groupID:[0-9]+}/userRole/{userID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $userID = $args['userID'];
    $roleID = $request->getParsedBody()["roleID"];
    $membership = ChurchCRM\Person2group2roleP2g2rQuery::create()->filterByGroupId($groupID)->filterByPersonId($userID)->findOne();
    $membership->setRoleId($roleID);
    $membership->save();
    echo $membership->toJSON();
  });

  $this->post('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $roleID = $args['roleID'];
    $input = (object)$request->getParsedBody();
    $group = GroupQuery::create()->findOneById($groupID);
    if (isset($input->groupRoleName))
    {
      $groupRole =  ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
      $groupRole->setOptionName($input->groupRoleName);
      $groupRole->save();
      return json_encode(["success" => true]);
    } 
    elseif (isset($input->groupRoleOrder)) 
    {
      $groupRole =  ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
      $groupRole->setOptionSequence($input->groupRoleOrder);
      $groupRole->save();
      return json_encode(["success" => true]);
    }

    echo json_encode(["success" => false]);
  });
  
  $this->get('/{groupID:[0-9]+}/roles', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $group = GroupQuery::create()->findOneById($groupID);
    $roles=  ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->find();
    echo $roles->toJSON();
  });

  $this->delete('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $roleID = $args['roleID'];
    echo json_encode($this->GroupService->deleteGroupRole($groupID, $roleID));
  });

  $this->post('/{groupID:[0-9]+}/roles', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $roleName = $request->getParsedBody()["roleName"];
    echo $this->GroupService->addGroupRole($groupID, $roleName);
  });

  $this->post('/{groupID:[0-9]+}/defaultRole', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $roleID = $request->getParsedBody()["roleID"];
    $this->GroupService->setGroupRoleAsDefault($groupID, $roleID);
    echo json_encode(["success" => true]);
  });

  $this->post('/{groupID:[0-9]+}/setGroupSpecificPropertyStatus', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $input = $request->getParsedBody();
    if ($input['GroupSpecificPropertyStatus']) {
      $this->GroupService->enableGroupSpecificProperties($groupID);
      echo json_encode(["status" => "group specific properties enabled"]);
    } else {
      $this->GroupService->disableGroupSpecificProperties($groupID);
      echo json_encode(["status" => "group specific properties disabled"]);
    }
  });


});
