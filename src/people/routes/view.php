<?php

require_once __DIR__ . '/../../Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\VolunteerPositionQuery;
use ChurchCRM\model\ChurchCRM\VolunteerQualification;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\PropertyService;
use ChurchCRM\Service\VolunteerAssignmentService;
use ChurchCRM\Service\VolunteerScheduleService;
use ChurchCRM\Service\VolunteerSetupService;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\VolunteerMinistryQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOccurrenceQuery;
use ChurchCRM\model\ChurchCRM\VolunteerScheduleQuery;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// ─── POST: assign volunteer opportunities (PRG pattern) ──────────────────────
$app->post('/view/{personID:[0-9]+}', function (Request $request, Response $response, array $args): Response {
    $iPersonID = (int) $args['personID'];
    $currentUser = AuthenticationManager::getCurrentUser();

    $person = PersonQuery::create()->findPk($iPersonID);
    if (empty($person)) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/person/not-found?id=' . $iPersonID);
    }

    if (!$currentUser->canEditPerson($iPersonID, $person->getFamId())) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/v2/access-denied?role=PersonView');
    }

    if ($currentUser->isEditRecordsEnabled()) {
        $personService = new PersonService();
        $parsedBody    = $request->getParsedBody();
        $volIDs        = is_array($parsedBody) ? ($parsedBody['VolunteerOpportunityIDs'] ?? []) : [];
        if (!empty($volIDs)) {
            foreach ($volIDs as $volID) {
                $personService->addVolunteerOpportunity($iPersonID, (int) $volID);
            }
        }
    }

    return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/view/' . $iPersonID);
});

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

    // Handle volunteer-opportunity removal (RemoveVO query param)
    $queryParams = $request->getQueryParams();
    $iRemoveVO   = (int) ($queryParams['RemoveVO'] ?? 0);
    if ($iRemoveVO > 0 && $currentUser->isEditRecordsEnabled()) {
        $personService = new PersonService();
        $personService->removeVolunteerOpportunity($iPersonID, $iRemoveVO);
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/people/view/' . $iPersonID);
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
            $headerButtons[] = ['label' => gettext('View User'), 'url' => '/v2/user/' . $iPersonID, 'icon' => 'fa-eye'];
        }
    } elseif ($person->isUser() && $person->getId() === $currentUser->getId()) {
        $headerButtons[] = ['label' => gettext('View User'), 'url' => '/v2/user/' . $iPersonID, 'icon' => 'fa-eye', 'adminOnly' => false];
    }
    $sPageHeaderButtons = PageHeader::buttons($headerButtons);

    // ── Person data (raw SQL join — legacy query) ────────────────────────────
    $sSQL = "SELECT a.*, family_fam.*, COALESCE(cls.lst_OptionName, 'Unassigned') AS sClassName, fmr.lst_OptionName AS sFamRole,
            b.per_FirstName AS EnteredFirstName, b.per_ID AS EnteredId,
            b.Per_LastName AS EnteredLastName, c.per_FirstName AS EditedFirstName,
            c.per_LastName AS EditedLastName, c.per_ID AS EditedId
        FROM person_per a
        LEFT JOIN family_fam ON a.per_fam_ID = family_fam.fam_ID
        LEFT JOIN list_lst cls ON a.per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
        LEFT JOIN list_lst fmr ON a.per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
        LEFT JOIN person_per b ON a.per_EnteredBy = b.per_ID
        LEFT JOIN person_per c ON a.per_EditedBy = c.per_ID
        WHERE a.per_ID = " . $iPersonID;
    $rsPerson   = RunQuery($sSQL);
    $personData = mysqli_fetch_array($rsPerson, MYSQLI_ASSOC);

    // ── Custom fields master (definitions) ───────────────────────────────────
    $customFieldsMaster = PersonCustomMasterQuery::create()->orderByOrder()->find();

    $sSQL       = 'SELECT * FROM person_custom WHERE per_ID = ' . $iPersonID;
    $rsCustomData = RunQuery($sSQL);
    $aCustomData  = mysqli_fetch_array($rsCustomData, MYSQLI_BOTH) ?: [];

    // ── Groups this person is assigned to ───────────────────────────────────
    $sSQL = 'SELECT grp_ID, grp_Name, grp_Type, grp_hasSpecialProps, p2g2r_rle_ID AS roleId,
            role.lst_OptionName AS roleName,
            COALESCE(grptype.lst_OptionName, \'' . gettext('Unassigned') . '\') AS groupTypeName
        FROM group_grp
        LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
        LEFT JOIN list_lst role ON role.lst_OptionID = p2g2r_rle_ID AND role.lst_ID = grp_RoleListID
        LEFT JOIN list_lst grptype ON grptype.lst_OptionID = grp_Type AND grptype.lst_ID = 3
        WHERE person2group2role_p2g2r.p2g2r_per_ID = ' . $iPersonID . '
        ORDER BY groupTypeName, grp_Name';
    $rsAssignedGroups     = RunQuery($sSQL);
    $assignedGroupsData   = [];
    while ($row = mysqli_fetch_array($rsAssignedGroups, MYSQLI_ASSOC)) {
        $assignedGroupsData[] = $row;
    }

    // ── All groups (for add-group dropdown) ─────────────────────────────────
    $sSQL = 'SELECT grp_ID, grp_Name, grp_Type, COALESCE(grptype.lst_OptionName, \'' . gettext('Unassigned') . '\') AS groupTypeName
        FROM group_grp
        LEFT JOIN list_lst grptype ON grptype.lst_OptionID = grp_Type AND grptype.lst_ID = 3
        ORDER BY groupTypeName, grp_Name';
    $rsGroups     = RunQuery($sSQL);
    $allGroupsData = [];
    while ($row = mysqli_fetch_array($rsGroups, MYSQLI_ASSOC)) {
        $allGroupsData[] = $row;
    }

    // ── Volunteer opportunities ──────────────────────────────────────────────
    // Get IDs of opportunities assigned to this person (no FK relation in
    // schema.xml, so a join isn't auto-generated — use a two-step query).
    $assignedVolOppIds = [];
    foreach (PersonVolunteerOpportunityQuery::create()->filterByPersonId($iPersonID)->find() as $pvo) {
        $assignedVolOppIds[] = $pvo->getVolunteerOpportunityId();
    }
    $assignedVolunteerOppsData = empty($assignedVolOppIds)
        ? []
        : VolunteerOpportunityQuery::create()
            ->filterById($assignedVolOppIds)
            ->orderByOrder()
            ->find();

    $allVolunteerOppsData = VolunteerOpportunityQuery::create()
        ->orderByOrder()
        ->find();

    // ── Volunteer v2 pane (#9711, design §3.5 / §3.8 surface 2) ──────────────
    //
    // What this person is qualified for and what they are committed to next,
    // read-only, linking into /volunteer. Prepared here because views run no
    // queries (groups-mvc-guidelines.md), and only when the V2 experience is
    // actually shown — `v1` mode must cost nothing.
    //
    // This is a PERSON-page read, not a coordinator read: it is about the person
    // whose record is open, gated by the same permission that opened the record,
    // and it exposes nothing a coordinator screen would not. It is deliberately
    // NOT scoped to the viewer's volunteer ministries — an administrator looking
    // at a member's record is not acting as a coordinator of anything.
    $volunteerV2Qualifications = [];
    $volunteerV2Assignments = [];

    if (User::isVolunteerV2Enabled()) {
        $setupService = new VolunteerSetupService();
        $assignmentService = new VolunteerAssignmentService();
        $scheduleService = new VolunteerScheduleService();

        $positionNames = [];
        $positionMinistryIds = [];
        $qualificationRows = array_values(array_filter(
            $setupService->listQualificationsForPerson($iPersonID),
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
            $volunteerV2Qualifications[] = [
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

            $volunteerV2Assignments[] = [
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
            $volunteerV2Assignments,
            static fn (array $a, array $b): int => ($a['occurrenceDate'] ?? '') <=> ($b['occurrenceDate'] ?? '')
        );
    }

    // ── Properties (ORM via PropertyService) ─
    $assignedPersonProperties = PropertyService::getAssigned($person);
    $allPersonProperties      = PropertyService::getAll($person);

    // ── Computed display values ──────────────────────────────────────────────
    $dBirthDate              = $person->getFormattedBirthDate();
    $plaintextMailingAddress = $person->getAddress();
    $formattedMailingAddress = $person->getAddress();

    $sHomePhone           = $personData['per_HomePhone'] ?? '';
    $sHomePhoneUnformatted = $personData['per_HomePhone'] ?? '';
    $sWorkPhone           = $personData['per_WorkPhone'] ?? '';
    $sWorkPhoneUnformatted = $personData['per_WorkPhone'] ?? '';
    $sCellPhone           = $personData['per_CellPhone'] ?? '';
    $sCellPhoneUnformatted = $personData['per_CellPhone'] ?? '';
    $sEmail               = $personData['per_Email'] ?? '';
    $sUnformattedEmail    = $personData['per_Email'] ?? '';

    $per_Envelope = $personData['per_Envelope'] ?? 0;
    $sEnvelope    = ($per_Envelope > 0) ? $per_Envelope : gettext('Not assigned');

    $fam_ID  = $personData['fam_ID'] ?? '';
    $fam_Name = $personData['fam_Name'] ?? '';

    // Edit permission
    $bOkToEdit = (
        $currentUser->isEditRecordsEnabled() ||
        ($currentUser->isEditSelfEnabled() && $iPersonID === $currentUser->getId()) ||
        ($currentUser->isEditSelfEnabled() && $fam_ID !== '' && (int)$fam_ID === (int)$currentUser->getPerson()->getFamId())
    );

    // ── Map configuration ────────────────────────────────────────────────────
    $personMapConfig = null;
    $famLat          = (float) ($personData['fam_Latitude'] ?? 0);
    $famLng          = (float) ($personData['fam_Longitude'] ?? 0);
    $familyHasCoords = ($fam_ID !== '') && $famLat !== 0.0 && $famLng !== 0.0;
    if ($familyHasCoords) {
        $personMapConfig = ['lat' => $famLat, 'lng' => $famLng];
    } elseif ($fam_ID === '' && !empty($personData['per_Address1']) && !SystemConfig::getBooleanValue('bHidePersonAddress')) {
        $personMapConfig = ['address' => $plaintextMailingAddress];
    }

    // ── Timeline ─────────────────────────────────────────────────────────────
    $timelineService = new TimelineService();
    $personTimeline  = $timelineService->getForPerson($iPersonID);

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
        'personData'             => $personData,
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
        // Custom fields
        'customFieldsMaster'     => $customFieldsMaster,
        'aCustomData'            => $aCustomData,
        // Groups
        'assignedGroupsData'     => $assignedGroupsData,
        'allGroupsData'          => $allGroupsData,
        // Volunteer opps
        'assignedVolunteerOppsData' => $assignedVolunteerOppsData,
        'allVolunteerOppsData'      => $allVolunteerOppsData,
        // Volunteer rollout state (#9704): 'v1' | 'v2' | 'both'. Decides which
        // Volunteer tab(s) the view renders; the POST / RemoveVO handlers above
        // are untouched, the flag only decides whether the form reaches them.
        'volunteerVersion'          => User::getVolunteerVersion(),
        // #9711: the V2 pane's data (design §3.5). Empty arrays in `v1` mode,
        // where the pane is not rendered at all.
        'volunteerV2Qualifications' => $volunteerV2Qualifications,
        'volunteerV2Assignments'    => $volunteerV2Assignments,
        // Properties (ORM)
        'assignedPersonProperties'  => $assignedPersonProperties,
        'allPersonProperties'       => $allPersonProperties,
        // Map & Timeline
        'personMapConfig'        => $personMapConfig,
        'familyHasCoords'        => $familyHasCoords,
        'personTimeline'         => $personTimeline,
    ];

    return $renderer->render($response, 'person-view.php', $pageArgs);
});
