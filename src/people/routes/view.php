<?php

require_once __DIR__ . '/../../Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\VolunteerOpportunityQuery;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\PropertyService;
use ChurchCRM\Service\TimelineService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
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
        $volIDs        = $parsedBody['VolunteerOpportunityIDs'] ?? [];
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
    if (!$currentUser->canEditPerson($iPersonID, $person->getFamId())) {
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
        $breadcrumbItems[] = [InputUtils::escapeHTML($person->getFamily()->getName()), '/v2/family/' . $person->getFamId()];
    }
    $breadcrumbItems[] = [InputUtils::escapeHTML($person->getFirstName() . ' ' . $person->getLastName())];
    $aBreadcrumbs = PageHeader::breadcrumbs($breadcrumbItems);

    $headerButtons = [];
    if ($currentUser->isAdmin()) {
        if (!$person->isUser()) {
            $headerButtons[] = ['label' => gettext('Make User'), 'url' => '/UserEditor.php?NewPersonID=' . $iPersonID, 'icon' => 'fa-person-chalkboard'];
        } else {
            $headerButtons[] = ['label' => gettext('Edit User'), 'url' => '/UserEditor.php?PersonID=' . $iPersonID, 'icon' => 'fa-user-secret'];
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

    // ── Properties (ORM via PropertyService — mirrors master's PersonView.php) ─
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
