<?php
/*******************************************************************************
*
*  filename    : person-list.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright 2019 Troy Smith
*
******************************************************************************/

use ChurchCRM\dto\SystemConfig;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\SessionUser;
use ChurchCRM\Bootstrapper;

//Set the page title
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('List');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="box box-primary">
    <div class="box-header">
        <?= gettext('Filter and Cart') ?>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-6">
                <div class='external-filter'>
                <!-- <label>Gender:</label> -->
                <span style="margin: 5px; display:inline-block;"id="filter-07"></span>
                <!-- <label>Classification:</label> -->
                <span style="margin: 5px; display:inline-block;"id="filter-08"></span>
                <!-- <label>Family Role:</label> -->
                <span style="margin: 5px; display:inline-block;"  id="filter-09"></span>
                <!-- <label>Contact Properties:</label> -->
                <span style="margin: 5px; display:inline-block;"  id="filter-10"></span>
                <!-- <label>Custom Fields:</label> -->
                <span style="margin: 5px; display:inline-block;"  id="filter-11"></span>
                <!-- <label>Group Types:</label> -->
                <span style="margin: 5px; display:inline-block;"  id="filter-12"></span>
                <input style="margin: 20px" id="ClearFilter" type="button" class="btn btn-default" value="<?= gettext('Clear Filter') ?>"><BR><BR>
                </div>
            </div>

            <div class= "col-lg-6">
                <a class="btn btn-success" role="button" href="<?= SystemURLs::getRootPath()?>/PersonEditor.php"><span class="fa fa-plus" aria-hidden="true"></span><?= gettext('Add Person') ?></a>
                <a id="AddAllToCart" class="btn btn-primary" ><?= gettext('Add All to Cart') ?></a>
                <!-- <input name="IntersectCart" type="submit" class="btn btn-warning" value="< ?= gettext('Intersect with Cart') ?>">&nbsp; -->
                <a id="RemoveAllFromCart" class="btn btn-danger" ><?= gettext('Remove All from Cart') ?></a>
            </div>
        </div>
    </div>

</div>
<p><br/><br/></p>
<div class="box box-warning">
    <div class="box-body">
        <table id="members" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
            <thead>
            <tr>
                <th><?= gettext('Actions') ?></th>
                <th><?= gettext('Id') ?></th>
                <th><?= gettext('Name') ?></th>
                <th><?= gettext('Address') ?></th>
                <th><?= gettext('Home Phone') ?></th>
                <th><?= gettext('Cell Phone') ?></th>
                <th><?= gettext('Email') ?></th>
                <th><?= gettext('Gender') ?></th>
                <th><?= gettext('Classification') ?></th>
                <th><?= gettext('Roles') ?></th>
                <th><?= gettext('Properties') ?></th>
                <th><?= gettext('Custom') ?></th>
                <th><?= gettext('Group Types') ?></th>
            </tr>
            </thead>
            <tbody>

            <!--Populate the table with person details -->
            <?php foreach ($members as $person) {
              /* @var $members ChurchCRM\people */

    ?>
            <tr>
              <td><a href='<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $person->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-search-plus fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>
                    <a href='<?= SystemURLs::getRootPath()?>/PersonEditor.php?PersonID=<?= $person->getId() ?>'>
                        <span class="fa-stack">
                            <i class="fa fa-square fa-stack-2x"></i>
                            <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                        </span>
                    </a>

                    <?php if (!isset($_SESSION['aPeopleCart']) || !in_array($per_ID, $_SESSION['aPeopleCart'], false)) {
                            ?>
                          <a class="AddToPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                        <span class="fa-stack">
                                    <i class="fa fa-square fa-stack-2x"></i>
                                    <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
                                </span>
                            </a>
                        </td>
                      <?php
                        } else {
                            ?>
                        <a class="RemoveFromPeopleCart" data-cartpersonid="<?= $person->getId() ?>">
                        <span class="fa-stack">
                                    <i class="fa fa-square fa-stack-2x"></i>
                                    <i class="fa fa-remove fa-stack-1x fa-inverse"></i>
                                </span>
                            </a>
                            <?php
                        }
                            ?>
                <td><?= $person->getId() ?></td>
                <td><?= $person->getFormattedName(SystemConfig::getValue('iPersonNameStyle')) ?></td>
                <td><?= $person->getAddress() ?></td>
                <td><?= $person->getHomePhone() ?></td>
                <td><?= $person->getCellPhone() ?></td>
                <td><?= $person->getEmail() ?></td>
                <td><?= empty($person->getGenderName()) ? 'Unassigned': $person->getGenderName() ?></td>
                <td><?= empty($person->getClassificationName()) ? 'Unassigned' : $person->getClassificationName() ?></td>
                <td><?= empty($person->getFamilyRoleName()) ? 'Unassigned': $person->getFamilyRoleName() ?></td>
                <td><?= empty($person->getPropertiesString()) ? 'Unassigned': $person->getPropertiesString() ?></td>
                <td><?= empty($person->getCustomFields()) ? 'Unassigned': $person->getCustomFields() ?></td>
                <td><?= empty($person->getGroups()) ? 'Unassigned': $person->getGroups() ?></td>
                <?php
}
                ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >

    var oTable;

    $(document).ready(function() {
        // get $fp array from people.php
        var FamilyProperties = JSON.parse('<?= $fp ?>');

        // get $cL array from people.php
        var CustomFields = JSON.parse('<?= $cl ?>');

        var GroupTypes = JSON.parse('<?= $gl ?>');
        // setup datatables
        'use strict';
        var bVisible = parseInt("<?= SystemConfig::getValue('bHidePersonAddress') ? 0 : 1 ?>");

        oTable = $('#members').DataTable({
            columns: [null,{visible:false},null,{visible:bVisible},null,null,null,null,null,null,{visible:false},{visible:false},{visible:false}],
            "language": {
                url: "<?= SystemURLs::getRootPath() . '/locale/datatables/' . Bootstrapper::GetCurrentLocale()->getDataTables() ?>.json"
            },
            responsive: true,
            // sortby name
            order: [[ 2, "asc" ]],
            // setup location of table control elements
            dom: "<'row'<'col-sm-4'<?= SessionUser::getUser()->isCSVExport() ? "B" : "" ?>><'col-sm-4'r><'col-sm-4 searchStyle'f>>" +
                                "<'row'<'col-sm-12't>>" +
                                "<'row'<'col-sm-4'l><'col-sm-4'i><'col-sm-4'p>>",
        });

        yadcf.init(oTable, [{
            column_number: [7],
            filter_type: 'multi_select',
            select_type: 'select2',
            filter_container_id: 'filter-07',
            filter_default_label: 'Gender',
            filter_match_mode : "exact",
            select_type_options: {width: '190px'}
        }, {
            column_number: [8],
            filter_type: 'multi_select',
            select_type: 'select2',
            filter_container_id: 'filter-08',
            filter_default_label: 'Classification',
            filter_match_mode : "exact",
            select_type_options: {width: '190px'}
        }, {
            column_number: [9],
            filter_type: 'multi_select',
            select_type: 'select2',
            filter_container_id: 'filter-09',
            filter_default_label: 'Family Role',
            filter_match_mode : "exact",
            select_type_options: {width: '190px'}
        }, {
            column_number: [10],
            filter_container_id: 'filter-10',
            filter_type: 'multi_select',
            select_type: 'select2', 
            data: FamilyProperties, 
            filter_default_label: "Family Properties",
            select_type_options: {width: '190px'}
        }, {
            column_number: [11],
            filter_container_id: 'filter-11',
            filter_type: 'multi_select',
            select_type: 'select2',
            data: CustomFields, 
            filter_default_label: "Custom Field",
            select_type_options: {width: '190px'}
        }, {
            column_number: [12],
            filter_container_id: 'filter-12',
            filter_type: 'multi_select',
            select_type: 'select2',
            data: GroupTypes, 
            filter_default_label: "Group Types",
            select_type_options: {width: '190px'}
        }
    ]);

    });

    document.getElementById("ClearFilter").addEventListener("click", function() {yadcf.exResetAllFilters(oTable,true);});
    
    document.getElementById("AddAllToCart").addEventListener("click", function() {
        var listPeople = [];
        var table = $('#members').DataTable().rows( { filter: 'applied' } ).every( function () {
        // fill array
        var row = this.data();
        listPeople.push(row[1]);
    });
        // bypass SelectList.js
        window.CRM.cart.addPerson(listPeople);
    });

    document.getElementById("RemoveAllFromCart").addEventListener("click", function() {
        var listPeople = [];
        var table = $('#members').DataTable().rows( { filter: 'applied' } ).every( function () {
        // fill array
        var row = this.data();
        listPeople.push(row[1]);
    });
        // bypass SelectList.js
        window.CRM.cart.removePerson(listPeople);
    });

</script>

<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
?>
