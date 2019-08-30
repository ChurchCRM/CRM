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
use ChurchCRM\Utils\InputUtils;

// $iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext('Contribution Listing');

// Security: User must have finance permission to use this form
if (!$_SESSION['user']->isFinanceEnabled()) {
    RedirectUtils::Redirect('index.php');
    exit;
}

require 'Include/Header.php';

$iDepositSlipID = 0;
if (array_key_exists('DepositSlipID', $_GET)) {
    $iDepositSlipID = InputUtils::LegacyFilterInput($_GET['DepositSlipID'], 'int');
}

$linkBack = InputUtils::LegacyFilterInput($_GET['linkBack'], 'string');

?>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext('Contributions: '); ?></h3>
      <div class="pull-right">
      <button type="button" class="btn btn-primary" id="addNewContrib"><?= gettext('Add New Contribution') ?> </button>
      </div>
  </div>
  <div class="box-body">
    <div class="container-fluid">
      <table class="display responsive nowrap data-table table table-striped table-hover" id="contribTable" width="100%"></table>
        <div id="contribButton" style="display:none">
          <button type="button" id="deleteSelectedRows" class="btn btn-danger"
                  disabled> <?= gettext('Delete Selected Rows') ?> </button>
          <!-- <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx"
                  disabled><i class="fa fa-download"></i> < ?= gettext('Export Selected Rows (OFX)') ?></button> -->
          <!-- <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv"
                  disabled><i class="fa fa-download"></i> < ?= gettext('Export Selected Rows (CSV)') ?></button> -->
          <!-- <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf"
                  disabled> < ?= gettext('Generate Deposit Slip for Selected Rows (PDF)') ?></button> -->
          <!-- <button type="button" id="AddAllToCart" class="btn btn-primary">< ?= gettext('Add All to Cart') ?></button>
          <button type="button" id="RemoveAllFromCart" class="btn btn-danger">< ?= gettext('Remove All from Cart') ?></button> -->
          
        </div>
        <div id="depositButton" style="display:none">
          <button type="button" id="AddToDeposit" class="btn btn-primary"><?= gettext('Add to Deposit (#' . $iDepositSlipID . ')') ?></button>
          <button type="button" id="AddSelectedToDeposit" class="btn btn-primary"><?= gettext('Add Selected Rows') ?></button>
          <button type="button" id="cancel" class="btn btn-danger"><?= gettext('Cancel') ?></button>
        </div>
    </div>
  </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/FindContributions.js"></script>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">

iDepositSlipID = <?= $iDepositSlipID ?>;
slinkBack = "<?= $linkBack ?>";

$(document).ready(function () {
  if (iDepositSlipID) {
    url = "/api/contrib/deposit";
    initTable(url);
    initAddToDeposit()
  } 
  else {
    url = "/api/contrib";
    initTable(url);
    // table must be initialized before buttons
    initButtons();
  }

  // delete only if no associated with a deposit
  $('#deleteSelectedRows').click(function () {
      var selectedRows = dataT.rows('.selected').data()
      // verify
      var inDeposit = false;
      promise = $.when(
        $.each(selectedRows, function(index, value) {
          if (value.DepId != null) {
            inDeposit = true;
            return false;
          }
        })
      )
      promise.done(function(data){
        if(inDeposit) {
          depositError();
        } 
        else {
          deleteContrib(selectedRows.length);
        }
      });
    });

  function deleteContrib(len) {
    bootbox.confirm({
        title:'<?= gettext("Confirm Delete") ?>',
        message: '<p><?= gettext("Are you sure you want to delete the selected"); ?> '+ len + ' <?= gettext("Contribution(s)"); ?>?' +
          '</p><p><?= gettext("This will also remove all splits associated with this contribution"); ?></p>'+
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
        callback: function (result) {
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
  }

  function depositError() {
    bootbox.alert({
        title:'<?= gettext("Delete Error") ?>',
        message: '<p><?= gettext("Unable to delete contributions that are associated with a deposit."); ?></p>'+
          '<p><?= gettext("Contributions must be removed from a deposit before they can deleted."); ?></p>'
      });
  }

});
</script>

<?php require "Include/Footer.php" ?>
