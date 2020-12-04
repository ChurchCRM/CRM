<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
 *  last change : 2020-11-30
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2020 ChurchCRM
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Authentication\AuthenticationManager;

// $iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext('Contribution Listing');

// Security: User must have finance permission to use this form
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()){
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
<div class="box box-primary">
    <div class="box-header">
        <?= gettext('Filter:') ?>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-6">
                <div class='external-filter'>
                <!-- <label>Gender:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Date" multiple="multiple"></select>
                <!-- <label>Classification:</label> -->
                <select style="visibility: hidden; margin: 5px; display:inline-block; width: 150px;" class="filter-Comment" multiple="multiple"></select>
 
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
          deleteContrib(selectedRows,selectedRows.length);
        }
      });
    });

  function deleteContrib(selectedRows,len) {
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
            $.each(selectedRows, function (index, value) {
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

  $('.filter-Date').select2({
      multiple: true,
      placeholder: "Select Date",
  });
  $('.filter-Comment').select2({
      multiple: true,
      placeholder: "Select Comment"
  });

  $('.filter-Date').on("change", function() {
      filterColumn(5, $(this).select2('data'), false);
  });
  $('.filter-Comment').on("change", function() {
      filterColumn(7, $(this).select2('data'), true);
  });

  // clear external filters
  // document.getElementById("ClearFilter").addEventListener("click", function() {
  $('#ClearFilter').on("click", function() {
    $('.filter-Date').val([]).trigger('change')
    $('.filter-Comment').val([]).trigger('change')
  });

  $("#AddAllToCart").click(function(){
        var listPeople = [];
        dataT.rows( { filter: 'applied' } ).every( function () {
        // fill array
        var row = this.data();
        listPeople.push(row.ConId);
    });
        // bypass SelectList.js
        window.CRM.cart.addPerson(listPeople);
    });

    $("#RemoveAllFromCart").click(function(){
        var listPeople = [];
        dataT.rows( { filter: 'applied' } ).every( function () {
        // fill array
        var row = this.data();
        listPeople.push(row.ConId);
    });
        // bypass SelectList.js
        window.CRM.cart.removePerson(listPeople);
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
    dataT.column(col).search(val, 1, 0).draw();
  }
});

</script>

<?php require "Include/Footer.php" ?>
