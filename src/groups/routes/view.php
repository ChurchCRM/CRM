<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/view/not-found', 'viewGroupNotFound');
$app->get('/view/{groupID}', 'viewGroup');

function viewGroupNotFound(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer(SystemURLs::getDocumentRoot() . '/v2/templates/common/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'memberType' => 'Group',
        'id'         => (int) ($request->getQueryParams()['id'] ?? 0),
    ];

    return $renderer->render($response->withStatus(404), 'not-found-view.php', $pageArgs);
}

function viewGroup(Request $request, Response $response, array $args): Response
{
    $iGroupID = (int) ($args['groupID'] ?? 0);

    if ($iGroupID < 1) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    $thisGroup = GroupQuery::create()->findOneById($iGroupID);
    if ($thisGroup === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/view/not-found?id=' . $iGroupID);
    }

    // ------------------------------------------------------------------ //
    // Group type name
    // ------------------------------------------------------------------ //
    if ($thisGroup->getType() > 0) {
        $groupTypeOption = ListOptionQuery::create()
            ->filterById(3)
            ->filterByOptionId($thisGroup->getType())
            ->findOne();
        $sGroupType = $groupTypeOption ? $groupTypeOption->getOptionName() : gettext('Unknown');
    } else {
        $sGroupType = gettext('Unassigned');
    }

    // ------------------------------------------------------------------ //
    // Default role name
    // ------------------------------------------------------------------ //
    $defaultRole = ListOptionQuery::create()
        ->filterById($thisGroup->getRoleListId())
        ->filterByOptionId($thisGroup->getDefaultRole())
        ->findOne();

    // ------------------------------------------------------------------ //
    // Assigned properties (record2property_r2p, class = 'g')
    // ------------------------------------------------------------------ //
    $assignedPropertyRecords = RecordPropertyQuery::create()
        ->filterByRecordId($iGroupID)
        ->find();

    $rsAssignedRows        = [];
    $rsAssignedPropertyIds = [];
    foreach ($assignedPropertyRecords as $rec) {
        $propDef = $rec->getProperty();
        if ($propDef === null || $propDef->getProClass() !== 'g') {
            continue;
        }
        $propType          = $propDef->getPropertyType();
        $rsAssignedRows[]  = [
            'pro_ID'     => $rec->getPropertyId(),
            'pro_Name'   => $propDef->getProName(),
            'r2p_Value'  => $rec->getPropertyValue(),
            'pro_Prompt' => (string) $propDef->getProPrompt(),
            'prt_Name'   => $propType ? $propType->getPrtName() : '',
            'pro_prt_ID' => $propDef->getProPrtId(),
        ];
        $rsAssignedPropertyIds[] = $rec->getPropertyId();
    }
    usort($rsAssignedRows, fn ($a, $b) => [$a['prt_Name'], $a['pro_Name']] <=> [$b['prt_Name'], $b['pro_Name']]);

    // All group property definitions (for the "Assign" dropdown)
    $allGroupPropertyDefs = PropertyQuery::create()
        ->filterByProClass('g')
        ->orderByProName()
        ->find();

    // ------------------------------------------------------------------ //
    // Group-specific custom properties (groupprop_master)
    // ------------------------------------------------------------------ //
    $groupSpecificProps = [];
    if ($thisGroup->getHasSpecialProps()) {
        $groupSpecificProps = GroupPropMasterQuery::create()
            ->filterByGrpId($iGroupID)
            ->orderByPropId()
            ->find();
    }

    // Hardcoded type-ID → label map (mirrors Functions.php $aPropTypes)
    $aPropTypes = [
        1  => gettext('True / False'),
        2  => gettext('Date'),
        3  => gettext('Text Field (50 char)'),
        4  => gettext('Text Field (100 char)'),
        5  => gettext('Text Field (long)'),
        6  => gettext('Year'),
        7  => gettext('Season'),
        8  => gettext('Number'),
        9  => gettext('Person from Group'),
        10 => gettext('Money'),
        11 => gettext('Phone Number'),
        12 => gettext('Custom Drop-Down List'),
    ];

    // ------------------------------------------------------------------ //
    // Email / phone list (ORM, honouring Do-Not-Email / Do-Not-SMS props)
    // ------------------------------------------------------------------ //
    $doNotEmailSet = array_flip(_getExcludedPersonIds('Do Not Email'));
    $doNotSmsSet   = array_flip(_getExcludedPersonIds('Do Not SMS'));

    // Role name map for this group's role list
    $roleNameMap = [];
    foreach (ListOptionQuery::create()->filterById($thisGroup->getRoleListId())->find() as $opt) {
        $roleNameMap[(int) $opt->getOptionId()] = $opt->getOptionName();
    }

    $memberships      = Person2group2roleP2g2rQuery::create()
        ->filterByGroupId($iGroupID)
        ->innerJoinWithPerson()
        ->find();

    $mailtoDelimiter  = AuthenticationManager::getCurrentUser()->getUserConfigString('sMailtoDelimiter');
    $systemEmail      = (string) SystemConfig::getValue('sToEmailAddress');
    $allEmailsSeen    = [];  // email => true (hash set)
    $roleEmailsRaw    = [];  // role name => [email, ...]
    $phonesSeen       = [];  // phone => true (hash set)
    $phonesRaw        = [];

    foreach ($memberships as $membership) {
        $person = $membership->getPerson();
        if ($person === null) {
            continue;
        }
        $personId = (int) $person->getId();
        $roleId   = (int) $membership->getRoleId();
        $roleName = $roleNameMap[$roleId] ?? gettext('Member');

        // Email
        if (!isset($doNotEmailSet[$personId])) {
            $email = (string) $person->getEmail();
            if (!empty($email) && !isset($allEmailsSeen[$email])) {
                $allEmailsSeen[$email]        = true;
                $roleEmailsRaw[$roleName][]   = $email;
            }
        }

        // Phone
        if (!isset($doNotSmsSet[$personId])) {
            $phone = (string) $person->getCellPhone();
            if (!empty($phone) && !isset($phonesSeen[$phone])) {
                $phonesSeen[$phone] = true;
                $phonesRaw[]       = $phone;
            }
        }
    }

    // Build full mailto link for "All Members"
    $allEmailParts = array_keys($allEmailsSeen);
    if (!empty($systemEmail) && !isset($allEmailsSeen[$systemEmail])) {
        $allEmailParts[] = $systemEmail;
    }
    $sEmailLink = !empty($allEmailParts)
        ? urlencode(implode($mailtoDelimiter, $allEmailParts))
        : '';

    // Build per-role mailto links
    $roleEmails = [];
    foreach ($roleEmailsRaw as $roleName => $roleAddresses) {
        $parts = $roleAddresses;
        if (!empty($systemEmail) && !in_array($systemEmail, $parts, true)) {
            $parts[] = $systemEmail;
        }
        $roleEmails[$roleName] = urlencode(implode($mailtoDelimiter, $parts));
    }

    $sPhoneLink = implode(', ', $phonesRaw);

    // ------------------------------------------------------------------ //
    // Permissions
    // ------------------------------------------------------------------ //
    $currentUser      = AuthenticationManager::getCurrentUser();
    $bCanManageGroups = $currentUser->isManageGroupsEnabled();
    $bEmailEnabled    = $currentUser->isEmailEnabled();

    // ------------------------------------------------------------------ //
    // Page header
    // ------------------------------------------------------------------ //
    $sPageTitle    = gettext('Group View') . ' : ' . InputUtils::escapeHTML($thisGroup->getName());
    $sPageSubtitle = gettext('View group members, roles, and properties');
    $aBreadcrumbs  = PageHeader::breadcrumbs([
        [gettext('Groups'), '/groups/dashboard'],
        [InputUtils::escapeHTML($thisGroup->getName())],
    ]);

    $headerButtons = [];
    if ($bCanManageGroups) {
        $headerButtons[] = [
            'label' => gettext('Edit Group'),
            'url'   => SystemURLs::getRootPath() . '/GroupEditor.php?GroupID=' . $iGroupID,
            'icon'  => 'fa-pen',
            'style' => 'outline-secondary',
        ];
    }
    $sPageHeaderButtons = !empty($headerButtons) ? PageHeader::buttons($headerButtons) : '';

    $pageArgs = [
        'sRootPath'          => SystemURLs::getRootPath(),
        'sPageTitle'         => $sPageTitle,
        'sPageSubtitle'      => $sPageSubtitle,
        'aBreadcrumbs'       => $aBreadcrumbs,
        'sPageHeaderButtons' => $sPageHeaderButtons,
        'iGroupID'           => $iGroupID,
        'thisGroup'          => $thisGroup,
        'sGroupType'         => $sGroupType,
        'defaultRole'        => $defaultRole,
        'bCanManageGroups'   => $bCanManageGroups,
        'bEmailEnabled'      => $bEmailEnabled,
        'rsAssignedRows'     => $rsAssignedRows,
        'rsAssignedPropertyIds' => $rsAssignedPropertyIds,
        'allGroupPropertyDefs'  => $allGroupPropertyDefs,
        'groupSpecificProps'    => $groupSpecificProps,
        'aPropTypes'         => $aPropTypes,
        'sEmailLink'         => $sEmailLink,
        'roleEmails'         => $roleEmails,
        'sPhoneLink'         => $sPhoneLink,
    ];

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-view.php', $pageArgs);
}

/**
 * Returns an array of person IDs who have the given property name assigned.
 * Used to exclude "Do Not Email" / "Do Not SMS" members.
 *
 * @return int[]
 */
function _getExcludedPersonIds(string $propertyName): array
{
    $prop = PropertyQuery::create()
        ->filterByProName($propertyName)
        ->filterByProClass('p')
        ->findOne();

    if ($prop === null) {
        return [];
    }

    $ids = [];
    foreach (RecordPropertyQuery::create()->filterByPropertyId($prop->getProId())->find() as $r) {
        $ids[] = (int) $r->getRecordId();
    }
    return $ids;
}
