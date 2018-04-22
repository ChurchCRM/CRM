<?php

use ChurchCRM\GroupQuery;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\Request\GroupAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;

$app->group('/group/{groupId:[0-9]+}', function () {

    $this->get('/roles', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $roles = ListOptionQuery::create()->filterById($group->getRoleListId())->find();
        return $response->withJSON($roles->toArray());
    });

    $this->post('/roles', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $groupRoleName = $request->getParsedBody()['roleName'];
        if (empty($groupRoleName)) {
            return $response->withStatus(401, "roleName " . gettext('not found'));
        }

        $options = ListOptionQuery::create()->filterByOptionName($groupRoleName)->filterByGroup($group)->find();
        if ($options->count() > 0 ) {
            return $response->withStatus(401, "roleName " . $groupRoleName . "" . gettext('already exists'));
        }

        return $response->write($this->GroupService->addGroupRole($group->getId(), $groupRoleName));
    });

    $this->get('/roles/default', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        return $response->withJSON(["default" => $group->getDefaultRole()]);
    });

    $this->post('/roles/default', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $roleID = $request->getParsedBody()['roleID'];
        $group->setDefaultRole($roleID);
        $group->save();
        return $response->withJSON(['success' => true]);
    });


    $this->post('/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        $input = (object)$request->getParsedBody();
        $group = GroupQuery::create()->findOneById($groupID);
        if (isset($input->groupRoleName)) {
            $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
            $groupRole->setOptionName($input->groupRoleName);
            $groupRole->save();

            return json_encode(['success' => true]);
        } elseif (isset($input->groupRoleOrder)) {
            $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
            $groupRole->setOptionSequence($input->groupRoleOrder);
            $groupRole->save();

            return json_encode(['success' => true]);
        }

        echo json_encode(['success' => false]);
    });

    $this->delete('/role/{roleID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        echo json_encode($this->GroupService->deleteGroupRole($groupID, $roleID));
    });

})->add(new GroupAPIMiddleware())->add(new ManageGroupRoleAuthMiddleware());
