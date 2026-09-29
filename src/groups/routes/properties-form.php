<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
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
// Shared helper: prepare the form data arrays from groupprop_master rows.
// -----------------------------------------------------------------------
function preparePropsFormData(int $iGroupID): array
{
    $data = [
        'numRows'              => 0,
        'aNameFields'         => [],
        'aDescFields'         => [],
        'aSpecialFields'      => [],
        'aFieldFields'        => [],
        'aTypeFields'         => [],
        'aPersonDisplayFields' => [],
    ];

    $rsPropList = FunctionsUtils::runQuery(
        'SELECT * FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID'
    );
    $data['numRows'] = mysqli_num_rows($rsPropList);

    for ($row = 1; $row <= $data['numRows']; $row++) {
        $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
        $data['aNameFields'][$row]          = $aRow['prop_Name'];
        $data['aDescFields'][$row]          = $aRow['prop_Description'];
        $data['aSpecialFields'][$row]       = $aRow['prop_Special'];
        $data['aFieldFields'][$row]         = $aRow['prop_Field'];
        $data['aTypeFields'][$row]          = $aRow['type_ID'];
        $data['aPersonDisplayFields'][$row] = ($aRow['prop_PersonDisplay'] === 'true');
    }

    return $data;
}

// -----------------------------------------------------------------------
// GET /groups/{groupID}/properties/form — render the form definition editor.
// -----------------------------------------------------------------------
$app->get('/{groupID:[0-9]+}/properties/form', function (Request $request, Response $response, array $args): Response {
    $iGroupID  = (int) $args['groupID'];
    $thisGroup = GroupQuery::create()->findOneById($iGroupID);

    if ($thisGroup === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    if (!$thisGroup->getHasSpecialProps()) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/view/' . $iGroupID);
    }

    $formData = preparePropsFormData($iGroupID);

    $pageArgs = buildPropsFormPageArgs($iGroupID, $thisGroup, $formData, false, [], false, false, []);

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-properties-form.php', $pageArgs);
});

// -----------------------------------------------------------------------
// POST /groups/{groupID}/properties/form — add a new property OR save changes.
// -----------------------------------------------------------------------
$app->post('/{groupID:[0-9]+}/properties/form', function (Request $request, Response $response, array $args): Response {
    $iGroupID  = (int) $args['groupID'];
    $thisGroup = GroupQuery::create()->findOneById($iGroupID);

    if ($thisGroup === null) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/dashboard');
    }

    if (!$thisGroup->getHasSpecialProps()) {
        return SlimUtils::renderRedirect($response, SystemURLs::getRootPath() . '/groups/view/' . $iGroupID);
    }

    $logger = LoggerUtils::getAppLogger();
    $body   = (array) $request->getParsedBody();

    $bErrorFlag         = false;
    $aNameErrors        = [];
    $bNewNameError      = false;
    $bDuplicateNameError = false;
    $aSpecialErrors     = [];

    if (isset($body['SaveChanges'])) {
        // ---------------------------------------------------------------
        // Save changes to existing property names / descriptions.
        // ---------------------------------------------------------------
        $rsPropList = FunctionsUtils::runQuery(
            'SELECT prop_ID, prop_Field, type_ID, prop_Special, prop_PersonDisplay FROM groupprop_master WHERE grp_ID = ' . $iGroupID . ' ORDER BY prop_ID'
        );
        $numRows = mysqli_num_rows($rsPropList);

        $aFieldFields       = [];
        $aTypeFields        = [];
        $aSpecialFields     = [];
        $aNameFields        = [];
        $aDescFields        = [];
        $aPersonDisplayFields = [];

        for ($row = 1; $row <= $numRows; $row++) {
            $aRow = mysqli_fetch_array($rsPropList, MYSQLI_BOTH);
            $aFieldFields[$row]   = $aRow['prop_Field'];
            $aTypeFields[$row]    = $aRow['type_ID'];
            $aSpecialFields[$row] = isset($aRow['prop_Special']) ? $aRow['prop_Special'] : 'NULL';
        }

        for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
            $aNameFields[$iPropID] = InputUtils::legacyFilterInput($body[$iPropID . 'name'] ?? '');
            if (strlen($aNameFields[$iPropID]) === 0) {
                $aNameErrors[$iPropID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iPropID] = false;
            }
            $aDescFields[$iPropID] = InputUtils::legacyFilterInput($body[$iPropID . 'desc'] ?? '');

            if (isset($body[$iPropID . 'special'])) {
                $aSpecialFields[$iPropID] = InputUtils::filterInt($body[$iPropID . 'special']);
                if ($aSpecialFields[$iPropID] === 0) {
                    $aSpecialErrors[$iPropID] = true;
                    $bErrorFlag = true;
                } else {
                    $aSpecialErrors[$iPropID] = false;
                }
            }

            $aPersonDisplayFields[$iPropID] = isset($body[$iPropID . 'show']);
        }

        if (!$bErrorFlag) {
            for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
                $temp = $aPersonDisplayFields[$iPropID] ? 'true' : 'false';
                $sql  = "UPDATE groupprop_master
                    SET `prop_Name` = '" . $aNameFields[$iPropID] . "',
                        `prop_Description` = '" . $aDescFields[$iPropID] . "',
                        `prop_Special` = " . $aSpecialFields[$iPropID] . ",
                        `prop_PersonDisplay` = '" . $temp . "'
                    WHERE `grp_ID` = '" . $iGroupID . "' AND `prop_ID` = '" . $iPropID . "';";
                FunctionsUtils::runQuery($sql);
            }
        }

        // Re-read fresh form data to show current state
        $formData = preparePropsFormData($iGroupID);
        $pageArgs = buildPropsFormPageArgs($iGroupID, $thisGroup, $formData, $bErrorFlag, $aNameErrors, $bNewNameError, $bDuplicateNameError, $aSpecialErrors);

        $renderer = new PhpRenderer(__DIR__ . '/../views/');
        return $renderer->render($response, 'group-properties-form.php', $pageArgs);
    }

    // -------------------------------------------------------------------
    // AddField: add a new property column.
    // -------------------------------------------------------------------
    if (isset($body['AddField'])) {
        $newFieldType = InputUtils::filterInt($body['newFieldType'] ?? '1');
        $newFieldName = InputUtils::legacyFilterInput($body['newFieldName'] ?? '');
        $newFieldDesc = InputUtils::legacyFilterInput($body['newFieldDesc'] ?? '');

        if (strlen($newFieldName) === 0) {
            $bNewNameError = true;
        } else {
            $rsPropNames = FunctionsUtils::runQuery('SELECT prop_Name FROM groupprop_master WHERE grp_ID = ' . $iGroupID);
            while ($aRow = mysqli_fetch_array($rsPropNames)) {
                if ($aRow[0] === $newFieldName) {
                    $bDuplicateNameError = true;
                    break;
                }
            }

            if (!$bDuplicateNameError) {
                $rsPropList = FunctionsUtils::runQuery('SELECT prop_ID FROM groupprop_master WHERE grp_ID = ' . $iGroupID);
                $newRowNum  = mysqli_num_rows($rsPropList) + 1;

                $tableName    = 'groupprop_' . $iGroupID;
                $fieldsResult = FunctionsUtils::runQuery('SELECT * FROM ' . $tableName);
                $newFieldNum  = mysqli_num_fields($fieldsResult);

                // Guard: only types 1–12 have a known column definition.
                if (!in_array($newFieldType, range(1, 12), true)) {
                    $bNewNameError = true; // re-render form with no change
                } else {
                if ($newFieldType == 12) {
                    $rsTempMax = FunctionsUtils::runQuery('SELECT MAX(lst_ID) FROM list_lst');
                    $aTemp     = mysqli_fetch_array($rsTempMax);
                    $newListID = ($aTemp[0] > 9) ? $aTemp[0] + 1 : 10;

                    $listOption = new ListOption();
                    $listOption->setId($newListID)->setOptionId(1)->setOptionSequence(1)->setOptionName(gettext('Default Option'));
                    $listOption->save();

                    $newSpecial = "'" . $newListID . "'";
                } else {
                    $newSpecial = 'NULL';
                }

                $insertSQL = "INSERT INTO `groupprop_master`
                    ( `grp_ID` , `prop_ID` , `prop_Field` , `prop_Name` , `prop_Description` , `type_ID` , `prop_Special` )
                    VALUES ('" . $iGroupID . "', '" . $newRowNum . "', 'c" . $newFieldNum . "', '" . $newFieldName . "', '" . $newFieldDesc . "', '" . $newFieldType . "', $newSpecial);";
                FunctionsUtils::runQuery($insertSQL);

                $alterSQL = 'ALTER TABLE `groupprop_' . $iGroupID . '` ADD `c' . $newFieldNum . '` ';
                switch ($newFieldType) {
                    case 1:  $alterSQL .= "ENUM('false', 'true')"; break;
                    case 2:  $alterSQL .= 'DATE'; break;
                    case 3:  $alterSQL .= 'VARCHAR(50)'; break;
                    case 4:  $alterSQL .= 'VARCHAR(100)'; break;
                    case 5:  $alterSQL .= 'TEXT'; break;
                    case 6:  $alterSQL .= 'YEAR'; break;
                    case 7:  $alterSQL .= "ENUM('winter', 'spring', 'summer', 'fall')"; break;
                    case 8:  $alterSQL .= 'INT'; break;
                    case 9:  $alterSQL .= 'MEDIUMINT(9)'; break;
                    case 10: $alterSQL .= 'DECIMAL(10,2)'; break;
                    case 11: $alterSQL .= 'VARCHAR(30)'; break;
                    case 12: $alterSQL .= 'TINYINT(4)'; break;
                }
                $alterSQL .= ' DEFAULT NULL;';
                $alterResult = FunctionsUtils::runQuery($alterSQL, false);

                if (!$alterResult) {
                    $logger->warning('Failed to add column to group properties table', [
                        'column'   => 'c' . $newFieldNum,
                        'group_id' => $iGroupID,
                    ]);
                }

                $bNewNameError = false;
                } // end type-range guard
            }
        }
    }

    // Re-read fresh form data to show current state
    $formData = preparePropsFormData($iGroupID);
    $pageArgs = buildPropsFormPageArgs($iGroupID, $thisGroup, $formData, $bErrorFlag, $aNameErrors, $bNewNameError, $bDuplicateNameError, $aSpecialErrors);

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    return $renderer->render($response, 'group-properties-form.php', $pageArgs);
});

// -----------------------------------------------------------------------
// Helper: build $pageArgs for both GET and POST renders.
// -----------------------------------------------------------------------
function buildPropsFormPageArgs(
    int $iGroupID,
    object $thisGroup,
    array $formData,
    bool $bErrorFlag,
    array $aNameErrors,
    bool $bNewNameError,
    bool $bDuplicateNameError,
    array $aSpecialErrors
): array {
    $aPropTypes = CustomFieldUtils::getPropTypes();

    // For type=9 (Person from Group) special select, load group list
    $rsGroupList = FunctionsUtils::runQuery('SELECT grp_ID, grp_Name FROM group_grp ORDER BY grp_Name');
    $aGroupList  = [];
    while ($aRow = mysqli_fetch_array($rsGroupList)) {
        $aGroupList[] = ['grp_ID' => $aRow['grp_ID'], 'grp_Name' => $aRow['grp_Name']];
    }

    $aBreadcrumbs = PageHeader::breadcrumbs([
        [gettext('Groups'), '/groups/dashboard'],
        [InputUtils::escapeHTML($thisGroup->getName()), '/groups/view/' . $iGroupID],
        [gettext('Properties Form')],
    ]);

    return [
        'sRootPath'           => SystemURLs::getRootPath(),
        'sPageTitle'          => gettext('Group-Specific Properties Form Editor') . ':  ' . $thisGroup->getName(),
        'sPageSubtitle'       => gettext('Define group-specific properties for members'),
        'aBreadcrumbs'        => $aBreadcrumbs,
        'iGroupID'            => $iGroupID,
        'thisGroup'           => $thisGroup,
        'aPropTypes'          => $aPropTypes,
        'numRows'             => $formData['numRows'],
        'aNameFields'         => $formData['aNameFields'],
        'aDescFields'         => $formData['aDescFields'],
        'aSpecialFields'      => $formData['aSpecialFields'],
        'aFieldFields'        => $formData['aFieldFields'],
        'aTypeFields'         => $formData['aTypeFields'],
        'aPersonDisplayFields' => $formData['aPersonDisplayFields'],
        'bErrorFlag'          => $bErrorFlag,
        'aNameErrors'         => $aNameErrors,
        'bNewNameError'       => $bNewNameError,
        'bDuplicateNameError' => $bDuplicateNameError,
        'aSpecialErrors'      => $aSpecialErrors,
        'aGroupList'          => $aGroupList,
    ];
}
