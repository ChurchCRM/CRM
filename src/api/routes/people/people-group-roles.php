<?php

// Routes
use ChurchCRM\Group;
use ChurchCRM\GroupQuery;
use ChurchCRM\Note;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery;

$app->group('/group/{groupID:[0-9]+}', function () {

    $this->post('/userRole/{userID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $roleID = $request->getParsedBody()['roleID'];
        $membership = ChurchCRM\Person2group2roleP2g2rQuery::create()->filterByGroupId($groupID)->filterByPersonId($userID)->findOne();
        $membership->setRoleId($roleID);
        $membership->save();
        echo $membership->toJSON();
    });

    $this->post('/defaultRole', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleID = $request->getParsedBody()['roleID'];
        $group = GroupQuery::create()->findPk($groupID);
        $group->setDefaultRole($roleID);
        $group->save();
        echo json_encode(['success' => true]);
    });

    $this->get('/roles', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $group = GroupQuery::create()->findOneById($groupID);
        $roles = ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->find();
        echo $roles->toJSON();
    });

    $this->post('/roles', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleName = $request->getParsedBody()['roleName'];
        echo $this->GroupService->addGroupRole($groupID, $roleName);
    });

    $this->delete('/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        echo json_encode($this->GroupService->deleteGroupRole($groupID, $roleID));
    });

    $this->post('/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        $input = (object)$request->getParsedBody();
        $group = GroupQuery::create()->findOneById($groupID);
        if (isset($input->groupRoleName)) {
            $groupRole = ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
            $groupRole->setOptionName($input->groupRoleName);
            $groupRole->save();

            return json_encode(['success' => true]);
        } elseif (isset($input->groupRoleOrder)) {
            $groupRole = ChurchCRM\ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
            $groupRole->setOptionSequence($input->groupRoleOrder);
            $groupRole->save();

            return json_encode(['success' => true]);
        }

        echo json_encode(['success' => false]);
    });

});
