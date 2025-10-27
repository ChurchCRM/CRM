<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomMasterQuery;
use ChurchCRM\model\ChurchCRM\PropertyQuery;

/**
 * This will avoid to call the db twice one to check if empty the other one to return the value
 * no caching was being done by the ORM so lets keep the value and return if not empty
 *
 * @var mixed $stuff
 */
function emptyOrUnassigned($stuff)
{
    return empty($stuff) ? 'Unassigned' : $stuff;
}

/**
 * Same as previous but return json encoded
 *
 * @var mixed $stuff
 */
function emptyOrUnassignedJSON($stuff): string
{
    return empty($stuff) ? 'Unassigned' : json_encode($stuff, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Listing');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
// Classification list
$ListItem =  ListOptionQuery::create()->select('OptionName')->filterById(1)->find()->toArray();
$ClassificationList[] = "Unassigned";
foreach ($ListItem as $element) {
    $ClassificationList[] = $element;
}
// Role list
$ListItem = ListOptionQuery::create()->select('OptionName')->filterById(2)->find()->toArray();
$RoleList[] = "Unassigned";
foreach ($ListItem as $element) {
    $RoleList[] = $element;
}
// Person properties list
$ListItem = PropertyQuery::create()->filterByProClass("p")->find();
$PropertyList[] = "Unassigned";
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

// Setting unassigned to 1 so it is not deleted
$CustomList["Unassigned"] = 1;

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
$GroupList[] = "Unassigned";
foreach ($ListItem as $element) {
    $GroupList[] = $element->getName();
}

?>

<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-filter"></i> <span id="filters-title"></span></h3>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-lg-6">
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

<div class="card card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-users"></i> <span id="people-title"></span></h3>
        <div class="card-tools">
            <div class="btn-group btn-group-sm" role="group">
                <a id="AddAllToCart" class="btn btn-success">
                    <i class="fa-solid fa-cart-plus"></i> <span id="add-all-cart-text"></span>
                </a>
                <a id="RemoveAllFromCart" class="btn btn-danger">
                    <i class="fa-solid fa-cart-arrow-down"></i> <span id="remove-all-cart-text"></span>
                </a>
            </div>
        </div>
    </div>
    <div class="card-body">
        <table id="members" class="table table-striped table-bordered data-table w-100">
            <tbody>
            <!--Populate the table with person details -->
            <?php foreach ($members as $person) {
              /* @var $members ChurchCRM\people */

                ?>
            <tr>
              <td>
                    <a href='<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $person->getId() ?>'>
                        <button type="button" class="btn btn-xs btn-default" title="<?= gettext('View') ?>"><i class="fa-solid fa-search-plus"></i></button>
                    </a>
                    <a href='<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getId() ?>'>
                        <button type="button" class="btn btn-xs btn-default" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen"></i></button>
                    </a>

                    <?php if (!isset($_SESSION['aPeopleCart']) || !in_array($person->getId(), $_SESSION['aPeopleCart'], false)) {
                        ?>
                            <button type="button" class="AddToCart btn btn-xs btn-primary" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Add to Cart') ?>"><i class="fa-solid fa-cart-plus"></i></button>
                        </td>
                        <?php
                    } else {
                        ?>
                        <button type="button" class="RemoveFromCart btn btn-xs btn-danger" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Remove from Cart') ?>"><i class="fa-solid fa-shopping-cart"></i></button>
                        </td>
                        <?php
                    } ?>

                <?php
                $columns = json_decode(SystemConfig::getValue('sPersonListColumns'), null, 512, JSON_THROW_ON_ERROR);
                foreach ($columns as $column) {
                    echo '<td>';
                    if ($column->displayFunction === 'getCustomFields') {
                        $columnData = [$person, $column->displayFunction]($allPersonCustomFields, $CustomMapping, $CustomList, $option_name);
                    } else {
                        $columnData = [$person, $column->displayFunction]();
                    }
                    if ($column->emptyOrUnassigned === 'true') {
                        if (is_array($columnData)) {
                            echo emptyOrUnassignedJSON($columnData);
                        } else {
                            echo emptyOrUnassigned($columnData);
                        }
                    } else {
                        echo $columnData;
                    }
                    echo '</td>';
                }
            }
            //lets clean all the customs that don't have anyone associated.
            foreach ($CustomList as $key => $value) {
                if ($value > 0) {
                    $tmp[] = $key;
                }
            }
            $CustomList = $tmp;

            ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >

    var oTable;

    $(document).ready(function() {

        // Set all i18next translations
        $('#filters-title').text(i18next.t('Filters'));
        $('#label-gender').text(i18next.t('Select Gender'));
        $('#label-classification').text(i18next.t('Select Classification'));
        $('#label-role').text(i18next.t('Select Role'));
        $('#label-properties').text(i18next.t('Select Properties'));
        $('#label-custom').text(i18next.t('Select Custom'));
        $('#label-group').text(i18next.t('Select Group'));
        $('#clear-filter-text').text(i18next.t('Clear Filter'));
        $('#people-title').text(i18next.t('People'));
        $('#add-all-cart-text').text(i18next.t('Add All to Cart'));
        $('#remove-all-cart-text').text(i18next.t('Remove All from Cart'));

        // setup filters
        var filterByClsId = '<?= $filterByClsId ?>';
        var filterByFmrId = '<?= $filterByFmrId ?>';
        var filterByGender = '<?= $filterByGender ?>';

        // setup datatables
        'use strict';
        let dataTableConfig = {
            deferRender: true,
            search: { regex: true },
            columns: [
                {
                    title:i18next.t('Actions'),
                },
                <?php
                $firstVisibleColumnId = PHP_INT_MAX;
                $columnId = 0;
                $columnIdMap = [];
                $columns = json_decode(SystemConfig::getValue('sPersonListColumns'), null, 512, JSON_THROW_ON_ERROR);
                foreach ($columns as $column) {
                    $columnId++;
                    $columnIdMap[$column->name] = $columnId;
                    $columnTitle = ['title' => "i18next.t('{$column->name}')"];
                    if ($column->visible === 'false') {
                        $columnTitle['visible'] = 'false';
                    } else {
                        if ($firstVisibleColumnId > $columnId) {
                            $firstVisibleColumnId = $columnId;
                        }
                    }
                    echo str_replace('"', '', json_encode($columnTitle)) . ",\n";
                }
                ?>
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
        var Gender = ['Male', 'Female', 'Unassigned'];
        for (var i = 0; i < Gender.length; i++) {
            if (filterByGender == Gender[i]) {
                $('.filter-Gender').val(i18next.t(Gender[i]));
                $('.filter-Gender').append('<option selected value='+i+'>'+i18next.t(Gender[i])+'</option>');
                $('.filter-Gender').trigger('change')
            } else {
            $('.filter-Gender').append('<option value='+i+'>'+i18next.t(Gender[i])+'</option>');
            }
        }
        var ClassificationList = <?= json_encode($ClassificationList, JSON_THROW_ON_ERROR) ?>;
        for (var i = 0; i < ClassificationList.length; i++) {
            // apply initinal filters if applicable
            if (filterByClsId == ClassificationList[i]) {
                $('.filter-Classification').val(ClassificationList[i]);
                $('.filter-Classification').append('<option selected value='+i+'>'+ClassificationList[i]+'</option>');
                $('.filter-Classification').trigger('change')
            } else {
               $('.filter-Classification').append('<option value='+i+'>'+ClassificationList[i]+'</option>');
            }
        }

        var RoleList = <?= json_encode($RoleList, JSON_THROW_ON_ERROR) ?>;
        for (var i = 0; i < RoleList.length; i++) {
            if (filterByFmrId == RoleList[i]) {
                $('.filter-Role').val(RoleList[i]);
                $('.filter-Role').append('<option selected value='+i+'>'+RoleList[i]+'</option>');
                $('.filter-Role').trigger('change')
            } else {
                $('.filter-Role').append('<option value='+i+'>'+RoleList[i]+'</option>');
            }
        }
        var PropertyList = <?= json_encode($PropertyList, JSON_THROW_ON_ERROR) ?>;
        for (var i = 0; i < PropertyList.length; i++) {
            $('.filter-Properties').append('<option value='+i+'>'+PropertyList[i]+'</option>');
        }
        var CustomList = <?=  json_encode($CustomList, JSON_THROW_ON_ERROR) ?>;
        for (var i = 0; i < CustomList.length; i++) {
            $('.filter-Custom').append('<option value='+i+'>'+CustomList[i]+'</option>');
        }
        var GroupList = <?= json_encode($GroupList, JSON_THROW_ON_ERROR) ?>;
        for (var i = 0; i < GroupList.length; i++) {
            $('.filter-Group').append('<option value='+i+'>'+GroupList[i]+'</option>');
        }

        // apply initinal filters if applicable
        // $('.filter-Classification').val(filterByClsId);
        // $('.filter-Classification').trigger('change')

        // clear external filters
        document.getElementById("ClearFilter").addEventListener("click", function() {

            $('.filter-Gender').val([]).trigger('change')
            $('.filter-Classification').val([]).trigger('change')
            $('.filter-Role').val([]).trigger('change')
            $('.filter-Properties').val([]).trigger('change')
            $('.filter-Custom').val([]).trigger('change')
            $('.filter-Group').val([]).trigger('change')
        });
    }); // end document ready

    $("#AddAllToCart").click(function(){
        var listPeople = [];
        // Get all visible rows from the filtered table
        $('#members').DataTable().rows({ filter: 'applied' }).every(function () {
            // Get the row node (DOM element)
            var node = this.node();
            // Find the AddToCart button in this row and get its person ID
            var personId = $(node).find('.AddToCart').data('cart-id');
            if (personId) {
                listPeople.push(personId);
            }
        });
        
        if (listPeople.length > 0) {
            // Use CartManager with notifications and automatic page reload
            window.CRM.cartManager.addPerson(listPeople, {
                showNotification: true,
                reloadPage: true
            });
        } else {
            // Show notification that no people to add
            window.CRM.cartManager.showNotification('warning', i18next.t('No people to add - all are already in cart'));
        }
    });

    $("#RemoveAllFromCart").click(function(){
        var listPeople = [];
        // Get all visible rows from the filtered table
        $('#members').DataTable().rows({ filter: 'applied' }).every(function () {
            // Get the row node (DOM element)
            var node = this.node();
            // Find the RemoveFromCart button in this row and get its person ID
            var personId = $(node).find('.RemoveFromCart').data('cart-id');
            if (personId) {
                listPeople.push(personId);
            }
        });
        
        
        if (listPeople.length > 0) {
            // Use CartManager with confirmation, notifications, and automatic page reload
            window.CRM.cartManager.removePerson(listPeople, {
                confirm: true,
                showNotification: true,
                reloadPage: true
            });
        } else {
            // Show notification that no people to remove
            window.CRM.cartManager.showNotification('warning', i18next.t('No people in cart to remove'));
        }
    });

</script>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
