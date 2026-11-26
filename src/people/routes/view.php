<?php

require_once __DIR__ . '/../../Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualification;
use ChurchCRM\Portal\Auth\LoginAsPerson;
use ChurchCRM\Service\CustomFieldService;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Service\LoginAsPersonHistoryService;
use ChurchCRM\Service\PersonPortalAccessService;
use ChurchCRM\Service\PersonSignInService;
use ChurchCRM\Service\PortalGrantService;
use ChurchCRM\Service\PropertyService;
use ChurchCRM\Volunteer\Service\VolunteerAssignmentService;
use ChurchCRM\Volunteer\Service\VolunteerScheduleService;
use ChurchCRM\Volunteer\Service\VolunteerQualificationService;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Group-specific fields shown on the profile. Definitions live on groupprop_master.
 * Values live on groupprop_{id}, a table created per group, so that read stays SQL.
 *
 * @return list<array{name: string, value: string}>
 */
function personViewGroupSpecialProps(int $groupId, int $personId): array
{
    $definitions = GroupPropMasterQuery::create()
        ->filterByGrpId($groupId)
        ->filterByPersonDisplay('true')
        ->orderByPropId()
        ->find();
    if (count($definitions) === 0) {
        return [];
    }

    $statement = Propel::getConnection()->prepare(
        'SELECT * FROM `groupprop_' . $groupId . '` WHERE per_ID = :personId'
    );
    $statement->bindValue(':personId', $personId, \PDO::PARAM_INT);
    $statement->execute();
    $values = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

    $props = [];
    foreach ($definitions as $definition) {
        $current = trim((string) ($values[$definition->getField()] ?? ''));
        if ($current === '') {
            continue;
        }
        $special = (int) $definition->getTypeId() === 11 ? null : $definition->getSpecial();
        $props[] = [
            'name' => (string) $definition->getName(),
            'value' => CustomFieldUtils::display($definition->getTypeId(), $current, $special),
        ];
    }

    return $props;
}

// ─── GET: main person view ───────────────────────────────────────────────────
$app->get('/view/{personID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $iPersonID   = (int) $args['personID'];
    $currentUser = AuthenticationManager::getCurrentUser();

    $person = PersonQuery::create()->findPk($iPersonID);
    if (empty($person)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/person/not-found?id=' . $iPersonID);
    }

    // GHSA-fcw7-mmfh-7vjm: Prevent IDOR - verify user has permission to view this person
    if (!$currentUser->canReadPerson($iPersonID)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/access-denied?role=PersonView');
    }

    // ── Page header ─────────────────────────────────────────────────────────
    $sPageTitle    = InputUtils::escapeHTML($person->getFullName());
    $sPageSubtitle = gettext('Person Profile') . ' — ID: ' . $person->getId();

    $breadcrumbItems = [[gettext('People'), '/people/dashboard']];
    if ($person->getFamId() !== '' && $person->getFamily() !== null) {
        $breadcrumbItems[] = [InputUtils::escapeHTML($person->getFamily()->getName()), '/people/family/' . $person->getFamId()];
    }
    $breadcrumbItems[] = [InputUtils::escapeHTML($person->getFirstName() . ' ' . $person->getLastName())];
    $aBreadcrumbs = PageHeader::breadcrumbs($breadcrumbItems);

    $headerButtons = [];
    if ($currentUser->isAdmin()) {
        if (!$person->isUser()) {
            $headerButtons[] = ['label' => gettext('Make User'), 'url' => '/admin/system/users/new?personId=' . $iPersonID, 'icon' => 'fa-person-chalkboard'];
        } else {
            $headerButtons[] = ['label' => gettext('Edit User'), 'url' => '/admin/system/users/' . $iPersonID . '/edit', 'icon' => 'fa-user-secret'];
            $headerButtons[] = ['label' => gettext('View User'), 'url' => '/admin/system/users/' . $iPersonID, 'icon' => 'fa-eye'];
        }
    } elseif ($person->isUser() && $person->getId() === $currentUser->getId()) {
        $headerButtons[] = ['label' => gettext('View User'), 'url' => '/account', 'icon' => 'fa-eye', 'adminOnly' => false];
    }
    $sPageHeaderButtons = PageHeader::buttons($headerButtons);

    // Filter custom fields by field-level permissions (GHSA-p6xx-xx98-f323)
    $visibleCustomFields = $person->getVisibleCustomFieldDefinitions();
    $aCustomData = (new CustomFieldService())->getPersonValues($iPersonID);

    $typeNames = [];
    foreach (ListOptionQuery::create()->filterById(3)->find() as $typeOption) {
        $typeNames[(int) $typeOption->getOptionId()] = $typeOption->getOptionName();
    }

    $memberships = Person2group2roleP2g2rQuery::create()
        ->filterByPersonId($iPersonID)
        ->joinWithGroup()
        ->find();

    $roleListIds = [];
    foreach ($memberships as $membership) {
        $membershipGroup = $membership->getGroup();
        if ($membershipGroup !== null) {
            $roleListIds[] = (int) $membershipGroup->getRoleListId();
        }
    }
    $roleNames = [];
    if ($roleListIds !== []) {
        foreach (ListOptionQuery::create()->filterById(array_values(array_unique($roleListIds)))->find() as $roleOption) {
            $roleNames[(int) $roleOption->getId() . ':' . (int) $roleOption->getOptionId()] = $roleOption->getOptionName();
        }
    }

    $assignedGroupsData = [];
    foreach ($memberships as $membership) {
        $membershipGroup = $membership->getGroup();
        if ($membershipGroup === null) {
            continue;
        }
        $typeId = (int) $membershipGroup->getType();
        $groupId = (int) $membershipGroup->getId();
        $assignedGroupsData[] = [
            'grp_ID' => $groupId,
            'grp_Name' => $membershipGroup->getName(),
            'grp_Type' => $typeId,
            'grp_hasSpecialProps' => (bool) $membershipGroup->getHasSpecialProps(),
            'roleId' => (int) $membership->getRoleId(),
            'roleName' => $roleNames[(int) $membershipGroup->getRoleListId() . ':' . (int) $membership->getRoleId()] ?? '',
            'groupTypeName' => $typeNames[$typeId] ?? gettext('Unassigned'),
            'viewUri' => Group::getViewURIForId($groupId, $typeId === 4),
            'specialProps' => $membershipGroup->getHasSpecialProps()
                ? personViewGroupSpecialProps($groupId, $iPersonID)
                : [],
        ];
    }
    usort(
        $assignedGroupsData,
        static function (array $a, array $b): int {
            $byType = strcasecmp((string) $a['groupTypeName'], (string) $b['groupTypeName']);

            return $byType !== 0 ? $byType : strcasecmp((string) $a['grp_Name'], (string) $b['grp_Name']);
        }
    );

    // ── Volunteer pane ───────────────────────────────────────────────────────
    // What this person is qualified for and what they are committed to next,
    // read-only, linking into /volunteer. Gated by the permission that opened
    // the record, not scoped to the viewer's ministries.
    $volunteerQualifications = [];
    $volunteerAssignments = [];
    $qualificationService = new VolunteerQualificationService();
    $assignmentService = new VolunteerAssignmentService();
    $scheduleService = new VolunteerScheduleService();

    $positionNames = [];
    $positionMinistryIds = [];
    $qualificationRows = array_values(array_filter(
        $qualificationService->listQualificationsForPerson($iPersonID),
        static fn (VolunteerQualification $row): bool => (bool) $row->getActive()
    ));

    $positionIds = array_values(array_unique(array_map(
        static fn (VolunteerQualification $row): int => (int) $row->getPositionId(),
        $qualificationRows
    )));
    if ($positionIds !== []) {
        foreach (VolunteerPositionQuery::create()->filterById($positionIds, Criteria::IN)->find() as $position) {
            $positionNames[(int) $position->getId()] = $position->getName();
            $positionMinistryIds[(int) $position->getId()] = (int) $position->getMinistryId();
        }
    }

    $upcoming = $assignmentService->listAssignmentsForPerson($iPersonID);
    $occurrenceIds = array_values(array_unique(array_map(
        static fn ($assignment): int => (int) $assignment->getOccurrenceId(),
        $upcoming
    )));

    $occurrences = [];
    $schedules = [];
    if ($occurrenceIds !== []) {
        foreach (VolunteerOccurrenceQuery::create()->filterById($occurrenceIds, Criteria::IN)->find() as $occurrence) {
            $occurrences[(int) $occurrence->getId()] = $occurrence;
        }
        $scheduleIds = array_values(array_unique(array_map(
            static fn ($occurrence): int => (int) $occurrence->getScheduleId(),
            $occurrences
        )));
        if ($scheduleIds !== []) {
            foreach (VolunteerScheduleQuery::create()->filterById($scheduleIds, Criteria::IN)->find() as $schedule) {
                $schedules[(int) $schedule->getId()] = $schedule;
            }
        }
    }

    $ministryIds = array_values(array_unique(array_merge(
        array_values($positionMinistryIds),
        array_map(static fn ($schedule): int => (int) $schedule->getMinistryId(), $schedules)
    )));
    $ministryNames = [];
    if ($ministryIds !== []) {
        foreach (VolunteerMinistryQuery::create()->filterById($ministryIds, Criteria::IN)->find() as $ministry) {
            $ministryNames[(int) $ministry->getId()] = $ministry->getName();
        }
    }

    foreach ($qualificationRows as $row) {
        $positionId = (int) $row->getPositionId();
        $volunteerQualifications[] = [
            'positionId' => $positionId,
            'positionName' => $positionNames[$positionId] ?? null,
            'ministryId' => $positionMinistryIds[$positionId] ?? null,
            'ministryName' => $ministryNames[$positionMinistryIds[$positionId] ?? 0] ?? null,
            'grantedDate' => $row->getGrantedDate('Y-m-d'),
        ];
    }

    // Upcoming positions a person still holds. A cancelled or substituted-away
    // row is history, not a commitment, so it is not a "what am I doing next"
    // answer and is left out (§2.11.1).
    foreach ($upcoming as $assignment) {
        if (!in_array($assignment->getStatus(), ['pending', 'accepted'], true)) {
            continue;
        }

        $occurrence = $occurrences[(int) $assignment->getOccurrenceId()] ?? null;
        if ($occurrence === null) {
            continue;
        }

        $schedule = $schedules[(int) $occurrence->getScheduleId()] ?? null;
        // The one method allowed to decide an occurrence's time — for a linked
        // occurrence it reads the event row (D4).
        $window = $scheduleService->resolveOccurrenceWindow($occurrence);
        $positionId = (int) $assignment->getPositionId();
        $ministryId = $schedule === null ? null : (int) $schedule->getMinistryId();

        $volunteerAssignments[] = [
            'occurrenceId' => (int) $occurrence->getId(),
            'occurrenceDate' => $occurrence->getOccurrenceDate('Y-m-d'),
            'start' => $window['start'] === null ? null : $window['start']->format('Y-m-d H:i:s'),
            'positionId' => $positionId,
            'positionName' => $positionNames[$positionId]
                ?? (VolunteerPositionQuery::create()->findPk($positionId)?->getName()),
            'scheduleName' => $schedule === null ? null : $schedule->getName(),
            'ministryId' => $ministryId,
            'ministryName' => $ministryId === null ? null : ($ministryNames[$ministryId] ?? null),
            'status' => $assignment->getStatus(),
        ];
    }

    usort(
        $volunteerAssignments,
        static fn (array $a, array $b): int => ($a['occurrenceDate'] ?? '') <=> ($b['occurrenceDate'] ?? '')
    );

    // ── Properties (ORM via PropertyService) ─
    $assignedPersonProperties = PropertyService::getAssigned($person);
    $allPersonProperties      = PropertyService::getAll($person);

    // ── Computed display values ──────────────────────────────────────────────
    $dBirthDate              = $person->getFormattedBirthDate();
    $plaintextMailingAddress = $person->getAddress();
    $formattedMailingAddress = $person->getAddress();

    $sHomePhone           = $person->getHomePhone() ?? '';
    $sHomePhoneUnformatted = $sHomePhone;
    $sWorkPhone           = $person->getWorkPhone() ?? '';
    $sWorkPhoneUnformatted = $sWorkPhone;
    $sCellPhone           = $person->getCellPhone() ?? '';
    $sCellPhoneUnformatted = $sCellPhone;
    $sEmail               = $person->getEmail() ?? '';
    $sUnformattedEmail    = $sEmail;

    $per_Envelope = $person->getEnvelope() ?? 0;
    $sEnvelope    = ($per_Envelope > 0) ? $per_Envelope : gettext('Not assigned');

    $family = $person->getFamily();
    $fam_ID  = $family !== null ? $family->getId() : '';
    $fam_Name = $family !== null ? ($family->getName() ?? '') : '';

    // Edit permission
    $bOkToEdit = $currentUser->isEditRecordsEnabled();

    // ── Map configuration ────────────────────────────────────────────────────
    $personMapConfig = null;
    $famLat          = $family !== null ? (float) $family->getLatitude() : 0.0;
    $famLng          = $family !== null ? (float) $family->getLongitude() : 0.0;
    $familyHasCoords = $family !== null && $famLat !== 0.0 && $famLng !== 0.0;
    if ($familyHasCoords) {
        $personMapConfig = ['lat' => $famLat, 'lng' => $famLng];
    } elseif ($family === null && !empty($person->getAddress1()) && !SystemConfig::getBooleanValue('bHidePersonAddress')) {
        $personMapConfig = ['address' => $plaintextMailingAddress];
    }

    // ── Timeline ─────────────────────────────────────────────────────────────
    $timelineService = new TimelineService();
    $personTimeline  = $timelineService->getForPerson($iPersonID);

    // ── Portal sign-in card (member sign-in design §11.3) ───────────────────
    $portalSignIn = $currentUser->isEditRecordsEnabled()
        ? (new PersonSignInService())->statusFor($person, $currentUser->isAdmin())
        : null;
    // Its devices, passkeys and portal permissions: administrators only (§11.3, §8.6)
    $portalAccess = $portalSignIn !== null && $currentUser->isAdmin() ? (new PersonPortalAccessService())->forPerson($person) : null;
    $portalGrantChoices = $portalAccess === null ? [] : PortalGrantService::givableCapabilitiesFor($iPersonID);

    // ── Login as Person (§6.5, §11.3): administrators only ───────────────────
    $loginAsPerson = null;
    $loginAsPersonHistory = null;
    if ($currentUser->isAdmin()) {
        if (LoginAsPerson::isEnabled()) {
            $loginAsPersonRefusal = LoginAsPerson::refusalFor($person);
            $loginAsPerson = [
                'refusal' => $loginAsPersonRefusal === null ? null : LoginAsPerson::refusalMessage($loginAsPersonRefusal),
                'label' => LoginAsPerson::menuLabel($person),
                'csrfToken' => CSRFUtils::generateToken(),
            ];
        }
        $loginAsPersonHistory = (new LoginAsPersonHistoryService())->forPerson($iPersonID);
        if ($loginAsPersonHistory === [] && !LoginAsPerson::isEnabled()) {
            $loginAsPersonHistory = null;
        }
    }

    $convertToFamilyCsrfToken = $currentUser->isAdmin() && (int) $person->getFamId() === 0 ? CSRFUtils::generateToken() : null;

    // PageInit.php (required above) took the previous request's message, e.g. why
    // Login as Person ended, out of the session; Footer.php shows it.
    global $sGlobalMessage, $sGlobalMessageClass;

    // ── Render ───────────────────────────────────────────────────────────────
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'              => SystemURLs::getRootPath(),
        'sPageTitle'             => $sPageTitle,
        'sPageSubtitle'          => $sPageSubtitle,
        'aBreadcrumbs'           => $aBreadcrumbs,
        'sPageHeaderButtons'     => $sPageHeaderButtons,
        // Person
        'person'                 => $person,
        'iPersonID'              => $iPersonID,
        'fam_ID'                 => $fam_ID,
        'fam_Name'               => $fam_Name,
        // Computed display values
        'dBirthDate'             => $dBirthDate,
        'sEnvelope'              => $sEnvelope,
        'sHomePhone'             => $sHomePhone,
        'sHomePhoneUnformatted'  => $sHomePhoneUnformatted,
        'sWorkPhone'             => $sWorkPhone,
        'sWorkPhoneUnformatted'  => $sWorkPhoneUnformatted,
        'sCellPhone'             => $sCellPhone,
        'sCellPhoneUnformatted'  => $sCellPhoneUnformatted,
        'sEmail'                 => $sEmail,
        'sUnformattedEmail'      => $sUnformattedEmail,
        'plaintextMailingAddress' => $plaintextMailingAddress,
        'formattedMailingAddress' => $formattedMailingAddress,
        'bOkToEdit'              => $bOkToEdit,
        // Custom fields (filtered by user's field-level permissions)
        'customFieldsMaster'     => $visibleCustomFields,
        'aCustomData'            => $aCustomData,
        // Groups
        'assignedGroupsData'     => $assignedGroupsData,
        // Volunteer pane: positions the person is qualified for and what they serve next
        'volunteerQualifications'   => $volunteerQualifications,
        'volunteerAssignments'      => $volunteerAssignments,
        // Properties (ORM)
        'assignedPersonProperties'  => $assignedPersonProperties,
        'allPersonProperties'       => $allPersonProperties,
        // Map & Timeline
        'personMapConfig'        => $personMapConfig,
        'familyHasCoords'        => $familyHasCoords,
        'personTimeline'         => $personTimeline,
        // Email history URL used by the person timeline
        'emailHistoryUrl'        => SystemURLs::getRootPath() . '/people/view/' . (int) $iPersonID . '/emails',
        // Portal sign-in card: null when the viewer lacks Edit Records
        'portalSignIn'           => $portalSignIn,
        'portalSignInCanManage'  => $currentUser->isAdmin(),
        'portalSignInIsSelf'     => $iPersonID === (int) $currentUser->getPersonId(),
        'portalSignInCsrfToken'  => $portalSignIn === null ? '' : CSRFUtils::generateToken(),
        // Login as Person: the Actions item (null while off or for non-admins) and the history card
        'loginAsPerson'          => $loginAsPerson,
        'loginAsPersonHistory'   => $loginAsPersonHistory,
        'convertToFamilyCsrfToken' => $convertToFamilyCsrfToken,
        'portalAccess'           => $portalAccess,
        'portalGrantChoices'     => $portalGrantChoices,
        'sGlobalMessage'         => $sGlobalMessage ?? '',
        'sGlobalMessageClass'    => $sGlobalMessageClass ?? 'success',
    ];

    return $renderer->render($response, 'person-view.php', $pageArgs);
});

// ─── GET: full email history for a person ───────────────────────────────────
$app->get('/view/{personID:[0-9]+}/emails', function (Request $request, Response $response, array $args): Response {
    $iPersonID   = (int) $args['personID'];
    $currentUser = AuthenticationManager::getCurrentUser();

    $person = PersonQuery::create()->findPk($iPersonID);
    if (empty($person)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/person/not-found?id=' . $iPersonID);
    }
    if (!$currentUser->canReadPerson($iPersonID)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/access-denied?role=PersonView');
    }

    $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
    $emailHistory = (new EmailLogService())->getForPerson($iPersonID, $page, EmailLogService::DEFAULT_PAGE_SIZE);

    // Home / People / Family / Person / Email History — PageHeader takes [label, url] pairs.
    $emailBreadcrumbs = [[gettext('People'), '/people/dashboard']];
    if ($person->getFamId() !== '' && $person->getFamily() !== null) {
        $emailBreadcrumbs[] = [InputUtils::escapeHTML($person->getFamily()->getName()), '/people/family/' . $person->getFamId()];
    }
    $emailBreadcrumbs[] = [InputUtils::escapeHTML($person->getFullName()), '/people/view/' . $iPersonID];
    $emailBreadcrumbs[] = [gettext('Email History')];

    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'person-emails.php', [
        'sRootPath'     => SystemURLs::getRootPath(),
        'sPageTitle'    => InputUtils::escapeHTML($person->getFullName()),
        'sPageSubtitle' => gettext('Email History'),
        'aBreadcrumbs'  => PageHeader::breadcrumbs($emailBreadcrumbs),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Back to Person'), 'url' => '/people/view/' . $iPersonID, 'icon' => 'fa-arrow-left'],
        ]),
        'person'        => $person,
        'iPersonID'     => $iPersonID,
        'emailHistory'  => $emailHistory,
    ]);
});
