<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\FunctionsUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// -----------------------------------------------------------------------
// GET /groups/{groupID}/members/{personID}/properties — render edit form.
//
// Auth note: The original GroupPropsEditor.php required isEditRecordsEnabled().
// This route lives in the groups MVC app, which is globally gated by
// ManageGroupRoleAuthMiddleware (isManageGroupsEnabled()).  We preserve the
// isEditRecordsEnabled() check here so the intent is explicit; the combined
// effective permission is ManageGroups AND EditRecords.  The only UI entry
// point (person-view.php) is already gated by isManageGroupsEnabled(), so
// the practical impact of the tighter combined check is nil.
// -----------------------------------------------------------------------
$app->get('/{groupID:[0-9]+}/members/{personID:[0-9]+}/properties', function (Request $request, Response $response, array $args): Response {
    if (!AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
        AuthenticationManager::redirectHomeIfFalse(false, 'EditRecords');
        return $response;
    }

    $iGroupID  = (int) $args['groupID'];
    $iPersonID = (int) $args['personID'];
    $logger    = LoggerUtils::getAppLogger();

    $thisGroup  = GroupQuery::create()->findOneById($iGroupID);
    $thisPerson = PersonQuery::create()->findOneById($iPersonID);

    if ($thisGroup === null || $thisPerson === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    // Ensure groupprop_X table exists and person has a row.
    ensurePropTableAndRow($iGroupID, $iPersonID, $logger);

    // Load current values for this person.
    $rsPersonProps = FunctionsUtils::runQuery('SELECT * FROM groupprop_' . $iGroupID . ' WHERE per_ID = ' . $iPersonID);
    $aPersonProps  = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH) ?: [];

    $propMasterRows = GroupPropMasterQuery::create()->filterByGrpId($iGroupID)->orderByPropId()->find();

    $pageArgs = buildMemberPropsPageArgs(
        $iGroupID,
        $iPersonID,
        $thisGroup,
        $thisPerson,
        $propMasterRows,
        $aPersonProps,
        [],  // no validation errors on first pass
        false
    );

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-member-properties.php', $pageArgs);
});

// -----------------------------------------------------------------------
// POST /groups/{groupID}/members/{personID}/properties — save values,
// redirect to person view on success.
// -----------------------------------------------------------------------
$app->post('/{groupID:[0-9]+}/members/{personID:[0-9]+}/properties', function (Request $request, Response $response, array $args): Response {
    if (!AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) {
        AuthenticationManager::redirectHomeIfFalse(false, 'EditRecords');
        return $response;
    }

    $iGroupID  = (int) $args['groupID'];
    $iPersonID = (int) $args['personID'];
    $logger    = LoggerUtils::getAppLogger();

    $thisGroup  = GroupQuery::create()->findOneById($iGroupID);
    $thisPerson = PersonQuery::create()->findOneById($iPersonID);

    if ($thisGroup === null || $thisPerson === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    $body = (array) $request->getParsedBody();

    // Phone country fallback (needed for phone-type fields)
    $sPhoneCountry = $thisPerson->getCountry() ?? '';

    $propMasterRows = GroupPropMasterQuery::create()->filterByGrpId($iGroupID)->orderByPropId()->find();

    $aPropErrors  = [];
    $bErrorFlag   = false;
    $aPersonProps = [];

    foreach ($propMasterRows as $propRow) {
        $prop_Field = $propRow->getField();
        $type_ID    = $propRow->getTypeId();

        $currentFieldData = InputUtils::legacyFilterInput($body[$prop_Field] ?? '');
        $bErrorFlag |= !CustomFieldUtils::validate($type_ID, $currentFieldData, $prop_Field, $aPropErrors);
        $aPersonProps[$prop_Field] = $currentFieldData;
    }

    if (!$bErrorFlag) {
        // Build UPDATE SQL
        $sSQL = 'UPDATE groupprop_' . $iGroupID . ' SET ';

        foreach ($propMasterRows as $propRow) {
            $prop_Field       = $propRow->getField();
            $type_ID          = $propRow->getTypeId();
            $currentFieldData = trim((string) ($aPersonProps[$prop_Field] ?? ''));
            CustomFieldUtils::buildSql($sSQL, $type_ID, $currentFieldData, $prop_Field, $sPhoneCountry);
        }

        // Chop trailing ', '
        $sSQL  = mb_substr($sSQL, 0, -2);
        $sSQL .= ' WHERE per_ID = ' . $iPersonID;

        $updateResult = FunctionsUtils::runQuery($sSQL, false);

        if (!$updateResult) {
            $logger->error('Failed to update group member properties', [
                'person_id' => $iPersonID,
                'group_id'  => $iGroupID,
            ]);
            $aPropErrors['_db'] = gettext('An error occurred while saving. Please try again.');
        } else {
            return SlimUtils::renderRedirect($response, Person::getViewURIForId($iPersonID));
        }
    }

    // Validation failed or DB error — re-render with errors.
    // Reload current DB values for re-display.
    $rsPersonProps = FunctionsUtils::runQuery('SELECT * FROM groupprop_' . $iGroupID . ' WHERE per_ID = ' . $iPersonID);
    $aPersonProps  = mysqli_fetch_array($rsPersonProps, MYSQLI_BOTH) ?: [];

    $pageArgs = buildMemberPropsPageArgs(
        $iGroupID,
        $iPersonID,
        $thisGroup,
        $thisPerson,
        $propMasterRows,
        $aPersonProps,
        $aPropErrors,
        true
    );

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-member-properties.php', $pageArgs);
});

// -----------------------------------------------------------------------
// Helper: ensure the per-group property table and a row for this person exist.
// -----------------------------------------------------------------------
function ensurePropTableAndRow(int $iGroupID, int $iPersonID, $logger): void
{
    $tableCheck = FunctionsUtils::runQuery('SHOW TABLES LIKE "groupprop_' . $iGroupID . '"');
    if (mysqli_num_rows($tableCheck) === 0) {
        $createSQL = 'CREATE TABLE IF NOT EXISTS groupprop_' . $iGroupID . ' (
            per_ID mediumint(8) unsigned NOT NULL default "0",
            PRIMARY KEY (per_ID),
            UNIQUE KEY per_ID (per_ID)
        ) ENGINE=InnoDB';
        $r = FunctionsUtils::runQuery($createSQL, false);
        if (!$r) {
            $logger->error('Failed to create group properties table', ['group_id' => $iGroupID]);
            return;
        }
    }

    $rowCheck = FunctionsUtils::runQuery('SELECT per_ID FROM groupprop_' . $iGroupID . ' WHERE per_ID = ' . $iPersonID);
    if (mysqli_num_rows($rowCheck) === 0) {
        FunctionsUtils::runQuery('INSERT INTO groupprop_' . $iGroupID . ' (per_ID) VALUES (' . $iPersonID . ')', false);
    }
}

// -----------------------------------------------------------------------
// Helper: build $pageArgs for both GET and error-rerender POST.
// -----------------------------------------------------------------------
function buildMemberPropsPageArgs(
    int $iGroupID,
    int $iPersonID,
    object $thisGroup,
    object $thisPerson,
    object $propMasterRows,
    array $aPersonProps,
    array $aPropErrors,
    bool $isPostPass
): array {
    $aBreadcrumbs = PageHeader::breadcrumbs([
        [gettext('Groups'), '/groups/dashboard'],
        [InputUtils::escapeHTML($thisGroup->getName()), '/groups/view/' . $iGroupID],
        [gettext('Member Properties')],
    ]);

    return [
        'sRootPath'    => SystemURLs::getRootPath(),
        'sPageTitle'   => gettext('Group Member Properties Editor'),
        'sPageSubtitle' => gettext('Edit custom properties for a group member'),
        'aBreadcrumbs' => $aBreadcrumbs,
        'iGroupID'     => $iGroupID,
        'iPersonID'    => $iPersonID,
        'thisGroup'    => $thisGroup,
        'thisPerson'   => $thisPerson,
        'propMasterRows' => $propMasterRows,
        'aPersonProps' => $aPersonProps,
        'aPropErrors'  => $aPropErrors,
        'isPostPass'   => $isPostPass,
    ];
}
