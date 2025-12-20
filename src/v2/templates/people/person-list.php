<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\Utils\InputUtils;

/**
 * This will avoid to call the db twice one to check if empty the other one to return the value
 * no caching was being done by the ORM so lets keep the value and return if not empty
 *
 * @var mixed $stuff
 */
function emptyOrUnassigned($stuff)
{
    return empty($stuff) ? gettext('Unassigned') : $stuff;
}

/**
 * Same as previous but return json encoded
 *
 * @var mixed $stuff
 */
function emptyOrUnassignedJSON($stuff): string
{
    return empty($stuff) ? 'Unassigned' : InputUtils::escapeHTML(json_encode($stuff, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Listing');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
// Load compiled webpack assets for people list
echo '<link rel="stylesheet" href="' . SystemURLs::getRootPath() . '/skin/v2/people-list.min.css">';
echo '<script src="' . SystemURLs::getRootPath() . '/skin/v2/people-list.min.js"></script>';
// Classification list
$ListItem =  ListOptionQuery::create()->select('OptionName')->filterById(1)->find()->toArray();
$ClassificationList = [];
$ClassificationList[] = "Unassigned";
foreach ($ListItem as $element) {
    $ClassificationList[] = $element;
}
// Role list
$ListItem = ListOptionQuery::create()->select('OptionName')->filterById(2)->find()->toArray();
$RoleList = [];
$RoleList[] = "Unassigned";
foreach ($ListItem as $element) {
    $RoleList[] = $element;
}
// Person properties list
$ListItem = PropertyQuery::create()->filterByProClass("p")->find();
$PropertyList = [];
foreach ($ListItem as $element) {
    $PropertyList[] = $element->getProName();
}

$option_name = fn (string $t1, string $t2): string => $t1 . ':' . $t2;

$allPersonCustomFields = PersonCustomMasterQuery::create()->find();

// Person custom list
$ListItem = PersonCustomMasterQuery::create()->select(['Name', 'FieldSecurity', 'Id', 'TypeId', 'Special'])->find();

// CREATE A MAPPING FOR CUSTOMS LIKE THIS
// CustomMapping = {"c1":{"Name":"Father of confession", "Elements":{23:"option1", 24:"option2"}}, c2.... }
// allowing not only for search if has a custom set but also if is set to a given value.
$CustomMapping = [];

$CustomList = [];

foreach ($ListItem as $element) {
    if (AuthenticationManager::getCurrentUser()->isEnabledSecurity($element["FieldSecurity"])) {
        $CustomList[$element["Name"]] = 0;
        $CustomMapping[$element["Id"]] = ["Name" => $element["Name"], "Elements" => []];
        if (in_array($element["TypeId"], [12])) {
            $ListElements = ListOptionQuery::create()->select(['OptionName', 'OptionId'])->filterById($element["Special"])->find()->toArray();
            foreach ($ListElements as $element2) {
                $CustomList[$option_name($element["Name"], $element2["OptionName"])] = 0;
                $CustomMapping[$element["Id"]]["Elements"][$element2["OptionId"]] = $element2["OptionName"];
            }
        }
    }
}

// Get person group list
$ListItem = GroupQuery::create()->find();
$GroupList = [];
$GroupList[] = "Unassigned";
foreach ($ListItem as $element) {
    $GroupList[] = $element->getName();
}

// Person list column definitions - defines which columns appear and their data source
// Note: Some columns are hidden but still needed for DataTable filtering functionality
// Note: Column names used in filters (Gender, Role, Classification, Properties, Custom, Group) 
//       must be plain strings (not gettext) for the $columnIdMap to work correctly
$personListColumns = [
    (object) ['name' => 'Id', 'displayFunction' => 'getId', 'visible' => 'false', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Name', 'displayFunction' => 'getFullName', 'visible' => 'true', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Family Name', 'displayFunction' => 'getFamilyName', 'visible' => 'true', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Family Status', 'displayFunction' => '', 'visible' => 'false', 'emptyOrUnassigned' => 'false', 'isFamilyStatus' => true],
    (object) ['name' => 'Phone', 'displayFunction' => 'getPhones', 'visible' => 'true', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Email', 'displayFunction' => 'getEmail', 'visible' => 'true', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Classification', 'displayFunction' => 'getClassificationName', 'visible' => 'true', 'emptyOrUnassigned' => 'true'],
    (object) ['name' => 'Gender', 'displayFunction' => 'getGenderName', 'visible' => 'false', 'emptyOrUnassigned' => 'true'],
    (object) ['name' => 'Role', 'displayFunction' => 'getFamilyRoleName', 'visible' => 'false', 'emptyOrUnassigned' => 'true'],
    (object) ['name' => 'Birth Date', 'displayFunction' => 'getFormattedBirthDate', 'visible' => 'false', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Address', 'displayFunction' => 'getAddress', 'visible' => 'false', 'emptyOrUnassigned' => 'false'],
    (object) ['name' => 'Properties', 'displayFunction' => 'getPropertiesString', 'visible' => 'false', 'emptyOrUnassigned' => 'true'],
    (object) ['name' => 'Custom', 'displayFunction' => 'getCustomFields', 'visible' => 'false', 'emptyOrUnassigned' => 'true'],
    (object) ['name' => 'Group', 'displayFunction' => 'getGroups', 'visible' => 'true', 'emptyOrUnassigned' => 'true'],
];

?>

<?php
// Calculate data quality status
$hasDataQualityIssues = $genderDataCheckCount > 0 || $roleDataCheckCount > 0 ||
                        $classificationDataCheckCount > 0;
?>

<?php if ($hasDataQualityIssues): ?>
<!-- Data Quality Alert -->
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <div class="mr-3">
            <i class="fa-solid fa-clipboard-check fa-2x"></i>
        </div>
        <div class="flex-grow-1">
            <strong><?= gettext('Data Quality:') ?></strong>
            <?php 
            $issues = [];
            if ($genderDataCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/v2/people?Gender=0" class="alert-link">' . 
                            sprintf(gettext('%d missing gender'), $genderDataCheckCount) . '</a>';
            }
            if ($roleDataCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/v2/people?FamilyRole=0" class="alert-link">' . 
                            sprintf(gettext('%d missing role'), $roleDataCheckCount) . '</a>';
            }
            if ($classificationDataCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/v2/people?Classification=0" class="alert-link">' . 
                            sprintf(gettext('%d missing classification'), $classificationDataCheckCount) . '</a>';
            }

            echo implode(' · ', $issues);
            ?>
        </div>
    </div>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<div class="card card-primary mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-filter"></i> <span id="filters-title"></span></h3>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <label id="label-family-status"></label>
                    <select style="width: 100%;" class="form-control filter-FamilyStatus" multiple="multiple"></select>
                </div>
                <div class="form-group">
                    <label id="label-gender"></label>
                    <select style="width: 100%;" class="form-control filter-Gender" multiple="multiple"></select>
                </div>
                <div class="form-group">
                    <label id="label-classification"></label>
                    <select style="width: 100%;" class="form-control filter-Classification" multiple="multiple"></select>
                </div>
                <div class="form-group">
                    <label id="label-role"></label>
                    <select style="width: 100%;" class="form-control filter-Role" multiple="multiple"></select>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="form-group">
                    <label id="label-properties"></label>
                    <select style="width: 100%;" class="form-control filter-Properties" multiple="multiple"></select>
                </div>
                <div class="form-group">
                    <label id="label-custom"></label>
                    <select style="width: 100%;" class="form-control filter-Custom" multiple="multiple"></select>
                </div>
                <div class="form-group">
                    <label id="label-group"></label>
                    <select style="width: 100%;" class="form-control filter-Group" multiple="multiple"></select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <button id="ClearFilter" type="button" class="btn btn-secondary btn-block">
                    <i class="fa-solid fa-times"></i> <span id="clear-filter-text"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-users"></i> <span id="people-title"></span></h3>
    </div>
    <div class="card-body p-2">
        <table id="members" class="table table-striped table-hover data-table mb-0 w-100">
            <thead>
                <tr>
                    <?php foreach ($personListColumns as $column) {
                        // Output all columns - DataTables JS config controls visibility
                        echo '<th>' . $column->name . '</th>';
                    } ?>
                    <th><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
            <!--Populate the table with person details -->
            <?php foreach ($members as $person) {
              /* @var $members ChurchCRM\people */

                ?>
            <tr>
                <?php
                $columns = $personListColumns;
                foreach ($columns as $column) {
                    // Output ALL columns - DataTables JS config controls visibility
                    
                    echo '<td>';
                    
                    // Handle special Phone column (merged Home + Cell)
                    if ($column->displayFunction === 'getPhones') {
                        $homePhone = $person->getHomePhone();
                        $cellPhone = $person->getCellPhone();
                        $hasPhone = false;
                        if (!empty($homePhone)) {
                            echo '<i class="fa-solid fa-house text-muted mr-1" title="' . gettext('Home') . '"></i>' . InputUtils::escapeHTML($homePhone);
                            $hasPhone = true;
                        }
                        if (!empty($cellPhone)) {
                            if ($hasPhone) echo '<br>';
                            echo '<i class="fa-solid fa-mobile-screen text-muted mr-1" title="' . gettext('Cell') . '"></i>' . InputUtils::escapeHTML($cellPhone);
                            $hasPhone = true;
                        }
                        if (!$hasPhone) {
                            echo '<span class="text-muted">—</span>';
                        }
                    }
                    // Handle other columns
                    else {
                        // Skip method call for Family Status column (handled separately below)
                        if (!isset($column->isFamilyStatus) || $column->isFamilyStatus !== true) {
                            if ($column->displayFunction === 'getCustomFields') {
                                $columnData = [$person, $column->displayFunction]($allPersonCustomFields, $CustomMapping, $CustomList, $option_name);
                            } else {
                                $columnData = [$person, $column->displayFunction]();
                            }
                        } else {
                            // For Family Status, compute status from the Family object
                            if (isset($column->isFamilyStatus) && $column->isFamilyStatus === true) {
                                $family = $person->getFamily();
                                $columnData = ($family) ? $family->getStatusText() : gettext('Active');
                            }
                        }
                        
                        // Make family name clickable to family view
                        if ($column->displayFunction === 'getFamilyName') {
                            $familyLink = '<a href="' . SystemURLs::getRootPath() . '/v2/family/' . $person->getFamId() . '">' . InputUtils::escapeHTML($columnData) . '</a>';
                            // Check if family is inactive using Family::isActive()
                            $family = $person->getFamily();
                            if ($family && !$family->isActive()) {
                                echo $familyLink . ' <span class="badge badge-secondary" title="' . gettext('Inactive') . '">';
                                echo '<i class="fa-solid fa-power-off"></i> ' . gettext('Inactive');
                                echo '</span>';
                            } else {
                                echo $familyLink;
                            }
                        }
                        // Make email clickable with mailto link
                        elseif ($column->displayFunction === 'getEmail') {
                            if (!empty($columnData)) {
                                echo '<a href="mailto:' . InputUtils::escapeAttribute($columnData) . '">' . InputUtils::escapeHTML($columnData) . '</a>';
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                        }
                        // Make person name clickable and add gender icon, role, and photo icon
                        elseif (in_array($column->displayFunction, ['getFullName', 'getFirstName', 'getLastName'], true)) {
                            echo '<a href="' . SystemURLs::getRootPath() . '/PersonView.php?PersonID=' . $person->getId() . '" class="font-weight-bold">' . InputUtils::escapeHTML($columnData) . '</a>';
                            // Add role in parentheses
                            $role = $person->getFamilyRoleName();
                            if (!empty($role) && $role !== 'Unassigned') {
                                echo ' <span class="text-muted small">(' . InputUtils::escapeHTML($role) . ')</span>';
                            }
                            // Add gender icon
                            $gender = $person->getGender();
                            if ($gender == 1) { // Male
                                echo ' <i class="fa-solid fa-mars text-primary" title="' . gettext('Male') . '"></i>';
                            } elseif ($gender == 2) { // Female
                                echo ' <i class="fa-solid fa-venus text-danger" title="' . gettext('Female') . '"></i>';
                            }
                            // Add photo icon if person has photo
                            if ($column->displayFunction === 'getFullName' && $person->getPhoto()->hasUploadedPhoto()) {
                                echo ' <button class="btn btn-xs btn-outline-secondary view-person-photo ml-1" data-person-id="' . $person->getId() . '" title="' . gettext('View Photo') . '">';
                                echo '<i class="fa-solid fa-camera"></i>';
                                echo '</button>';
                            }
                        }
                        // Format groups nicely as badges - include hidden JSON for filtering
                        elseif ($column->displayFunction === 'getGroups') {
                            if (is_array($columnData) && !empty($columnData)) {
                                // Always render badges for display
                                foreach ($columnData as $group) {
                                    echo '<span class="badge badge-info mr-1">' . InputUtils::escapeHTML($group) . '</span>';
                                }
                                // Add hidden span with JSON for DataTables filtering
                                echo '<span style="display:none;">' . InputUtils::escapeHTML(json_encode($columnData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) . '</span>';
                            } else {
                                echo '<span class="text-muted">—</span>';
                            }
                        }
                        // Handle Family Status column (hidden for filter)
                        elseif (isset($column->isFamilyStatus) && $column->isFamilyStatus === true) {
                            $family = $person->getFamily();
                            echo ($family) ? InputUtils::escapeHTML($family->getStatusText()) : InputUtils::escapeHTML(gettext('Active'));
                        }
                        // Handle Gender column (hidden for filter) 
                        elseif ($column->displayFunction === 'getGenderName') {
                            $genderValue = $person->getGender();
                            if ($genderValue == 1) {
                                echo 'Male';
                            } elseif ($genderValue == 2) {
                                echo 'Female';
                            } else {
                                echo 'Unassigned';
                            }
                        }
                        // Handle Role column (hidden for filter)
                        elseif ($column->displayFunction === 'getFamilyRoleName') {
                            echo emptyOrUnassigned($columnData);
                        }
                        // Handle Properties column (hidden for filter)
                        elseif ($column->displayFunction === 'getPropertiesString') {
                            if (is_array($columnData) && !empty($columnData)) {
                                // Output as JSON for quote-based filter matching (HTML-escaped to prevent XSS)
                                echo InputUtils::escapeHTML(json_encode($columnData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                            } else {
                                echo 'Unassigned';
                            }
                        }
                        // Handle Custom column (hidden for filter)
                        elseif ($column->displayFunction === 'getCustomFields') {
                            if (is_array($columnData) && !empty($columnData)) {
                                // Output as JSON for quote-based filter matching (HTML-escaped to prevent XSS)
                                echo InputUtils::escapeHTML(json_encode($columnData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                            } else {
                                echo 'Unassigned';
                            }
                        }
                        // Handle Family Status column (hidden for filter)
                        elseif ($column->emptyOrUnassigned === 'true') {
                            if (is_array($columnData)) {
                                echo emptyOrUnassignedJSON($columnData);
                            } else {
                                echo emptyOrUnassigned($columnData);
                            }
                        } else {
                            echo $columnData;
                        }
                    }
                    echo '</td>';
                }
                ?>
                <td class="text-right">
                    <a href='<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getId() ?>'>
                        <button type="button" class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen"></i></button>
                    </a>
                    <?php if (!isset($_SESSION['aPeopleCart']) || !in_array($person->getId(), $_SESSION['aPeopleCart'], false)) { ?>
                        <button type="button" class="AddToCart btn btn-sm btn-primary" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Add to Cart') ?>"><i class="fa-solid fa-cart-plus"></i></button>
                    <?php } else { ?>
                        <button type="button" class="RemoveFromCart btn btn-sm btn-danger" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Remove from Cart') ?>"><i class="fa-solid fa-trash"></i></button>
                    <?php } ?>
                </td>
            </tr>
            <?php
            }
            //lets clean all the customs that don't have anyone associated.
            foreach ($CustomList as $key => $value) {
                if ($value > 0) {
                    $tmp[] = $key;
                }
            }
            $CustomList = $tmp;

            ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >

    var oTable;

    function initializePeopleList() {
        // Prevent double initialization
        if (oTable) {
            return;
        }

        // setup filters
        var filterByClsId = '<?= $filterByClsId ?>';
        var filterByFmrId = '<?= $filterByFmrId ?>';
        var filterByGender = '<?= $filterByGender ?>';

        // Set all i18next translations
        $('#filters-title').text(i18next.t('Filters'));
        $('#label-gender').text(i18next.t('Gender'));
        $('#label-classification').text(i18next.t('Classification'));
        $('#label-role').text(i18next.t('Role'));
        $('#label-properties').text(i18next.t('Properties'));
        $('#label-custom').text(i18next.t('Custom'));
        $('#label-family-status').text(i18next.t('Family Status'));
        $('#label-group').text(i18next.t('Group'));
        $('#clear-filter-text').text(i18next.t('Clear Filter'));
        $('#people-title').text(i18next.t('People'));
        $('#add-all-cart-text').text(i18next.t('Add All to Cart'));
        $('#remove-all-cart-text').text(i18next.t('Remove All from Cart'));

        // setup datatables
        'use strict';
        let dataTableConfig = {
            deferRender: true,
            search: { regex: true },
            columnDefs: [
                <?php
                $columnId = -1;
                $columns = $personListColumns;
                foreach ($columns as $column) {
                    $columnId++;
                    if ($column->visible === 'false') {
                        echo "{ targets: " . $columnId . ", visible: false },\n";
                    }
                }
                ?>
            ],
            columns: [
                <?php
                $firstVisibleColumnId = PHP_INT_MAX;
                $columnId = -1;
                $columnIdMap = [];
                $columns = $personListColumns;
                foreach ($columns as $column) {
                    // Include ALL columns - DataTables needs config for each <th>
                    $columnId++;
                    $columnIdMap[$column->name] = $columnId;
                    $columnTitle = ['title' => $column->name];
                    if ($column->visible !== 'false') {
                        if ($firstVisibleColumnId > $columnId) {
                            $firstVisibleColumnId = $columnId;
                        }
                    }
                    echo json_encode($columnTitle) . ",\n";
                }
                ?>
                {
                    title:i18next.t('Actions'),
                    orderable: false,
                    searchable: false
                }
            ],
            // sort by first visible column
            order: [[ <?php echo $firstVisibleColumnId ?> , "asc" ]]
        }

        $.extend(dataTableConfig, window.CRM.plugin.dataTable);

        oTable = $('#members').DataTable(dataTableConfig);

        $('.filter-Gender').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Gender')
         });
        $('.filter-Classification').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Classification')
        });
        $('.filter-Role').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Role')
        });
        $('.filter-Properties').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Properties')
        });
        $('.filter-Custom').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Custom')
        });
        $('.filter-FamilyStatus').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Family Status')
        });
        $('.filter-Group').select2({
            multiple: true,
            placeholder: i18next.t('Select') + " " + i18next.t('Group')
        });

        $('.filter-Gender').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Gender'] ?>, $(this).select2('data'), true);
        });
        $('.filter-Classification').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Classification'] ?>, $(this).select2('data'), true);
        });
        $('.filter-Role').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Role'] ?>, $(this).select2('data'), true);
        });
        $('.filter-Properties').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Properties'] ?>, $(this).select2('data'), false);
        });
        $('.filter-Custom').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Custom'] ?>, $(this).select2('data'), false);
        });
        $('.filter-FamilyStatus').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Family Status'] ?>, $(this).select2('data'), true);
        });
        $('.filter-Group').on("change", function() {
            filterColumn(<?php echo $columnIdMap['Group'] ?>, $(this).select2('data'), false);
        });

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
        }

        // apply filters
        function filterColumn(col, search, regEx) {
            if (search.length === 0) {
                tmp = [''];
            } else {
                var tmp = [];
                if (regEx) {
                    search.forEach(function(item) {
                        tmp.push('^'+escapeRegExp(item.text)+'$')});
                } else {
                    search.forEach(function(item) {
                    tmp.push('"'+escapeRegExp(item.text)+'"')});
                }
            }
            // join array into string with regex or (|)
            var val = tmp.join('|');
            // apply search
            oTable.column(col).search(val, 1, 0, 1).draw();
        }

        // the following is an example of how we can fill the gender list from the table data
        // client processing can only be done with visible columns in this case because of combined data
        // oTable.columns(8).data().eq(0).unique().sort().each( function ( d, j ) {
        //     $('.filter-Gender').append('<option>'+d+'</option>');
        // });

        // setup external DataTable filters
        var Gender = ['Unassigned', 'Male', 'Female'];  // order: 0=Unassigned, 1=Male, 2=Female
        var shouldTriggerFamilyStatusFilter = false;
        var shouldTriggerGenderFilter = false;
        for (var i = 0; i < Gender.length; i++) {
            if (filterByGender == Gender[i]) {
                $('.filter-Gender').val(i18next.t(Gender[i]));
                $('.filter-Gender').append('<option selected value='+i+'>'+i18next.t(Gender[i])+'</option>');
                shouldTriggerGenderFilter = true;
            } else {
            $('.filter-Gender').append('<option value='+i+'>'+i18next.t(Gender[i])+'</option>');
            }
        }
        // Populate filter option lists via webpack-included initializer
        var serverVars = {
            RoleList: <?= json_encode($RoleList, JSON_THROW_ON_ERROR) ?>,
            PropertyList: <?= json_encode($PropertyList, JSON_THROW_ON_ERROR) ?>,
            CustomList: <?= json_encode($CustomList, JSON_THROW_ON_ERROR) ?>,
            GroupList: <?= json_encode($GroupList, JSON_THROW_ON_ERROR) ?>,
            ClassificationList: <?= json_encode($ClassificationList, JSON_THROW_ON_ERROR) ?>,
            FamilyStatusList: <?= json_encode([gettext('Active'), gettext('Inactive')], JSON_THROW_ON_ERROR) ?>,
            filterByGender: <?= json_encode(isset($_GET['filterByGender']) ? $_GET['filterByGender'] : '', JSON_THROW_ON_ERROR) ?>,
            filterByClsId: <?= json_encode(isset($_GET['filterByClsId']) ? $_GET['filterByClsId'] : '', JSON_THROW_ON_ERROR) ?>,
            filterByFmrId: <?= json_encode(isset($_GET['filterByFmrId']) ? $_GET['filterByFmrId'] : '', JSON_THROW_ON_ERROR) ?>,
            familyActiveStatus: <?= json_encode(isset($_GET['familyActiveStatus']) ? $_GET['familyActiveStatus'] : '', JSON_THROW_ON_ERROR) ?>
        };
        if (window.initializePeopleListFromServer) {
            window.initializePeopleListFromServer(serverVars);
        }

        // Set trigger flags based on incoming URL params
        var shouldTriggerClassificationFilter = (serverVars.filterByClsId !== '');
        var shouldTriggerRoleFilter = (serverVars.filterByFmrId !== '');
        var shouldTriggerGenderFilter = (serverVars.filterByGender !== '');
        if (serverVars.familyActiveStatus === 'active' || serverVars.familyActiveStatus === 'inactive') {
            shouldTriggerFamilyStatusFilter = true;
        }

        // clear external filters
        document.getElementById("ClearFilter").addEventListener("click", function() {

            $('.filter-Gender').val([]).trigger('change')
            $('.filter-Classification').val([]).trigger('change')
            $('.filter-Role').val([]).trigger('change')
            $('.filter-Properties').val([]).trigger('change')
            $('.filter-Custom').val([]).trigger('change')
            $('.filter-FamilyStatus').val([]).trigger('change')
            $('.filter-Group').val([]).trigger('change')
        });

        // Helper function to collect all filtered people IDs from the table
        function collectFilteredPeople() {
            // Guard: ensure oTable is initialized
            if (!oTable || typeof oTable.rows !== 'function') {
                return [];
            }
            
            var listPeople = [];
            var currentPage = oTable.page();
            var currentPageLength = oTable.page.len();
            
            // Temporarily show all rows to ensure all are in DOM
            oTable.page.len(-1).draw(false);
            
            // Get all matching rows and collect their person IDs
            oTable.rows({ search: 'applied' }).every(function () {
                var node = this.node();
                // Find any button with data-cart-id (works for both AddToCart and RemoveFromCart)
                var personId = $(node).find('[data-cart-id]').first().data('cart-id');
                
                if (personId) {
                    listPeople.push(personId);
                }
            });
            
            // Restore pagination to original state
            oTable.page.len(currentPageLength).draw(false);
            oTable.page(currentPage).draw(false);
            
            return listPeople;
        }

        $("#AddAllToCart").click(function(){
            var filteredCount = oTable.rows({ search: 'applied' }).count();
            
            if (filteredCount === 0) {
                window.CRM.cartManager.showNotification('warning', i18next.t('No people to add - filter returned no results'));
                return;
            }
            
            var listPeople = collectFilteredPeople();
            
            if (listPeople.length > 0) {
                window.CRM.cartManager.addPerson(listPeople, {
                    showNotification: true,
                    reloadPage: false,
                    callback: function() {
                        updateCartButtonStates();
                    }
                });
            } else {
                window.CRM.cartManager.showNotification('warning', i18next.t('No people to add - all are already in cart'));
            }
        });

        $("#RemoveAllFromCart").click(function(){
            // Get the count and list of filtered rows that will be removed
            var listPeople = collectFilteredPeople();
            var filteredCount = listPeople.length;
            
            bootbox.confirm({
                title: "Remove from Cart",
                message: i18next.t("Remove") + " " + filteredCount + " " + i18next.t("people from cart?"),
                buttons: {
                    cancel: {
                        label: i18next.t("Cancel")
                    },
                    confirm: {
                        label: i18next.t("Yes, Remove"),
                        className: "btn-danger"
                    }
                },
                callback: function (result) {
                    if (result) {
                        if (listPeople.length > 0) {
                            // Don't pass confirm: true since we already showed bootbox confirmation above
                            window.CRM.cartManager.removePerson(listPeople, {
                                showNotification: true,
                                reloadPage: false,
                                confirm: false,
                                callback: function() {
                                    updateCartButtonStates();
                                }
                            });
                        }
                    }
                }
            });
        });

        // Update button cart states after DataTable draws (page change, filter change, etc)
        oTable.on('draw.dt', function() {
            updateCartButtonStates();
        });
        
        // Function to update button states for all visible rows based on cart status
        function updateCartButtonStates() {
            // Guard: only run if oTable is initialized
            if (!oTable || !window.CRM.APIRequest) {
                return;
            }
            
            // Fetch current cart state from server
            window.CRM.APIRequest({
                method: "GET",
                path: "cart/",
                suppressErrorDialog: true,
            }).done(function(data) {
                // Use CartManager's syncButtonStates to update all buttons
                if (window.CRM.cartManager && window.CRM.cartManager.syncButtonStates) {
                    window.CRM.cartManager.syncButtonStates(
                        data.PeopleCart || [],
                        data.FamiliesInCart || [],
                        data.GroupsInCart || []
                    );
                }
            });
        }
        
        // Apply initial filters from URL parameters now that Select2 and DataTable are ready
        if (filterByGender) {
            var genderIndex = -1;
            // Gender is already set via inline trigger above, just validate
            for (var i = 0; i < Gender.length; i++) {
                if (filterByGender === Gender[i]) {
                    genderIndex = i;
                    break;
                }
            }
        }
        if (filterByClsId) {
            // Already set via inline trigger above
        }
        if (filterByFmrId) {
            // Already set via inline trigger above
        }
        
        // Trigger URL filters after a delay to ensure DataTable is fully initialized
        setTimeout(function() {
            if (shouldTriggerGenderFilter) {
                $('.filter-Gender').trigger('change');
            }
            if (shouldTriggerClassificationFilter) {
                $('.filter-Classification').trigger('change');
            }
            if (shouldTriggerRoleFilter) {
                $('.filter-Role').trigger('change');
            }
            if (shouldTriggerFamilyStatusFilter) {
                $('.filter-FamilyStatus').trigger('change');
            }
        }, 100);
    } // end initializePeopleList

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializePeopleList);
        
        // Handle person photo viewing (same as family list)
        $(document).on('click', '.view-person-photo', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var personId = $(this).data('person-id');
            window.CRM.showPhotoLightbox('person', personId);
        });
    });

</script>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
