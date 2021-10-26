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
use ChurchCRM\Authentication\AuthenticationManager;

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext('Deposit Listing');

// Security: User must have finance permission to use this form
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::Redirect('index.php');
    exit;
}

require 'Include/Header.php';
?>

<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?php echo gettext('Add New Deposit: '); ?></h3>
  </div>
  <div class="card-body">
    <form action="#" method="get" class="form">
      <div class="row">
        <div class="container-fluid">
          <div class="col-lg-4">
            <label for="depositComment"><?= gettext('Deposit Comment') ?></label>
            <input class="form-control newDeposit" name="depositComment" id="depositComment" style="width:100%">
          </div>
          <div class="col-lg-3">
            <label for="depositType"><?= gettext('Deposit Type') ?></label>
            <select class="form-control" id="depositType" name="depositType" disabled>
              <option value="Bank"><?= gettext('Bank') ?></option>
            </select>
          </div>
          <div class="col-lg-3">
            <label for="depositDate"><?= gettext('Deposit Date') ?></label>
            <input class="form-control" name="depositDate" id="depositDate" style="width:100%" class="date-picker">
          </div>
        </div>
      </div>
      <p>
      <div class="row">
        <div class="col-xs-3">
          <button type="button" class="btn btn-primary" id="addNewDeposit"><?= gettext('Add New Deposit') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?php echo gettext('Deposits: '); ?></h3>
  </div>
  <div class="card-body">
    <div class="container-fluid">
      <table class="display responsive nowrap data-table table table-striped table-hover" id="depositsTable" width="100%"></table>

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

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FindDepositSlip.js"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  $('#deleteSelectedRows').click(function () {
    var deletedRows = dataT.rows('.selected').data()
    bootbox.confirm({
      title:'<?= gettext("Confirm Delete") ?>',
      message: '<p><?= gettext("Are you sure you want to delete the selected"); ?> '+ deletedRows.length + ' <?= gettext("Deposit(s)"); ?>?' +
        '</p><p><?= gettext("This will also delete all payments associated with this deposit"); ?></p>'+
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
              path: 'deposits/' + value.Id
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
