<?php

// Routes
use ChurchCRM\GroupQuery;
use ChurchCRM\Note;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\Request\GroupAPIMiddleware;

$app->group('/group/{groupId:[0-9]+}', function () {

    $this->get('', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        return $response->withJSON($group->toArray());
    });

    $this->delete('', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $group->delete();
        return $response->withStatus(200);
    });

    $this->post('', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $input = (object)$request->getParsedBody();
        $group->setName($input->groupName);
        $group->setType($input->groupType);
        $group->setDescription($input->description);
        $group->save();
        return $response->withJSON($group->toArray());
    });

    $this->get('/cartStatus', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        return $response->withJSON($group->checkAgainstCart());
    });

    $this->get('/members', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $members = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($group->getId());


        foreach ($members as $member) {
            $p = $member->getPerson();
            $fam = $p->getFamily();

            // Philippe Logel : this is usefull when a person don't have a family : ie not an address
            if (!empty($fam)) {
                $p->setAddress1($fam->getAddress1());
                $p->setAddress2($fam->getAddress2());
                $p->setCity($fam->getCity());
                $p->setState($fam->getState());
                $p->setZip($fam->getZip());
            }
        }

        return $response->withJSON(["members" => $members->toArray()]);
    });

    $this->get('/events', function ($request, $response, $args) {
        $group = $request->getAttribute("group");
        $members = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($group->getId());
        echo $members->toJSON();
    });

    $this->delete('/removeperson/{userID:[0-9]+}', function ($request, $response, $args) {
        $userID = $args['userID'];
        $person = PersonQuery::create()->findPk($userID);
        $group = $request->getAttribute("group");
        $groupRoleMemberships = $group->getPerson2group2roleP2g2rs();
        foreach ($groupRoleMemberships as $groupRoleMembership) {
            if ($groupRoleMembership->getPersonId() == $person->getId()) {
                $groupRoleMembership->delete();
                $note = new Note();
                $note->setText(gettext("Deleted from group") . ": " . $group->getName());
                $note->setType("group");
                $note->setEntered($_SESSION['user']->getId());
                $note->setPerId($person->getId());
                $note->save();
            }
        }
        echo json_encode(['success' => 'true']);
    });

    $this->post('/addperson/{userID:[0-9]+}', function ($request, $response, $args) {
        $userID = $args['userID'];
        $person = PersonQuery::create()->findPk($userID);
        $input = (object)$request->getParsedBody();
        $group = $request->getAttribute("group");
        $p2g2r = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByPersonId($userID)
            ->findOneOrCreate();
        if ($input->RoleID) {
            $p2g2r->setRoleId($input->RoleID);
        } else {
            $p2g2r->setRoleId($group->getDefaultRole());
        }

        $group->addPerson2group2roleP2g2r($p2g2r);
        $group->save();
        $note = new Note();
        $note->setText(gettext("Added to group") . ": " . $group->getName());
        $note->setType("group");
        $note->setEntered($_SESSION['user']->getId());
        $note->setPerId($person->getId());
        $note->save();
        $members = ChurchCRM\Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByPersonId($input->PersonID)
            ->findByGroupId($group->getId());
        echo $members->toJSON();
    });

    $this->post('/userRole/{userID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $roleID = $request->getParsedBody()['roleID'];
        $membership = ChurchCRM\Person2group2roleP2g2rQuery::create()->filterByGroupId($groupID)->filterByPersonId($userID)->findOne();
        $membership->setRoleId($roleID);
        $membership->save();
        echo $membership->toJSON();
    });




    $this->delete('/roles/{roleID:[0-9]+}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        echo json_encode($this->GroupService->deleteGroupRole($groupID, $roleID));
    });

    $this->post('/defaultRole', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $group = GroupQuery::create()->findPk($groupID);
        $roleID = $request->getParsedBody()['roleID'];
        $group->setDefaultRole($roleID);
        $group->save();
        echo json_encode(['success' => true]);
    });

    $this->post('/setGroupSpecificPropertyStatus', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $input = $request->getParsedBody();
        if ($input['GroupSpecificPropertyStatus']) {
            $this->GroupService->enableGroupSpecificProperties($groupID);
            echo json_encode(['status' => 'group specific properties enabled']);
        } else {
            $this->GroupService->disableGroupSpecificProperties($groupID);
            echo json_encode(['status' => 'group specific properties disabled']);
        }
    });

    $this->post('/settings/active/{value}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $flag = $args['value'];
        if ($flag == "true" || $flag == "false") {
            $group = GroupQuery::create()->findOneById($groupID);
            if ($group != null) {
                $group->setActive($flag);
                $group->save();
            } else {
                return $response->withStatus(500, gettext('invalid group id'));
            }
            return $response->withJson(['status' => "success"]);
        } else {
            return $response->withStatus(500, gettext('invalid status value'));
        }
    });

    $this->post('/settings/email/export/{value}', function ($request, $response, $args) {
        $groupID = $args['groupID'];
        $flag = $args['value'];
        if ($flag == "true" || $flag == "false") {
            $group = GroupQuery::create()->findOneById($groupID);
            if ($group != null) {
                $group->setIncludeInEmailExport($flag);
                $group->save();
            } else {
                return $response->withStatus(500, gettext('invalid group id'));
            }
            return $response->withJson(['status' => "success"]);
        } else {
            return $response->withStatus(500, gettext('invalid export value'));
        }
    });


})->add(new GroupAPIMiddleware());
