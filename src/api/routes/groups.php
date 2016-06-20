<?php
// Routes

$app->group('/groups', function () use ($app) {
  $groupService = $app->GroupService;

  $app->post('/:groupID/userRole/:userID', function ($groupID, $userID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo json_encode($groupService->setGroupMemberRole($groupID, $userID, $input->roleID));
  });

  $app->post('/:groupID/removeuser/:userID', function ($groupID, $userID) use ($groupService) {

    $groupService->removeUserFromGroup($groupID, $userID);
    echo json_encode(["success" => true]);
  });
  $app->post('/:groupID/adduser/:userID', function ($groupID, $userID) use ($groupService) {

    echo json_encode($groupService->addUserToGroup($groupID, $userID, 0));
  });
  $app->delete('/:groupID', function ($groupID) use ($groupService) {

    $groupService->deleteGroup($groupID);
    echo json_encode(["success" => true]);
  });

  $app->get('/:groupID', function ($groupID) use ($groupService) {

    echo $groupService->getGroupJSON($groupService->getGroups($groupID));
  });

  $app->post('/:groupID', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo $groupService->updateGroup($groupID, $input);
  });

  $app->post('/', function () use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo json_encode($groupService->createGroup($input->groupName));
  });

  $app->post('/:groupID/roles/:roleID', function ($groupID, $roleID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    if (property_exists($input, "groupRoleName")) {
      $groupService->setGroupRoleName($groupID, $roleID, $input->groupRoleName);
    } elseif (property_exists($input, "groupRoleOrder")) {
      $groupService->setGroupRoleOrder($groupID, $roleID, $input->groupRoleOrder);
    }

    echo json_encode(["success" => true]);
  });

  $app->delete('/:groupID/roles/:roleID', function ($groupID, $roleID) use ($app, $groupService) {

    echo json_encode($groupService->deleteGroupRole($groupID, $roleID));
  });

  $app->post('/:groupID/roles', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    echo $groupService->addGroupRole($groupID, $input->roleName);
  });

  $app->post('/:groupID/defaultRole', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    $groupService->setGroupRoleAsDefault($groupID, $input->roleID);
    echo json_encode(["success" => true]);
  });

  $app->post('/:groupID/setGroupSpecificPropertyStatus', function ($groupID) use ($app, $groupService) {

    $input = getJSONFromApp($app);
    if ($input->GroupSpecificPropertyStatus) {
      $groupService->enableGroupSpecificProperties($groupID);
      echo json_encode(["status" => "group specific properties enabled"]);
    } else {
      $groupService->disableGroupSpecificProperties($groupID);
      echo json_encode(["status" => "group specific properties disabled"]);
    }
  });
});
