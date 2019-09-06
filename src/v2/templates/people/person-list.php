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
use ChurchCRM\ListOptionQuery;
use ChurchCRM\GroupQuery;

use ChurchCRM\PropertyQuery;
use ChurchCRM\PersonCustomMasterQuery;

//Set the page title
$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Listing');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
// classification list
$ListItem =  ListOptionQuery::create()->select('OptionName')->filterById(1)->find()->toArray();
$ClassificationList[] = "Unassigned";
foreach($ListItem as $element) {
    $ClassificationList[] = $element;
}
// role list
$ListItem = ListOptionQuery::create()->select('OptionName')->filterById(2)->find()->toArray();
$RoleList[] = "Unassigned";
foreach($ListItem as $element) {
    $RoleList[] = $element;
}
// person properties list
$ListItem = PropertyQuery::create()->filterByProClass("p")->find();
$PropertyList[] = "Unassigned";
foreach($ListItem as $element) {
    $PropertyList[] = $element->getProName();
}
// person custom list
$ListItem = PersonCustomMasterQuery::create()->select('Name')->find();
$CustomList[] = "Unassigned";
foreach ($ListItem as $element) {
    $CustomList[] = $element->getName();
}
// get person group list
$ListItem = GroupQuery::create()->find();
$GroupList[] = "Unassigned";
foreach ($ListItem as $element) {
    $GroupList[] = $element->getName();
}

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
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Gender" multiple="multiple"></select>
                <!-- <label>Classification:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Classification" multiple="multiple"></select>
                <!-- <label>Family Role:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Role"multiple="multiple"></select>
                <!-- <label>Properties:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Properties" multiple="multiple"></select>
                <!-- <label>Custom Fields:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Custom" multiple="multiple"></select>
                <!-- <label>Group Types:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Group" multiple="multiple"></select> 
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
                <td><?= $person->getLastName()?></td>
                <td><?= $person->getFirstName()?></td>
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

        // setup filters
        var filterByClsId = '<?= $filterByClsId ?>';
        var filterByFmrId = '<?= $filterByFmrId ?>';
        var filterByGender = '<?= $filterByGender ?>';

        // setup datatables
        'use strict';
        var bVisible = parseInt("<?= SystemConfig::getValue('bHidePersonAddress') ? 0 : 1 ?>");

        oTable = $('#members').DataTable({
            deferRender: true,
            search: {
            regex: true,
                  },
            columns: [
                {
                    title:i18next.t('Actions'), 
                },
                {
                    title:i18next.t('Id'),
                    visible:false
                },
                {
                    title:i18next.t('Last Name'),
                },
                {
                    title:i18next.t('First Name'),
                },
                {
                    title:i18next.t('Address'),
                    visible:bVisible
                },
                {
                    title:i18next.t('Home Phone'),
                },
                {
                    title:i18next.t('Cell Phone'),
                },
                {
                    title:i18next.t('Email'),
                },
                {
                    title:i18next.t('Gender'),
                },
                {
                    title:i18next.t('Classification'),
                },
                {
                    title:i18next.t('Roles'),
                },
                {
                    title:i18next.t('Properties'),
                    visible:false
                },
                {
                    title:i18next.t('Custom'),
                    visible:false
                },
                {
                    title:i18next.t('Group'),
                    visible:false
                }
            ],
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

         
        $('.filter-Gender').select2({
            multiple: true,
            placeholder: "Select Gender",
        });
        $('.filter-Classification').select2({
            multiple: true,
            placeholder: "Select Classification"
        });
        $('.filter-Role').select2({
            multiple: true,
            placeholder: "Select Role"
        });
        $('.filter-Properties').select2({
            multiple: true,
            placeholder: "Select Properties"
        });
        $('.filter-Custom').select2({
            multiple: true,
            placeholder: "Select Custom"
        });
        $('.filter-Group').select2({
            multiple: true,
            placeholder: "Select Group"
        });

        $('.filter-Gender').on("change", function() {
            filterColumn(8, $(this).select2('data'), true);
        });
        $('.filter-Classification').on("change", function() {
            filterColumn(9, $(this).select2('data'), true);
        });
        $('.filter-Role').on("change", function() {
            filterColumn(10, $(this).select2('data'), true);
        });
        $('.filter-Properties').on("change", function() {
            filterColumn(11, $(this).select2('data'), false);
        });
        $('.filter-Custom').on("change", function() {
            filterColumn(12, $(this).select2('data'), false);
        });
        $('.filter-Group').on("change", function() {
            filterColumn(13, $(this).select2('data'), false);
        });

        // apply filters
        function filterColumn(col, search, regEx) {
            if (search.length === 0) {
                tmp = [''];
            } else {
                var tmp = [];
                if (regEx) {
                    search.forEach(function(item) {
                        tmp.push('^'+item.text+'$')});
                } else {
                    search.forEach(function(item) {
                    tmp.push(item.text)});
                }
            }
            

            // join array into string with regex or (|)
                var val = tmp.join('|');
            // apply search
            oTable.column(col).search(val, 1, 0).draw();
        
            
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
                $('.filter-Gender').val(Gender[i]);
                $('.filter-Gender').append('<option selected value='+i+'>'+Gender[i]+'</option>');
                $('.filter-Gender').trigger('change')
            } else { 
            $('.filter-Gender').append('<option value='+i+'>'+Gender[i]+'</option>');
            }
        }
        var ClassificationList = JSON.parse('<?= json_encode($ClassificationList) ?>');
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
        
        var RoleList = JSON.parse('<?= json_encode($RoleList) ?>');
        for (var i = 0; i < RoleList.length; i++) {
            if (filterByFmrId == RoleList[i]) {
                $('.filter-Role').val(RoleList[i]);
                $('.filter-Role').append('<option selected value='+i+'>'+RoleList[i]+'</option>');
                $('.filter-Role').trigger('change')
            } else {
                $('.filter-Role').append('<option value='+i+'>'+RoleList[i]+'</option>');
            }
        }
        var PropertyList = JSON.parse('<?= json_encode($PropertyList) ?>');
        for (var i = 0; i < PropertyList.length; i++) {
            $('.filter-Properties').append('<option value='+i+'>'+PropertyList[i]+'</option>');
        }
        var CustomList = JSON.parse('<?= json_encode($CustomList) ?>');
        for (var i = 0; i < CustomList.length; i++) {
            $('.filter-Custom').append('<option value='+i+'>'+CustomList[i]+'</option>');
        }
        var GroupList = JSON.parse('<?= json_encode($GroupList) ?>');
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
