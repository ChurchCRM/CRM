<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/groups', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Get(
     *     path="/groups/",
     *     summary="List all groups",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Array of all groups")
     * )
     */
    $group->get(
        '/',
        fn (Request $request, Response $response): Response => SlimUtils::renderJSON(
            $response,
            GroupQuery::create()->find()->toArray()
        )
    );

    /**
     * @OA\Get(
     *     path="/groups/calendars",
     *     summary="Get groups formatted for calendar display",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Groups with type, groupID, and name fields",
     *         @OA\JsonContent(type="array", @OA\Items(
     *             @OA\Property(property="type", type="string", example="group"),
     *             @OA\Property(property="groupID", type="integer"),
     *             @OA\Property(property="name", type="string")
     *         ))
     *     )
     * )
     */
    // get the group for the calendar, it's planned to only have the personan calendar and the calendar groups the user belongs to
    $group->get('/calendars', function (Request $request, Response $response, array $args): Response {
        $groups = GroupQuery::create()
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

    /**
     * @OA\Get(
     *     path="/groups/groupsInCart",
     *     summary="Get IDs of groups whose all members are in the session cart",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Group IDs where every member is in the cart",
     *         @OA\JsonContent(@OA\Property(property="groupsInCart", type="array", @OA\Items(type="integer")))
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/groups/{groupID}",
     *     summary="Get a single group by ID",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Group object"),
     *     @OA\Response(response=404, description="Group not found")
     * )
     */
    $group->get(
        '/{groupID:[0-9]+}',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderJSON(
            $response,
            GroupQuery::create()->findOneById($args['groupID'])->toArray()
        )
    );

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/cartStatus",
     *     summary="Check whether all members of a group are in the session cart",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Cart status for the group",
     *         @OA\JsonContent(@OA\Property(property="isInCart", type="boolean"))
     *     )
     * )
     */
    $group->get(
        '/{groupID:[0-9]+}/cartStatus',
        fn (Request $request, Response $response, array $args): Response => SlimUtils::renderJSON(
            $response,
            ['isInCart' => GroupQuery::create()->findOneById($args['groupID'])->checkAgainstCart()]
        )
    );

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/members",
     *     summary="Get members of a group with family address info",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Group members enriched with family address fields",
     *         @OA\JsonContent(@OA\Property(property="Person2group2roleP2g2rs", type="array", @OA\Items(type="object")))
     *     )
     * )
     */
    $group->get('/{groupID:[0-9]+}/members', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $members = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($groupID);

        // we loop to find the information in the family to add addresses etc ...
        foreach ($members as $member) {
            $p = $member->getPerson();
            $fam = $p->getFamily();

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

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/events",
     *     summary="Get group member-role memberships (events/roles per member)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Person-to-group-to-role membership records")
     * )
     */
    $group->get('/{groupID:[0-9]+}/events', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $members = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->findByGroupId($groupID);
        return SlimUtils::renderJSON($response, $members->toArray());
    });

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/roles",
     *     summary="Get the role options for a group",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Array of role list options for the group")
     * )
     */
    $group->get('/{groupID:[0-9]+}/roles', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $group = GroupQuery::create()->findOneById($groupID);
        $roles = ListOptionQuery::create()->filterById($group->getRoleListId())->find();
        return SlimUtils::renderJSON($response, $roles->toArray());
    });
});

$app->group('/groups', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Post(
     *     path="/groups/",
     *     summary="Create a new group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"groupName"},
     *             @OA\Property(property="groupName", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="isSundaySchool", type="boolean"),
     *             @OA\Property(property="groupType", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Newly created group object"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
    $group->post('/', function (Request $request, Response $response, array $args): Response {
        $groupSettings = $request->getParsedBody();
        $group = new Group();
        if ($groupSettings['isSundaySchool'] ?? false) {
            $group->makeSundaySchool();
        }
        $group->setName(InputUtils::sanitizeText($groupSettings['groupName']));
        $group->setDescription(InputUtils::sanitizeText($groupSettings['description'] ?? ''));
        // Only set the explicit group type if it was provided in the request.
        // This prevents overwriting types set by helper methods like makeSundaySchool().
        if (isset($groupSettings['groupType'])) {
            $group->setType($groupSettings['groupType']);
        }
        $group->save();
        return SlimUtils::renderJSON($response, $group->toArray());
    });

    /**
     * @OA\Post(
     *     path="/groups/{groupID}",
     *     summary="Update group name, type, and description (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="groupName", type="string"),
     *             @OA\Property(property="groupType", type="integer"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated group object"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
    $group->post('/{groupID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $input = $request->getParsedBody();
        $group = GroupQuery::create()->findOneById($groupID);
        $group->setName(InputUtils::sanitizeText($input['groupName']));
        $group->setType($input['groupType']);
        $group->setDescription(InputUtils::sanitizeText($input['description'] ?? ''));
        $group->save();
        return SlimUtils::renderJSON($response, $group->toArray());
    });

    /**
     * @OA\Delete(
     *     path="/groups/{groupID}",
     *     summary="Delete a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Group deleted successfully"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
    $group->delete('/{groupID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        GroupQuery::create()->findOneById($groupID)->delete();
        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Delete(
     *     path="/groups/{groupID}/removeperson/{userID}",
     *     summary="Remove a person from a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Person removed from group"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/addperson/{userID}",
     *     summary="Add a person to a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"PersonID"},
     *             @OA\Property(property="PersonID", type="integer"),
     *             @OA\Property(property="RoleID", type="integer", description="Defaults to group default role if omitted")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated membership records for the person in the group"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
    $group->post('/{groupID:[0-9]+}/addperson/{userID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $person = PersonQuery::create()->findPk($userID);
        $input = $request->getParsedBody();
        $group = GroupQuery::create()->findPk($groupID);

        $roleID = $input['RoleID'] ?? $group->getDefaultRole();

        $groupService = new GroupService();
        $groupService->addUserToGroup($groupID, $userID, $roleID);

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

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/userRole/{userID}",
     *     summary="Update a group member's role (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="roleID", type="integer"))
     *     ),
     *     @OA\Response(response=200, description="Updated membership object"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
    $group->post('/{groupID:[0-9]+}/userRole/{userID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $userID = $args['userID'];
        $roleID = $request->getParsedBody()['roleID'];
        $membership = Person2group2roleP2g2rQuery::create()->filterByGroupId($groupID)->filterByPersonId($userID)->findOne();
        $membership->setRoleId($roleID);
        $membership->save();
        return SlimUtils::renderJSON($response, $membership->toArray());
    });

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/roles/{roleID}",
     *     summary="Update a group role name or sort order (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="roleID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="groupRoleName", type="string", description="New role name"),
     *             @OA\Property(property="groupRoleOrder", type="integer", description="New sort order")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Role updated successfully"),
     *     @OA\Response(response=403, description="ManageGroupRole role required"),
     *     @OA\Response(response=500, description="Failed to update role")
     * )
     */
    $group->post('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = $args['groupID'];
            $roleID = $args['roleID'];
            $input = $request->getParsedBody();
            $group = GroupQuery::create()->findOneById($groupID);
            if (isset($input['groupRoleName'])) {
                $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
                // Sanitize role name to prevent XSS
                $groupRole->setOptionName(InputUtils::sanitizeText($input['groupRoleName']));
                $groupRole->save();

                return SlimUtils::renderSuccessJSON($response);
            } elseif (isset($input['groupRoleOrder'])) {
                $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
                $groupRole->setOptionSequence($input['groupRoleOrder']);
                $groupRole->save();

                return SlimUtils::renderSuccessJSON($response);
            }
            throw new \Exception(gettext('invalid group request'));
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update role. Please try again.'), [], $status, $e, $request);
        }
    });

    /**
     * @OA\Delete(
     *     path="/groups/{groupID}/roles/{roleID}",
     *     summary="Delete a group role (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="roleID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Role deleted successfully"),
     *     @OA\Response(response=403, description="ManageGroupRole role required"),
     *     @OA\Response(response=500, description="Failed to delete role")
     * )
     */
    $group->delete('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = $args['groupID'];
            $roleID = $args['roleID'];
            $groupService = new GroupService();

            return SlimUtils::renderJSON($response, $groupService->deleteGroupRole($groupID, $roleID));
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete role. Please try again.'), [], $status, $e, $request);
        }
    });

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/roles",
     *     summary="Add a new role to a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="roleName", type="string"))
     *     ),
     *     @OA\Response(response=200, description="New role created"),
     *     @OA\Response(response=403, description="ManageGroupRole role required"),
     *     @OA\Response(response=500, description="Failed to add role")
     * )
     */
    $group->post('/{groupID:[0-9]+}/roles', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = $args['groupID'];
            $roleName = $request->getParsedBody()['roleName'];
            $groupService = new GroupService();

            return SlimUtils::renderJSON($response, $groupService->addGroupRole($groupID, $roleName));
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Failed to add role. Please try again.'), [], $status, $e, $request);
        }
    });

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/defaultRole",
     *     summary="Set the default role for a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="roleID", type="integer"))
     *     ),
     *     @OA\Response(response=200, description="Default role updated"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
    $group->post('/{groupID:[0-9]+}/defaultRole', function (Request $request, Response $response, array $args): Response {
        $groupID = $args['groupID'];
        $roleID = $request->getParsedBody()['roleID'];
        $group = GroupQuery::create()->findPk($groupID);
        $group->setDefaultRole($roleID);
        $group->save();
        return SlimUtils::renderSuccessJSON($response);
    });

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/setGroupSpecificPropertyStatus",
     *     summary="Enable or disable group-specific properties (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(@OA\Property(property="GroupSpecificPropertyStatus", type="boolean"))
     *     ),
     *     @OA\Response(response=200, description="Property status updated",
     *         @OA\JsonContent(@OA\Property(property="status", type="string"))
     *     ),
     *     @OA\Response(response=403, description="ManageGroupRole role required"),
     *     @OA\Response(response=500, description="Failed to update properties")
     * )
     */
    $group->post('/{groupID:[0-9]+}/setGroupSpecificPropertyStatus', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = $args['groupID'];
            $input = $request->getParsedBody();
            $groupService = new GroupService();

            if ($input['GroupSpecificPropertyStatus']) {
                $groupService->enableGroupSpecificProperties($groupID);
                return SlimUtils::renderJSON($response, ['status' => 'group specific properties enabled']);
            } else {
                $groupService->disableGroupSpecificProperties($groupID);
                return SlimUtils::renderJSON($response, ['status' => 'group specific properties disabled']);
            }
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update properties. Please try again.'), [], $status, $e, $request);
        }
    });

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/settings/active/{value}",
     *     summary="Set a group's active status (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="value", in="path", required=true, @OA\Schema(type="string", enum={"true","false"})),
     *     @OA\Response(response=200, description="Active status updated"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/settings/email/export/{value}",
     *     summary="Set the email export flag for a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="value", in="path", required=true, @OA\Schema(type="string", enum={"true","false"})),
     *     @OA\Response(response=200, description="Email export flag updated"),
     *     @OA\Response(response=403, description="ManageGroupRole role required")
     * )
     */
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
