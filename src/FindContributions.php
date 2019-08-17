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
          <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx"
                  disabled><i class="fa fa-download"></i> <?= gettext('Export Selected Rows (OFX)') ?></button>
          <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv"
                  disabled><i class="fa fa-download"></i> <?= gettext('Export Selected Rows (CSV)') ?></button>
          <!-- <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf"
                  disabled> < ?= gettext('Generate Deposit Slip for Selected Rows (PDF)') ?></button> -->
          <!-- <button type="button" id="AddAllToCart" class="btn btn-primary">< ?= gettext('Add All to Cart') ?></button>
          <button type="button" id="RemoveAllFromCart" class="btn btn-danger">< ?= gettext('Remove All from Cart') ?></button> -->
          
        </div>
        <div id="depositButton" style="display:none">
          <button type="button" id="AddToDeposit" class="btn btn-primary"><?= gettext('Add to Deposit (' . $iDepositSlipID . ')') ?></button>
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
    initAddToDeposit()
  } 
  else {
    url = "/api/contrib";
    initButtons();
  }
  initTable(url);
  

  // $("#AddAllToCart").click(function(){
  //   var listContributions = [];
  //   // var deletedRows = dataT.rows('.selected').data()
  //   var deletedRows = dataT.rows().data();
  //   $.each(deletedRows, function (index, value) {
  //     listContributions.push(parseInt(value.Id));
  //   });
  //   // console.log(listContributions);
  //   window.CRM.cart.addContributions(listContributions);
  // });
  
  //  $("#RemoveAllFromCart").click(function(){
  //   window.CRM.cart.removeContributions(listContributions);
  // });
});
</script>

<?php require "Include/Footer.php" ?>
