<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
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
    ];

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-view.php', $pageArgs);
}

function _getExcludedPersonIdsByConfig(string $configKey): array
{
    $propertyId = (int) SystemConfig::getValue($configKey);
    if ($propertyId <= 0) {
        return [];
    }

    $ids = [];
    foreach (RecordPropertyQuery::create()->filterByPropertyId($propertyId)->find() as $r) {
        $ids[] = (int) $r->getRecordId();
    }

    return $ids;
}
