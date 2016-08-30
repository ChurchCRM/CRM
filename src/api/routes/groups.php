<?php
// Routes
use ChurchCRM\Group;
use ChurchCRM\GroupQuery;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\ListOption;

$app->group('/groups', function () {

  $this->get('/',function () {
    echo ChurchCRM\Base\GroupQuery::create()->find()->toJSON();
  });
  
  $this->post('/', function ($request, $response, $args) {
    $groupName = $request->getParsedBody()["groupName"];
    $group = new Group();
    $group->setName($groupName);
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
  });
  
  $this->post('/{groupID:[0-9]+}/removeuser/{userID:[0-9]+}', function ($request, $response, $args) {
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
   
  });
  $this->post('/{groupID:[0-9]+}/adduser/{userID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $userID = $args['userID'];
    echo json_encode($this->GroupService->addUserToGroup($groupID, $userID, 0));
  });


  $this->post('/{groupID:[0-9]+}/userRole/{userID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $userID = $args['userID'];
    $roleID = $request->getParsedBody()["roleID"];
    echo json_encode($this->GroupService->setGroupMemberRole($groupID, $userID, $roleID));
  });

  $this->post('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $roleID = $args['roleID'];
    $input = $request->getParsedBody();
    if (property_exists($input, "groupRoleName")) {
      $this->GroupService->setGroupRoleName($groupID, $roleID, $input['groupRoleName']);
    } elseif (property_exists($input, "groupRoleOrder")) {
      $this->GroupService->setGroupRoleOrder($groupID, $roleID, $input['groupRoleOrder']);
    }

    echo json_encode(["success" => true]);
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
