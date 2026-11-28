<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupPropMaster;
use ChurchCRM\model\ChurchCRM\GroupPropMasterQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOption;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

function normalizeGroupPropertyMetadata(string $value): string
{
    // Match the stored format so existing names still participate in duplicate checks.
    return strtr(InputUtils::sanitizeText($value), [
        "\0" => '\\0',
        "\n" => '\\n',
        "\r" => '\\r',
        "\x1a" => '\\Z',
        "\\" => '\\\\',
        "'" => "\\'",
        '"' => '\\"',
    ]);
}

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

    $row = 1;
    foreach (GroupPropMasterQuery::create()->filterByGrpId($iGroupID)->orderByPropId()->find() as $prop) {
        $data['aNameFields'][$row]          = $prop->getName();
        $data['aDescFields'][$row]          = $prop->getDescription();
        $data['aSpecialFields'][$row]       = $prop->getSpecial();
        $data['aFieldFields'][$row]         = $prop->getField();
        $data['aTypeFields'][$row]          = $prop->getTypeId();
        $data['aPersonDisplayFields'][$row] = ($prop->getPersonDisplay() === 'true');
        $row++;
    }
    $data['numRows'] = $row - 1;

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
        $existingProps = GroupPropMasterQuery::create()->filterByGrpId($iGroupID)->orderByPropId()->find();
        $numRows = count($existingProps);

        $aFieldFields       = [];
        $aTypeFields        = [];
        $aSpecialFields     = [];
        $aNameFields        = [];
        $aDescFields        = [];
        $aPersonDisplayFields = [];

        $row = 1;
        foreach ($existingProps as $prop) {
            $aFieldFields[$row]   = $prop->getField();
            $aTypeFields[$row]    = $prop->getTypeId();
            $aSpecialFields[$row] = $prop->getSpecial() ?? 'NULL';
            $row++;
        }

        for ($iPropID = 1; $iPropID <= $numRows; $iPropID++) {
            $aNameFields[$iPropID] = normalizeGroupPropertyMetadata($body[$iPropID . 'name'] ?? '');
            if (strlen($aNameFields[$iPropID]) === 0) {
                $aNameErrors[$iPropID] = true;
                $bErrorFlag = true;
            } else {
                $aNameErrors[$iPropID] = false;
            }
            $aDescFields[$iPropID] = normalizeGroupPropertyMetadata($body[$iPropID . 'desc'] ?? '');

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
                $special = $aSpecialFields[$iPropID];
                GroupPropMasterQuery::create()
                    ->filterByGrpId($iGroupID)
                    ->filterByPropId($iPropID)
                    ->update([
                        'Name' => $aNameFields[$iPropID],
                        'Description' => $aDescFields[$iPropID],
                        'Special' => ($special === 'NULL' || $special === null) ? null : (int) $special,
                        'PersonDisplay' => $aPersonDisplayFields[$iPropID] ? 'true' : 'false',
                    ]);
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
        $newFieldName = normalizeGroupPropertyMetadata($body['newFieldName'] ?? '');
        $newFieldDesc = normalizeGroupPropertyMetadata($body['newFieldDesc'] ?? '');

        if (strlen($newFieldName) === 0) {
            $bNewNameError = true;
        } else {
            $duplicate = GroupPropMasterQuery::create()
                ->filterByGrpId($iGroupID)
                ->filterByName($newFieldName)
                ->count();
            $bDuplicateNameError = $duplicate > 0;

            if (!$bDuplicateNameError) {
                $newRowNum = GroupPropMasterQuery::create()->filterByGrpId($iGroupID)->count() + 1;

                $columns = Propel::getConnection()->query('SHOW COLUMNS FROM `groupprop_' . $iGroupID . '`')->fetchAll();
                $newFieldNum = count($columns);

                // Guard: only types 1–12 have a known column definition.
                if (!in_array($newFieldType, range(1, 12), true)) {
                    $bNewNameError = true; // re-render form with no change
                } else {
                    $newProp = new GroupPropMaster();
                    $newProp
                        ->setGrpId($iGroupID)
                        ->setPropId($newRowNum)
                        ->setField('c' . $newFieldNum)
                        ->setName($newFieldName)
                        ->setDescription($newFieldDesc)
                        ->setTypeId($newFieldType);
                    if ($newFieldType == 12) {
                        $maxList = ListOptionQuery::create()->orderById(Criteria::DESC)->findOne();
                        $maxListId = (int) ($maxList?->getId() ?? 0);
                        $newListID = $maxListId > 9 ? $maxListId + 1 : 10;

                        $listOption = new ListOption();
                        $listOption->setId($newListID)->setOptionId(1)->setOptionSequence(1)->setOptionName(gettext('Default Option'));
                        $listOption->save();

                        $newProp->setSpecial($newListID);
                    }
                    $newProp->save();

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
                    try {
                        $alterResult = Propel::getConnection()->prepare($alterSQL)->execute();
                    } catch (\PDOException) {
                        $alterResult = false;
                    }

                    if (!$alterResult) {
                        $logger->warning('Failed to add column to group properties table', [
                            'column'   => 'c' . $newFieldNum,
                            'group_id' => $iGroupID,
                        ]);
                    }

                    $bNewNameError = false;
                }
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
    $aGroupList = [];
    foreach (GroupQuery::create()->orderByName()->find() as $group) {
        $aGroupList[] = ['grp_ID' => $group->getId(), 'grp_Name' => $group->getName()];
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
