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
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
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
                    <i class="fa-solid fa-minus-circle"></i> <span id="remove-all-cart-text"></span>
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
                        <button type="button" class="btn btn-sm btn-info" title="<?= gettext('View') ?>"><i class="fa-solid fa-eye fa-sm"></i></button>
                    </a>
                    <a href='<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getId() ?>'>
                        <button type="button" class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen fa-sm"></i></button>
                    </a>

                    <?php if (!isset($_SESSION['aPeopleCart']) || !in_array($person->getId(), $_SESSION['aPeopleCart'], false)) {
                        ?>
                            <button type="button" class="AddToCart btn btn-sm btn-primary" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Add to Cart') ?>"><i class="fa-solid fa-cart-plus fa-sm"></i></button>
                        <?php
                    } else {
                        ?>
                        <button type="button" class="RemoveFromCart btn btn-sm btn-danger" data-cart-id="<?= $person->getId() ?>" data-cart-type="person" title="<?= gettext('Remove from Cart') ?>"><i class="fa-solid fa-times fa-sm"></i></button>
                        <?php
                    } ?>
                    </td>

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
        var Gender = ['Unassigned', 'Male', 'Female'];  // order: 0=Unassigned, 1=Male, 2=Female
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
        var ClassificationList = <?= json_encode($ClassificationList, JSON_THROW_ON_ERROR) ?>;
        var shouldTriggerClassificationFilter = false;
        for (var i = 0; i < ClassificationList.length; i++) {
            // apply initial filters if applicable
            if (filterByClsId == ClassificationList[i]) {
                $('.filter-Classification').val(ClassificationList[i]);
                $('.filter-Classification').append('<option selected value='+i+'>'+ClassificationList[i]+'</option>');
                shouldTriggerClassificationFilter = true;
            } else {
               $('.filter-Classification').append('<option value='+i+'>'+ClassificationList[i]+'</option>');
            }
        }

        var RoleList = <?= json_encode($RoleList, JSON_THROW_ON_ERROR) ?>;
        var shouldTriggerRoleFilter = false;
        for (var i = 0; i < RoleList.length; i++) {
            if (filterByFmrId == RoleList[i]) {
                $('.filter-Role').val(RoleList[i]);
                $('.filter-Role').append('<option selected value='+i+'>'+RoleList[i]+'</option>');
                shouldTriggerRoleFilter = true;
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
        }, 100);
    } // end initializePeopleList

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializePeopleList);
    });

</script>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
