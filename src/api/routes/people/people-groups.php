<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/groups', function (RouteCollectorProxy $group): void {
    $group->get(
        '/',
        fn (Request $request, Response $response): Response => SlimUtils::renderJSON(
            $response,
            GroupQuery::create()->find()->toArray()
        )
    );

    // get the group for the calendar, it's planned to only have the personan calendar and the calendar groups the user belongs to
    $group->get('/calendars', function (Request $request, Response $response, array $args): Response {
        $groups = GroupQuery::Create()
            ->orderByName()
            ->find();

        $return = [];
        foreach ($groups as $group) {
            $values['type'] = 'group';
            $values['groupID'] = $group->getID();
            $values['name'] = $group->getName();

            $return[] = $values;
        }

        return SlimUtils::renderJSON($response, $return);
    });

    $group->get('/groupsInCart', function (Request $request, Response $response, array $args): Response {
        $groupsInCart = [];
        $groups = GroupQuery::create()->find();
        foreach ($groups as $group) {
            if ($group->checkAgainstCart()) {
                $groupsInCart[] = $group->getId();
            }
        }
        return SlimUtils::renderJSON($response, ['groupsInCart' => $groupsInCart]);
    });

    $group->get(
        '/{groupID:[0-9]+}',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderJSON(
            $response,
            GroupQuery::create()->findOneById($args['groupID'])->toArray()
        )
    );

    $group->get(
        '/{groupID:[0-9]+}/cartStatus',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderJSON(
            $response,
            GroupQuery::create()->findOneById($args['groupID'])->checkAgainstCart()
        )
    );

    $group->get('/{groupID:[0-9]+}/members', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $members = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($groupID);

        // we loop to find the information in the family to add addresses etc ...
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

        return SlimUtils::renderJSON($response, ['Person2group2roleP2g2rs' => $members->toArray()]);
    });

    $group->get('/{groupID:[0-9]+}/events', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $members = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($groupID);
        return SlimUtils::renderJSON($response, $members->toArray());
    });

    $group->get('/{groupID:[0-9]+}/roles', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $group = GroupQuery::create()->findOneById($groupID);
        $roles = ListOptionQuery::create()->filterById($group->getRoleListId())->find();
        return SlimUtils::renderJSON($response, $roles->toArray());
    });
});

$app->group('/groups', function (RouteCollectorProxy $group): void {
    $group->post('/', function (Request $request, Response $response, array $args): Response {
        $groupSettings = $request->getParsedBody();
        $group = new Group();
        if ($groupSettings['isSundaySchool']) {
            $group->makeSundaySchool();
        }
        $group->setName($groupSettings['groupName']);
        $group->save();
        return SlimUtils::renderJSON($response, $group->toArray());
    });

    $group->post('/{groupID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $input = $request->getParsedBody();
        $group = GroupQuery::create()->findOneById($groupID);
        $group->setName($input['groupName']);
        $group->setType($input['groupType']);
        $group->setDescription($input['description']);
        $group->save();
        return SlimUtils::renderJSON($response, $group->toArray());
    });

    $group->delete('/{groupID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        GroupQuery::create()->findOneById($groupID)->delete();
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->delete('/{groupID:[0-9]+}/removeperson/{userID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $person = PersonQuery::create()->findPk($userID);
        $group = GroupQuery::create()->findPk($groupID);
        $groupRoleMemberships = $group->getPerson2group2roleP2g2rs();
        foreach ($groupRoleMemberships as $groupRoleMembership) {
            if ($groupRoleMembership->getPersonId() == $person->getId()) {
                $groupRoleMembership->delete();
                $note = new Note();
                $note->setText(gettext('Deleted from group') . ': ' . $group->getName());
                $note->setType('group');
                $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
                $note->setPerId($person->getId());
                $note->save();
            }
        }
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/{groupID:[0-9]+}/addperson/{userID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $person = PersonQuery::create()->findPk($userID);
        $input = $request->getParsedBody();
        $group = GroupQuery::create()->findPk($groupID);
        $p2g2r = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($groupID)
            ->filterByPersonId($userID)
            ->findOneOrCreate();
        if ($input['RoleID']) {
            $p2g2r->setRoleId($input['RoleID']);
        } else {
            $p2g2r->setRoleId($group->getDefaultRole());
        }

        $group->addPerson2group2roleP2g2r($p2g2r);
        $group->save();
        $note = new Note();
        $note->setText(gettext('Added to group') . ': ' . $group->getName());
        $note->setType('group');
        $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
        $note->setPerId($person->getId());
        $note->save();
        $members = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByPersonId($input['PersonID'])
            ->findByGroupId($groupID);
        return SlimUtils::renderJSON($response, $members->toArray());
    });

    $group->post('/{groupID:[0-9]+}/userRole/{userID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $roleID = $request->getParsedBody()['roleID'];
        $membership = Person2group2roleP2g2rQuery::create()->filterByGroupId($groupID)->filterByPersonId($userID)->findOne();
        $membership->setRoleId($roleID);
        $membership->save();
        return SlimUtils::renderJSON($response, $membership->toArray());
    });

    $group->post('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        $input = $request->getParsedBody();
        $group = GroupQuery::create()->findOneById($groupID);
        if (isset($input['groupRoleName'])) {
            $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
            $groupRole->setOptionName($input['groupRoleName']);
            $groupRole->save();

            return SlimUtils::renderSuccessJSON($response);
        } elseif (isset($input['groupRoleOrder'])) {
            $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
            $groupRole->setOptionSequence($input['groupRoleOrder']);
            $groupRole->save();

            return SlimUtils::renderSuccessJSON($response);
        }
        throw new \Exception(gettext('invalid group request'));
    });

    $group->delete('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $roleID = $args['roleID'];
        $groupService = $this->get('GroupService');

        return SlimUtils::renderJSON($response, $groupService->deleteGroupRole($groupID, $roleID));
    });

    $group->post('/{groupID:[0-9]+}/roles', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $roleName = $request->getParsedBody()['roleName'];
        $groupService = $this->get('GroupService');

        return SlimUtils::renderJSON($response, $groupService->addGroupRole($groupID, $roleName));
    });

    $group->post('/{groupID:[0-9]+}/defaultRole', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $roleID = $request->getParsedBody()['roleID'];
        $group = GroupQuery::create()->findPk($groupID);
        $group->setDefaultRole($roleID);
        $group->save();
        return SlimUtils::renderSuccessJSON($response);
    });

    $group->post('/{groupID:[0-9]+}/setGroupSpecificPropertyStatus', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $input = $request->getParsedBody();
        $groupService = $this->get('GroupService');

        if ($input['GroupSpecificPropertyStatus']) {
            $groupService->enableGroupSpecificProperties($groupID);
            return SlimUtils::renderJSON($response, ['status' => 'group specific properties enabled']);
        } else {
            $groupService->disableGroupSpecificProperties($groupID);
            return SlimUtils::renderJSON($response, ['status' => 'group specific properties disabled']);
        }
    });

    $group->post('/{groupID:[0-9]+}/settings/active/{value}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $flag = $args['value'];
        if ($flag == 'true' || $flag == 'false') {
            $group = GroupQuery::create()->findOneById($groupID);
            if ($group === null) {
                throw new \Exception(gettext('invalid group id'));
            }

            $group->setActive($flag);
            $group->save();

            return SlimUtils::renderSuccessJSON($response);
        } else {
            throw new \Exception(gettext('invalid status value'));
        }
    });

    $group->post('/{groupID:[0-9]+}/settings/email/export/{value}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $flag = $args['value'];
        if ($flag == 'true' || $flag == 'false') {
            $group = GroupQuery::create()->findOneById($groupID);
            if ($group === null) {
                throw new \Exception(gettext('invalid group id'));
            }

            $group->setIncludeInEmailExport($flag);
            $group->save();

            return SlimUtils::renderSuccessJSON($response);
        } else {
            throw new \Exception(gettext('invalid export value'));
        }
    });
})->add(ManageGroupRoleAuthMiddleware::class);
