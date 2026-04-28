<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Base\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Note;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Service\GroupService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SundaySchoolService;
use ChurchCRM\Slim\Middleware\Api\GroupMiddleware;
use ChurchCRM\Slim\Middleware\Api\PersonMiddleware;
use ChurchCRM\Slim\Middleware\InputSanitizationMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Setting\SundaySchoolEnabledMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CsvExporter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

/**
 * Return the set of person IDs that have a given property assigned (e.g. "Do Not SMS").
 * The property is identified by a SystemConfig key that stores the property ID.
 *
 * @return array<int, true> personId => true
 */
function _getExcludedPersonIdSet(string $configKey): array
{
    $propertyId = (int) SystemConfig::getValue($configKey);
    if ($propertyId <= 0) {
        return [];
    }
    $set = [];
    foreach (RecordPropertyQuery::create()->filterByPropertyId($propertyId)->find() as $r) {
        $set[(int) $r->getRecordId()] = true;
    }
    return $set;
}

/**
 * Build a standard phone-response array from a list of phone strings.
 *
 * @param string[] $phoneList
 * @return array{phones: string[], displayList: string}
 */
function _buildPhoneResponse(array $phoneList): array
{
    return [
        'phones'      => $phoneList,
        'displayList' => implode(', ', $phoneList),
    ];
}

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
    $group->get('/', function (Request $request, Response $response): Response {
        $groups = GroupQuery::create()->orderByName()->find();

        // Pre-fetch group type names (list option ID = 3)
        $typeNames = [];
        foreach (ListOptionQuery::create()->filterById(3)->find() as $opt) {
            $typeNames[(int) $opt->getOptionId()] = $opt->getOptionName();
        }

        // Pre-fetch role names per role-list ID
        $allRoleListIds = [];
        foreach ($groups as $g) {
            $allRoleListIds[] = $g->getRoleListId();
        }
        $rolesByListId = [];
        if (!empty($allRoleListIds)) {
            foreach (ListOptionQuery::create()->filterById($allRoleListIds)->orderByOptionSequence()->find() as $opt) {
                $rolesByListId[(int) $opt->getId()][] = $opt->getOptionName();
            }
        }

        // Pre-fetch member counts per group
        $memberCounts = [];
        foreach (Person2group2roleP2g2rQuery::create()
            ->withColumn('COUNT(*)', 'cnt')
            ->select(['GroupId', 'cnt'])
            ->groupByGroupId()
            ->find() as $row) {
            $memberCounts[(int) $row['GroupId']] = (int) $row['cnt'];
        }

        $result = [];
        foreach ($groups as $g) {
            $data = $g->toArray();
            $data['groupType'] = $typeNames[(int) $g->getType()] ?? '';
            $data['memberCount'] = $memberCounts[(int) $g->getId()] ?? 0;
            $data['roles'] = $rolesByListId[(int) $g->getRoleListId()] ?? [];
            $result[] = $data;
        }

        return SlimUtils::renderJSON($response, $result);
    });

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
            $request->getAttribute('group')->toArray()
        )
    )->add(GroupMiddleware::class);

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
            ['isInCart' => $request->getAttribute('group')->checkAgainstCart()]
        )
    )->add(GroupMiddleware::class);

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
        $group = $request->getAttribute('group');
        $roles = ListOptionQuery::create()->filterById($group->getRoleListId())->find();
        return SlimUtils::renderJSON($response, $roles->toArray());
    })->add(GroupMiddleware::class);

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/phones",
     *     summary="Get cell phone numbers for group members (respects Do Not SMS)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Phone contact info for the group",
     *         @OA\JsonContent(
     *             @OA\Property(property="phones", type="array", @OA\Items(type="string"), description="Raw phone numbers"),
     *             @OA\Property(property="displayList", type="string", description="Comma-separated display string")
     *         )
     *     )
     * )
     */
    $group->get('/{groupID:[0-9]+}/phones', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = (int) $args['groupID'];

            $doNotSmsSet = _getExcludedPersonIdSet('iDoNotSmsPropertyId');

            $memberships = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($groupID)
                ->innerJoinWithPerson()
                ->find();

            $group = $request->getAttribute('group');
            $roleNameMap = [];
            foreach (ListOptionQuery::create()->filterById($group->getRoleListId())->find() as $opt) {
                $roleNameMap[(int) $opt->getOptionId()] = $opt->getOptionName();
            }

            $phonesSeen = [];
            $phones     = [];
            $rolePhones = [];
            foreach ($memberships as $membership) {
                $person = $membership->getPerson();
                if ($person === null) {
                    continue;
                }
                $personId = (int) $person->getId();
                if (isset($doNotSmsSet[$personId])) {
                    continue;
                }
                $phone = (string) $person->getCellPhone();
                if (empty($phone) || isset($phonesSeen[$phone])) {
                    continue;
                }
                $phonesSeen[$phone] = true;
                $phones[]           = $phone;
                $roleName = $roleNameMap[(int) $membership->getRoleId()] ?? gettext('Member');
                $rolePhones[$roleName][] = $phone;
            }

            $roles = [];
            foreach ($rolePhones as $name => $rPhones) {
                $roles[$name] = _buildPhoneResponse($rPhones);
            }

            return SlimUtils::renderJSON($response, array_merge(
                _buildPhoneResponse($phones),
                ['roles' => $roles],
            ));
        } catch (\Exception $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve phone numbers'), [], 500, $e, $request);
        }
    })->add(GroupMiddleware::class);

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/sundayschool/phones",
     *     summary="Get cell phone numbers for a Sunday School class, segmented by role",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Phone contact info segmented by role (all/teachers/parents)",
     *         @OA\JsonContent(
     *             @OA\Property(property="all",      type="object"),
     *             @OA\Property(property="teachers", type="object"),
     *             @OA\Property(property="students", type="object"),
     *             @OA\Property(property="parents",  type="object")
     *         )
     *     )
     * )
     */
    $group->get('/{groupID:[0-9]+}/sundayschool/phones', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID             = (int) $args['groupID'];
            $sundaySchoolService = new SundaySchoolService();

            $rsTeachers        = $sundaySchoolService->getClassByRole($groupID, 'Teacher');
            $thisClassChildren = $sundaySchoolService->getKidsFullDetails($groupID);

            $doNotSmsSet = _getExcludedPersonIdSet('iDoNotSmsPropertyId');

            $teacherPhones = [];
            $parentPhones  = [];
            $studentPhones = [];
            $teacherPhonesSeen = [];
            $studentPhonesSeen = [];
            $parentPhonesSeen  = [];

            foreach ($rsTeachers as $teacher) {
                if (isset($doNotSmsSet[(int) $teacher->getId()])) {
                    continue;
                }
                $phone = (string) $teacher->getCellPhone();
                if (!empty($phone) && !isset($teacherPhonesSeen[$phone])) {
                    $teacherPhonesSeen[$phone] = true;
                    $teacherPhones[]           = $phone;
                }
            }
            foreach ($thisClassChildren as $child) {
                // Student's own cell phone
                $kidId = (int) ($child['kidId'] ?? 0);
                if ($kidId > 0 && !isset($doNotSmsSet[$kidId])) {
                    $studentPhone = (string) ($child['mobilePhone'] ?? '');
                    if (!empty($studentPhone) && !isset($studentPhonesSeen[$studentPhone])) {
                        $studentPhonesSeen[$studentPhone] = true;
                        $studentPhones[]                  = $studentPhone;
                    }
                }
                // Parent phones
                foreach (['dadId' => 'dadCellPhone', 'momId' => 'momCellPhone'] as $idField => $phoneField) {
                    $parentId = (int) ($child[$idField] ?? 0);
                    if ($parentId > 0 && isset($doNotSmsSet[$parentId])) {
                        continue;
                    }
                    $phone = (string) ($child[$phoneField] ?? '');
                    if (!empty($phone) && !isset($parentPhonesSeen[$phone])) {
                        $parentPhonesSeen[$phone] = true;
                        $parentPhones[]           = $phone;
                    }
                }
            }

            // Merge and dedup across segments for 'all'
            $allPhones = array_values(array_unique(array_merge($teacherPhones, $studentPhones, $parentPhones)));

            return SlimUtils::renderJSON($response, [
                'all'      => _buildPhoneResponse($allPhones),
                'teachers' => _buildPhoneResponse($teacherPhones),
                'students' => _buildPhoneResponse($studentPhones),
                'parents'  => _buildPhoneResponse($parentPhones),
            ]);
        } catch (\Exception $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve phone numbers'), [], 500, $e, $request);
        }
    })->add(GroupMiddleware::class);

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/emails",
     *     summary="Get email addresses for group members (respects Do Not Email)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Email contact info for the group")
     * )
     */
    $group->get('/{groupID:[0-9]+}/emails', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = (int) $args['groupID'];
            $doNotEmailSet = _getExcludedPersonIdSet('iDoNotEmailPropertyId');

            $memberships = Person2group2roleP2g2rQuery::create()
                ->filterByGroupId($groupID)
                ->innerJoinWithPerson()
                ->find();

            $group = $request->getAttribute('group');
            $roleNameMap = [];
            foreach (ListOptionQuery::create()->filterById($group->getRoleListId())->find() as $opt) {
                $roleNameMap[(int) $opt->getOptionId()] = $opt->getOptionName();
            }

            $allEmails = [];
            $roleEmails = [];
            foreach ($memberships as $membership) {
                $person = $membership->getPerson();
                if ($person === null) {
                    continue;
                }
                if (isset($doNotEmailSet[(int) $person->getId()])) {
                    continue;
                }
                $email = (string) $person->getEmail();
                if (empty($email) || isset($allEmails[$email])) {
                    continue;
                }
                $allEmails[$email] = true;
                $roleName = $roleNameMap[(int) $membership->getRoleId()] ?? gettext('Member');
                $roleEmails[$roleName][] = $email;
            }

            $systemEmail = (string) SystemConfig::getValue('sToEmailAddress');
            $allList = array_keys($allEmails);
            if (!empty($systemEmail) && !isset($allEmails[$systemEmail])) {
                $allList[] = $systemEmail;
            }

            $roles = [];
            foreach ($roleEmails as $name => $emails) {
                $roles[$name] = implode(',', $emails);
            }

            return SlimUtils::renderJSON($response, [
                'all'       => implode(',', $allList),
                'roles'     => $roles,
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve email addresses'), [], 500, $e, $request);
        }
    })->add(GroupMiddleware::class);

    /**
     * @OA\Get(
     *     path="/groups/{groupID}/sundayschool/emails",
     *     summary="Get email addresses for a Sunday School class, segmented by role",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Email contact info segmented by role (teachers/parents/kids)")
     * )
     */
    $group->get('/{groupID:[0-9]+}/sundayschool/emails', function (Request $request, Response $response, array $args): Response {
        try {
            $groupID = (int) $args['groupID'];
            $doNotEmailSet = _getExcludedPersonIdSet('iDoNotEmailPropertyId');
            $sundaySchoolService = new SundaySchoolService();

            $rsTeachers = $sundaySchoolService->getClassByRole($groupID, 'Teacher');
            $thisClassChildren = $sundaySchoolService->getKidsFullDetails($groupID);

            $teacherEmails = [];
            $parentEmails = [];
            $kidEmails = [];
            $teacherEmailsSeen = [];
            $kidEmailsSeen     = [];
            $parentEmailsSeen  = [];

            foreach ($rsTeachers as $teacher) {
                if (isset($doNotEmailSet[(int) $teacher->getId()])) {
                    continue;
                }
                $email = (string) $teacher->getEmail();
                if (!empty($email) && !isset($teacherEmailsSeen[$email])) {
                    $teacherEmailsSeen[$email] = true;
                    $teacherEmails[] = $email;
                }
            }

            foreach ($thisClassChildren as $child) {
                $kidId = (int) ($child['kidId'] ?? 0);
                if ($kidId > 0 && !isset($doNotEmailSet[$kidId])) {
                    $kidEmail = (string) ($child['kidEmail'] ?? '');
                    if (!empty($kidEmail) && !isset($kidEmailsSeen[$kidEmail])) {
                        $kidEmailsSeen[$kidEmail] = true;
                        $kidEmails[] = $kidEmail;
                    }
                }

                foreach (['dadId' => 'dadEmail', 'momId' => 'momEmail'] as $idField => $emailField) {
                    $parentId = (int) ($child[$idField] ?? 0);
                    if ($parentId > 0 && isset($doNotEmailSet[$parentId])) {
                        continue;
                    }
                    $email = (string) ($child[$emailField] ?? '');
                    if (!empty($email) && !isset($parentEmailsSeen[$email])) {
                        $parentEmailsSeen[$email] = true;
                        $parentEmails[] = $email;
                    }
                }
            }

            // Merge and dedup across segments for 'all'
            $allEmails = array_values(array_unique(array_merge($teacherEmails, $parentEmails, $kidEmails)));

            return SlimUtils::renderJSON($response, [
                'all'      => implode(',', $allEmails),
                'teachers' => implode(',', $teacherEmails),
                'parents'  => implode(',', $parentEmails),
                'kids'     => implode(',', $kidEmails),
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to retrieve email addresses'), [], 500, $e, $request);
        }
    })->add(GroupMiddleware::class);

    /**
     * @OA\Get(
     *     path="/groups/sundayschool/export/email",
     *     summary="Export people emails with group memberships as CSV",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="CSV file with people emails and group role memberships"),
     *     @OA\Response(response=403, description="ManageGroups role required")
     * )
     */
    $group->get('/sundayschool/export/email', function (Request $request, Response $response): Response {
        $personService = new PersonService();
        $sundaySchoolService = new SundaySchoolService();

        $groups = GroupQuery::create()
            ->filterByActive(true)
            ->filterByIncludeInEmailExport(true)
            ->find();

        // Build header columns
        $headers = ['CRM ID', 'FirstName', 'LastName', 'Email'];
        foreach ($groups as $g) {
            $headers[] = $g->getName();
        }

        // Collect Sunday School parent IDs per group
        $sundaySchoolsParents = [];
        foreach ($groups as $g) {
            if ($g->isSundaySchool()) {
                $kids = $sundaySchoolService->getKidsFullDetails($g->getId());
                $parentIds = [];
                foreach ($kids as $kid) {
                    if ($kid['dadId'] !== null) {
                        $parentIds[] = (int) $kid['dadId'];
                    }
                    if ($kid['momId'] !== null) {
                        $parentIds[] = (int) $kid['momId'];
                    }
                }
                $sundaySchoolsParents[$g->getId()] = $parentIds;
            }
        }

        $dateFormat = SystemConfig::getValue('sDateFilenameFormat');
        $filename = 'EmailExport-' . date($dateFormat) . '.csv';

        $exporter = new CsvExporter();
        $exporter->insertHeaders($headers);

        foreach ($personService->getPeopleEmailsAndGroups() as $person) {
            $row = [
                $person['id'],
                $person['firstName'],
                $person['lastName'],
                $person['email'],
            ];

            foreach ($groups as $g) {
                $groupRole = $person[$g->getName()] ?? '';
                if ($groupRole === '' && $g->isSundaySchool()) {
                    if (in_array((int) $person['id'], $sundaySchoolsParents[$g->getId()], true)) {
                        $groupRole = 'Parent';
                    }
                }
                $row[] = $groupRole;
            }
            $exporter->insertRow($row);
        }

        $response = $response->withHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->getBody()->write($exporter->getContent());

        return $response;
    })->add(ManageGroupRoleAuthMiddleware::class);

    /**
     * @OA\Get(
     *     path="/groups/sundayschool/export/classlist",
     *     summary="Export Sunday School class roster as CSV",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="CSV file with class members, parents, and contact info"),
     *     @OA\Response(response=403, description="ManageGroups role required or Sunday School disabled")
     * )
     */
    $group->get('/sundayschool/export/classlist', function (Request $request, Response $response): Response {
        $headers = [
            'Class', 'Role', 'First Name', 'Last Name', 'Birth Date',
            'Mobile', 'Home Phone', 'Home Address',
            'Dad Name', 'Dad Mobile', 'Dad Email',
            'Mom Name', 'Mom Mobile', 'Mom Email',
            'Properties',
        ];

        $rows = [];
        $groups = GroupQuery::create()
            ->orderByName(Criteria::ASC)
            ->filterByType(4)
            ->find();

        foreach ($groups as $g) {
            $groupRoleMemberships = Person2group2roleP2g2rQuery::create()
                ->joinWithPerson()
                ->orderBy(PersonTableMap::COL_PER_LASTNAME)
                ->_and()->orderBy(PersonTableMap::COL_PER_FIRSTNAME)
                ->findByGroupId($g->getId());

            foreach ($groupRoleMemberships as $membership) {
                $groupRole = ListOptionQuery::create()
                    ->filterById($g->getRoleListId())
                    ->filterByOptionId($membership->getRoleId())
                    ->findOne();

                $roleName = $groupRole ? $groupRole->getOptionName() : '';
                $member = $membership->getPerson();
                $family = $member->getFamily();

                $address = '';
                $dadName = $dadCell = $dadEmail = '';
                $momName = $momCell = $momEmail = '';

                if ($family !== null) {
                    $address = trim($family->getAddress1() . ' ' . $family->getAddress2()
                        . ' ' . $family->getCity() . ' ' . $family->getState() . ' ' . $family->getZip());

                    if ($roleName === 'Student') {
                        foreach ($family->getAdults() as $adult) {
                            if ($adult->getGender() === 1) {
                                $dadName = $adult->getFirstName() . ' ' . $adult->getLastName();
                                $dadCell = $adult->getCellPhone();
                                $dadEmail = $adult->getEmail();
                            } elseif ($adult->getGender() === 2) {
                                $momName = $adult->getFirstName() . ' ' . $adult->getLastName();
                                $momCell = $adult->getCellPhone();
                                $momEmail = $adult->getEmail();
                            }
                        }
                    }
                }

                $props = '';
                if ($roleName === 'Student') {
                    $assignedProperties = $member->getProperties();
                    if (!empty($assignedProperties)) {
                        $propNames = [];
                        foreach ($assignedProperties as $property) {
                            $propNames[] = $property->getProperty()->getProName();
                        }
                        $props = implode(', ', $propNames);
                    }
                }

                $birthDate = '';
                $birthYear = $member->getBirthYear();
                $birthMonth = $member->getBirthMonth();
                $birthDay = $member->getBirthDay();
                if ($birthYear !== null && $birthYear !== '' && (!$member->hideAge() || $roleName === 'Student')) {
                    $date = \DateTime::createFromFormat('Y-m-d', $birthYear . '-' . $birthMonth . '-' . $birthDay);
                    if ($date !== false) {
                        $birthDate = $date->format(SystemConfig::getValue('sDateFormatLong'));
                    }
                }

                $rows[] = [
                    $g->getName(),
                    $roleName,
                    $member->getFirstName(),
                    $member->getLastName(),
                    $birthDate,
                    $member->getCellPhone(),
                    $member->getHomePhone(),
                    $address,
                    $dadName,
                    $dadCell,
                    $dadEmail,
                    $momName,
                    $momCell,
                    $momEmail,
                    $props,
                ];
            }
        }

        $dateFormat = SystemConfig::getValue('sDateFilenameFormat');
        $filename = 'SundaySchool-' . date($dateFormat) . '.csv';

        $exporter = new CsvExporter();
        $exporter->insertHeaders($headers);
        $exporter->insertRows($rows);

        $response = $response->withHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->getBody()->write($exporter->getContent());

        return $response;
    })->add(new SundaySchoolEnabledMiddleware())->add(ManageGroupRoleAuthMiddleware::class);
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
        $group->setName($groupSettings['groupName']);
        $group->setDescription($groupSettings['description'] ?? '');
        // Only set the explicit group type if it was provided in the request.
        // This prevents overwriting types set by helper methods like makeSundaySchool().
        if (isset($groupSettings['groupType'])) {
            $group->setType($groupSettings['groupType']);
        }
        $group->save();
        return SlimUtils::renderJSON($response, $group->toArray());
    })->add(new InputSanitizationMiddleware(['groupName' => 'text', 'description' => 'text']));

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
        $input = $request->getParsedBody();
        $group = $request->getAttribute('group');
        $group->setName($input['groupName']);
        $group->setType($input['groupType']);
        $group->setDescription($input['description'] ?? '');
        $group->save();
        return SlimUtils::renderJSON($response, $group->toArray());
    })->add(GroupMiddleware::class)
      ->add(new InputSanitizationMiddleware(['groupName' => 'text', 'description' => 'text']));

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
        $request->getAttribute('group')->delete();
        return SlimUtils::renderSuccessJSON($response);
    })->add(GroupMiddleware::class);

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
        $person = $request->getAttribute('person');
        $group = $request->getAttribute('group');
        $groupRoleMemberships = $group->getPerson2group2roleP2g2rs();
        foreach ($groupRoleMemberships as $groupRoleMembership) {
            if ($groupRoleMembership->getPersonId() == $person->getId()) {
                $groupRoleMembership->delete();
                HookManager::doAction(Hooks::GROUP_MEMBER_REMOVED, $person->getId(), $group);
                $note = new Note();
                $note->setText(gettext('Deleted from group') . ': ' . $group->getName());
                $note->setType('group');
                $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
                $note->setPerId($person->getId());
                $note->save();
            }
        }
        return SlimUtils::renderSuccessJSON($response);
    })->add(new PersonMiddleware('userID'))->add(GroupMiddleware::class);

    /**
     * @OA\Post(
     *     path="/groups/{groupID}/addperson/{userID}",
     *     summary="Add a person to a group (ManageGroupRole role required)",
     *     tags={"Groups"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="groupID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="userID", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=false,
     *         @OA\JsonContent(
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
        $person = $request->getAttribute('person');
        $input = $request->getParsedBody() ?? [];
        $group = $request->getAttribute('group');

        $roleID = $input['RoleID'] ?? $group->getDefaultRole();

        $groupService = new GroupService();
        $groupService->addUserToGroup($groupID, $userID, $roleID);

        $membership = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId((int) $groupID)
            ->filterByPersonId((int) $userID)
            ->findOne();
        HookManager::doAction(Hooks::GROUP_MEMBER_ADDED, $membership, $group, $person);

        $note = new Note();
        $note->setText(gettext('Added to group') . ': ' . $group->getName());
        $note->setType('group');
        $note->setEntered(AuthenticationManager::getCurrentUser()->getId());
        $note->setPerId($person->getId());
        $note->save();
        $members = Person2group2roleP2g2rQuery::create()
            ->joinWithPerson()
            ->filterByPersonId((int) $userID)
            ->findByGroupId($groupID);
        return SlimUtils::renderJSON($response, $members->toArray());
    })->add(new PersonMiddleware('userID'))->add(GroupMiddleware::class);

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
     *     @OA\Response(response=403, description="ManageGroupRole role required"),
     *     @OA\Response(response=404, description="Membership not found")
     * )
     */
    $group->post('/{groupID:[0-9]+}/userRole/{userID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $groupID = (int) $args['groupID'];
        $userID = (int) $args['userID'];
        $roleID = (int) ($request->getParsedBody()['roleID'] ?? 0);
        $membership = Person2group2roleP2g2rQuery::create()->filterByGroupId($groupID)->filterByPersonId($userID)->findOne();
        if ($membership === null) {
            throw new HttpNotFoundException($request, gettext('Membership not found'));
        }
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
     *     @OA\Response(response=404, description="Group role not found"),
     *     @OA\Response(response=500, description="Failed to update role")
     * )
     */
    $group->post('/{groupID:[0-9]+}/roles/{roleID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        try {
            $roleID = (int) $args['roleID'];
            $input = $request->getParsedBody();
            $group = $request->getAttribute('group');
            if (isset($input['groupRoleName'])) {
                $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
                if ($groupRole === null) {
                    throw new HttpNotFoundException($request, gettext('Group role not found'));
                }
                $groupRole->setOptionName($input['groupRoleName']);
                $groupRole->save();

                return SlimUtils::renderJSON($response, $groupRole->toArray());
            } elseif (isset($input['groupRoleOrder'])) {
                $groupRole = ListOptionQuery::create()->filterById($group->getRoleListId())->filterByOptionId($roleID)->findOne();
                if ($groupRole === null) {
                    throw new HttpNotFoundException($request, gettext('Group role not found'));
                }
                $groupRole->setOptionSequence($input['groupRoleOrder']);
                $groupRole->save();

                return SlimUtils::renderSuccessJSON($response);
            }
            throw new \Exception(gettext('invalid group request'));
        } catch (\Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return SlimUtils::renderErrorJSON($response, gettext('Failed to update role. Please try again.'), [], $status, $e, $request);
        }
    })->add(GroupMiddleware::class)
      ->add(new InputSanitizationMiddleware(['groupRoleName' => 'text']));

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
    })->add(GroupMiddleware::class)
      ->add(new InputSanitizationMiddleware(['roleName' => 'text']));

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
        $roleID = $request->getParsedBody()['roleID'];
        $group = $request->getAttribute('group');
        $group->setDefaultRole($roleID);
        $group->save();
        return SlimUtils::renderSuccessJSON($response);
    })->add(GroupMiddleware::class);

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
        $flag = $args['value'];
        if ($flag === 'true' || $flag === 'false') {
            $group = $request->getAttribute('group');
            $group->setActive(filter_var($flag, FILTER_VALIDATE_BOOLEAN));
            $group->save();
            return SlimUtils::renderSuccessJSON($response);
        } else {
            throw new \Exception(gettext('invalid status value'));
        }
    })->add(GroupMiddleware::class);

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
        $flag = $args['value'];
        if ($flag === 'true' || $flag === 'false') {
            $group = $request->getAttribute('group');
            $group->setIncludeInEmailExport(filter_var($flag, FILTER_VALIDATE_BOOLEAN));
            $group->save();
            return SlimUtils::renderSuccessJSON($response);
        } else {
            throw new \Exception(gettext('invalid export value'));
        }
    })->add(GroupMiddleware::class);
})->add(ManageGroupRoleAuthMiddleware::class);
