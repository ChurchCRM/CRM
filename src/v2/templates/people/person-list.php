<?php


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

<div class="pull-right">
  <a class="btn btn-success" role="button" href="<?= SystemURLs::getRootPath()?>/PersonEditor.php">
    <span class="fa fa-plus" aria-hidden="true"></span><?= gettext('Add Person') ?>
  </a>
</div>
<p><br/><br/></p>
<div class="box">
    <div class="box-body">
        <table id="members" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
            <thead>
            <tr>
                <th><?= gettext('Actions') ?></th>
                <th><?= gettext('Name') ?></th>
                <?= SystemConfig::getValue('bHidePersonAddress') ?'': '<th>' . gettext('Address') . '</th>' ?>
                <th><?= gettext('Home Phone') ?></th>
                <th><?= gettext('Cell Phone') ?></th>
                <th><?= gettext('Email') ?></th>
                <th><?= gettext('Gender') ?></th>
                <th><?= gettext('Classification') ?></th>
                <th><?= gettext('Roles') ?></th>
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

              <td><?= $person->getFormattedName(SystemConfig::getValue('iPersonNameStyle')) ?></td>
                <?= SystemConfig::getValue('bHidePersonAddress') ?'': '<td>' . $person->getAddress() . '</hd>' ?>
                <td><?= $person->getHomePhone() ?></td>
                <td><?= $person->getCellPhone() ?></td>
                <td><?= $person->getEmail() ?></td>
                <td><?= $person->getGenderName() ?></td>
                <td><?= $person->getClassificationName() ?></td>
                <td><?= $person->getFamilyRoleName() ?></td>
                <?php
}
                ?>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" >

    $(document).ready(function() {

        'use strict';
                        
        var oTable;
        
        oTable = $('.table').DataTable({
            //stateSave: true,
            "language": {
                "url": "<?= SystemURLs::getRootPath() . '/locale/datatables/' . Bootstrapper::GetCurrentLocale()->getDataTables() ?>.json"
            },
            responsive: true,
            // sortby name
            order: [[ 1, "asc" ]],
            // setup location of table control elements
            dom: "<'row'<'col-sm-4'<?= SessionUser::getUser()->isCSVExport() ? "B" : "" ?>><'col-sm-4'r><'col-sm-4 searchStyle'f>>" +
                                "<'row'<'col-sm-12't>>" +
                                "<'row'<'col-sm-4'l><'col-sm-4'i><'col-sm-4'p>>",
            // the following will insure header is exported properly
            buttons: [
            { 
                extend: 'copy',
                exportOptions: {
                    columns: [1,2,3,4,5,6,7],
                    format: { 
                        header: function ( data, column, row ) 
                            {
                            return data.split('<')[0]; 
                            }
                    }
                }
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: [1,2,3,4,5,6,7],
                    format: { 
                        header: function ( data, column, row ) 
                            {
                            return data.split('<')[0]; 
                            }
                    }
                }
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: [1,2,3,4,5,6,7],
                    format: { 
                        header: function ( data, column, row ) 
                            {
                            return data.split('<')[0]; 
                            }
                    }
                }
            },
            {
                extend: 'pdf',
                exportOptions: {
                    columns: [1,2,3,4,5,6,7],
                    format: { 
                        header: function ( data, column, row ) 
                            {
                            return data.split('<')[0]; 
                            }
                    }
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: [1,2,3,4,5,6,7],
                    format: { 
                        header: function ( data, column, row ) 
                            {
                            return data.split('<')[0]; 
                            }
                    }
                }
            }
        ]
        });
        
        // show multi select
        yadcf.init(oTable, [{
            column_number: 5,
            filter_type: "multi_select",
            select_type: 'select2',
            filter_match_mode : "exact"
        }, {
            column_number: 6,
            filter_type: "multi_select",
            select_type: 'select2',
            filter_match_mode : "exact"
        }, {
            column_number: 7,
            filter_type: "multi_select",
            select_type: 'select2',
            filter_match_mode : "exact"
        }]);
        
    });

</script>

<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
?>
