<?php
// Routes
use ChurchCRM\Group;
use ChurchCRM\GroupQuery;

$app->group('/groups', function () {


  $this->post('/', function ($request, $response, $args) {
    $groupName = $request->getParsedBody()["groupName"];
    $response->withJson($this->GroupService->createGroup($groupName));
  });

  $this->post('/{groupID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $input = (object)$request->getParsedBody();
    $group = GroupQuery::create()->findOneById($groupID);
    $group->setName($input->groupName);
    $group->setType($input->groupType);
    $group->setDescription($input->Description);
    $group->save();
    echo $group->toJSON();
    
  });

  $this->get('/{groupID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    echo $this->GroupService->getGroupJSON($this->GroupService->getGroups($groupID));
  });

  $this->delete('/{groupID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $this->GroupService->deleteGroup($groupID);
    echo json_encode(["success" => true]);
  });
  $this->post('/{groupID:[0-9]+}/removeuser/{userID:[0-9]+}', function ($request, $response, $args) {
    $groupID = $args['groupID'];
    $userID = $args['userID'];
    $this->GroupService->removeUserFromGroup($groupID, $userID);
    echo json_encode(["success" => true]);
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

  $this->post('/sundayschool/{name}', function ($request, $response, $args) {
    $className = $args['name'];
    $sundaySchoolClass = new Group();
    $sundaySchoolClass->setName($className);
    $sundaySchoolClass->setType(4);
    $sundaySchoolClass->set;
    $sundaySchoolClass->save();
  });

});
