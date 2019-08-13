<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
 *  last change : 2016-02-28
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2016 ChurchCRM
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext('Contribution Listing');

// Security: User must have finance permission to use this form
if (!$_SESSION['user']->isFinanceEnabled()) {
    RedirectUtils::Redirect('index.php');
    exit;
}

require 'Include/Header.php';
?>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext('Contributions: '); ?></h3>
      <div class="pull-right">
      <button type="button" class="btn btn-primary" onclick="javascript:document.location='ContributionEditor.php?linkBack=findContributions.php'" id="addNewContrib"><?= gettext('Add New Contribution') ?> </button>
      </div>
  </div>
  <div class="box-body">
    <div class="container-fluid">
      <table class="display responsive nowrap data-table table table-striped table-hover" id="contribTable" width="100%"></table>

      <button type="button" id="deleteSelectedRows" class="btn btn-danger"
              disabled> <?= gettext('Delete Selected Rows') ?> </button>
      <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx"
              disabled><i class="fa fa-download"></i> <?= gettext('Export Selected Rows (OFX)') ?></button>
      <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv"
              disabled><i class="fa fa-download"></i> <?= gettext('Export Selected Rows (CSV)') ?></button>
      <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf"
              disabled> <?= gettext('Generate Deposit Slip for Selected Rows (PDF)') ?></button>
    </div>
  </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FindContributions.js"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $('#deleteSelectedRows').click(function () {
    var deletedRows = dataT.rows('.selected').data()
    bootbox.confirm({
      title:'<?= gettext("Confirm Delete") ?>',
      message: '<p><?= gettext("Are you sure you want to delete the selected"); ?> '+ deletedRows.length + ' <?= gettext("Contribution(s)"); ?>?' +
        '</p><p><?= gettext("This will also delete all payments associated with this contribution"); ?></p>'+
        '<p><?= gettext("This action CANNOT be undone, and may have legal implications!") ?></p>'+
        '<p><?= gettext("Please ensure this what you want to do.") ?></p>',
      buttons: {
        cancel : {
          label: '<?= gettext("Close"); ?>'
        },
        confirm: {
          label: '<?php echo gettext("Delete"); ?>'
        }
      },
      callback: function ( result ) {
        if ( result )
        {
          $.each(deletedRows, function (index, value) {
            window.CRM.APIRequest({
              method: 'DELETE',
              path: 'contrib/' + value.Id
            })
              .done(function (data) {
                dataT.rows('.selected').remove().draw(false);
              });
          });
        }
      }
    });
  });
</script>

<?php require "Include/Footer.php" ?>
