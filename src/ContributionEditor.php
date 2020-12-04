<?php
/*******************************************************************************
 *
 *  filename    : ContributionEditor.php
 *  last change : 2019-08-09
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2019 Troy Smith
 *

 ******************************************************************************/


namespace ChurchCRM;

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
// use ChurchCRM\MICRReader;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\RedirectUtils;
// use ChurchCRM\DonationFund;
use ChurchCRM\DonationFundQuery;
use ChurchCRM\ContribSplitQuery;
use ChurchCRM\Person;
use ChurchCRM\PersonQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use ChurchCRM\Authentication\AuthenticationManager;

// Security: User must have finance permission to use this form
if (!AuthenticationManager::GetCurrentUser()->isFinanceEnabled()) {
    RedirectUtils::Redirect('index.php');
    exit;
}

// get user defined system settings
$sDateFormat = SystemConfig::getValue('sDateFormatLong');
$sDatePickerFormat = SystemConfig::getValue('sDatePickerFormat');
// $sDatePickerPlaceHolder = SystemConfig::getValue('sDatePickerPlaceHolder');
$bEnableNonDeductible = SystemConfig::getValue('bEnableNonDeductible');

// Handle URL via _GET first
$iContributionID = 0;
if (array_key_exists('ContributionID', $_GET)) {
    $iContributionID = InputUtils::LegacyFilterInput($_GET['ContributionID'], 'int');
}

$iContributorID = 0;
if (array_key_exists('ContributorID', $_GET)) {
    $iContributorID = InputUtils::LegacyFilterInput($_GET['ContributorID'], 'int');
}

// new contribtion
$dDate = InputUtils::LegacyFilterInput($_POST['contribDate']);
if (!$dDate) {
  if (array_key_exists('idefaultDate', $_SESSION)) {
      $dDate = $_SESSION['idefaultDate'];
  } else {
      // get previous sunday since data entry normally occurs later if dDate not set
      $dDate = date($sDatePickerFormat, strtotime('last Sunday', time()));
  }
}

$iMethod = InputUtils::LegacyFilterInput($_POST['contribType']);
if (!$iMethod) {
  if (array_key_exists('idefaultDate', $_SESSION)) {
      $iMethod = $_SESSION['idefaultDate'];
  } else {
      $iMethod = 'Cash';
  }
}

$linkBack = InputUtils::LegacyFilterInput($_GET['linkBack'], 'string');

require 'Include/Header.php';

?>
    <!-- Add Split Modal -->
    <div id="addNewContribModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <form name="issueReport">
                    <input type="hidden" name="pageName" value="<?= $_SERVER['SCRIPT_NAME'] ?>"/>
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><?= gettext('Add Split') ?></h4>
                    </div>
                    <div class="modal-body">
                        <div class="container-fluid">

                            <div class="row">
                                <div class="col-xl-3">
                                    <label for="AddFund"><?= gettext('Fund') ?> </label>
                                </div>
                                <div class="col-xl-3">
                                    <select class="form-control" id="AddFund" name="AddFund" autofocus >
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-3">
                                    <label for="AddAmount"><?= gettext('Amount') ?></label>
                                </div>
                                <div class="col-xl-3">
                                    <input class="FundAmount"  type="number" step="any" name="AddAmount" id="AddAmount" />
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-3">
                                    <label for="AddComment"><?= gettext('Comment') ?></label>
                                </div>
                                <div class="col-xl-3">
                                    <input type="text" size=40 name="AddComment" id="AddComment" />
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-xl-3">
                                    <label id= "AddNonDeductibleLabel" for="AddNonDeductible"><?= gettext('Non-Deductible') ?></label>
                                </div>
                                <div class="col-xl-3">
                                    <input type="checkbox" name="AddNonDeductible" id="AddNonDeductible" />
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-dismiss="modal" id="submitContrib"><?= gettext('Submit') ?></button>
                        <button type="button" class="btn btn-primary" id="addAnotherSplit"><?= gettext('Add New Split') ?></button>
                        <!-- <button type="button" class="btn btn-primary" id="addAnotherContribution">< ?= gettext('Add Contribution') ?></button> -->
                    </div>
                    <div class="modal-footer">
                        
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- End Add Split Modal -->

<form id="MainForm" method="POST" action="ContributionEditor.php?linkBack=findContributions.php">
<div class="row">
  <div class="col-lg-12">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Contribution Details") ?></h3>
      </div>

      <input type="hidden" name="ContributionID" id="ContributionID" value= <?= $iContributionID ?> >
      <input type="hidden" name="ContributorID" id="ContributorID" value= <?= $iContributorID ?> >
      <input type="hidden" name="TypeOfMbr" id="TypeOfMbr">

      <div class="box-body">
        <div class="container-fluid">
          <div class="row">
            <div class="col-lg-4">
              <label for="ContributorName"  class="text-nowrap" ><?= gettext('Contributor') ?></label>
              <select name="ContributorName" class="form-control choiceSelectBox" data-placeholder="<?= gettext('Select a Contributor') ?>" id="ContributorName" style="width:100%" ></select>
            </div>

            <div class="col-lg-2">
              <label for="contribDate"  class="text-nowrap" ><?= gettext('Date') ?></label>
              <input class="form-control" name="contribDate" id="contribDate" style="width:100%" class="date-picker">
            </div>

            <div class="col-lg-2">
              <label for="contribType"><?= gettext('Payment by') ?></label>
              <select class="form-control" id="contribType" name="contribType"  style="width:100%" >
                <option>Bank Draft</option>
                <option selected>Cash</option>
                <option>Check</option>
                <option>Credit Card</option>
                <option>eGive</option>
                <option>Other</option>
              </select>
            </div>

            <div class="col-lg-2">
              <label for="contribCheck" class="text-nowrap" ><?= gettext('Check #') ?> </label>
              <input class="form-control" name="contribCheck" id="contribCheck" style="width:100%">
            </div>

          </div>

          <div class="row">
            <div class="col-lg-4">
              <label for="contribComment" class="text-nowrap" ><?= gettext('Comment') ?></label>
                <input class="form-control" name="contribComment" id="contribComment" style="width:100%">
            </div>

            <div class="col-lg-2">
              <?php if (SystemConfig::getValue('bUseDonationEnvelopes')) { ?>
                <label for="Envelope" class="text-nowrap" ><?= gettext('Envelope Number') ?></label>
                <input  class="form-control" type="number" name="Envelope" size=8 id="Envelope" disabled />
              <?php } ?>
            </div>

            <div class="col-lg-2">
              <label for="TotalAmount" class="text-nowrap" ><?= gettext('Total $') ?></label>
              <input class="form-control"  type="number" step="any" name="TotalAmount" id="TotalAmount" disabled />
            </div>
          </div>
        </div>

        <p>
          <div class="row">
            <div class="col-lg-1">
              <input type="button" class="btn btn-primary" value="<?= gettext('Save') ?>" id="PledgeSubmit" name="PledgeSubmit" <?= $iContributionID ? 'enabled' : 'disabled' ?> />
            </div>

            
            <div class="col-lg-1">
              <?php if (AuthenticationManager::GetCurrentUser()->isAddRecordsEnabled()) { ?>
                <input type="button" class="btn btn-primary" value="<?= gettext('New Contribution') ?>" id="PledgeSubmitAdd" disabled />
              <?php } ?>
            </div>
          </div>

        </p>
      </div>
    </div>
  </div>
</div>

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('Contribution Splits:') ?></h3>
      <div class="pull-right">
      <!-- <button type="button" class="btn btn-primary" id="addNewContrib2">< ?= gettext('Add New Split') ?></button> -->
      <button disabled id="addNewContrib" type="button" class="btn btn-primary" data-toggle="modal" data-target="#addNewContribModal"><?= gettext('Add New Split') ?></button>
      </div>
  </div>
    <div class="box-body">
      <div class="container-fluid">
        <table class="display responsive nowrap data-table table table-striped table-hover" id="splitTable" width="100%"></table>

        <button style="display:none" type="button" id="deleteSelectedRows" class="btn btn-danger"
                disabled> <?= gettext('Delete Selected Rows') ?> </button>
        <!-- <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx"
                disabled><i class="fa fa-download"></i> < ?= gettext('Export Selected Rows (OFX)') ?></button>
        <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv"
                disabled><i class="fa fa-download"></i> < ?= gettext('Export Selected Rows (CSV)') ?></button>
        <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf"
                disabled> < ?= gettext('Generate Deposit Slip for Selected Rows (PDF)') ?></button> -->
    </div>
  </div>
</div>

</form>

<script  src="<?= SystemURLs::getRootPath() ?>/skin/js/ContributionEditor.js"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >

  var linkBack = "<?= $linkBack ?>";
  var iContributorID = <?= $iContributorID ?>;
  var iContributionID = <?= $iContributionID ?>;
  var CurrentUser = <?= AuthenticationManager::GetCurrentUser()->getId() ?>;
  var EnableNonDeductible = <?= $bEnableNonDeductible ?>;
  var dDate = "<?= $dDate ?>";
  var iMethod = "<?= $iMethod ?>";
  
  $(document).ready(function() {
    // setfocus on name

    initPaymentTable();
    initFundList();

    // delete selected rows
    $('#deleteSelectedRows').click(function () {
    var deletedRows = dataT.rows('.selected').data()
    bootbox.confirm({
      title:'<?= gettext("Confirm Delete") ?>',
      message: '<p><?= gettext("Are you sure you want to delete the selected"); ?> '+ deletedRows.length + ' <?= gettext("Split(s)"); ?>?' +
        // '</p><p>< ?= gettext("This will also delete all payments associated with this deposit"); ?></p>'+
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
              path: 'split/' + value.Id
            })
              .done(function (data) {
                
                dataT.rows('.selected').remove().draw(false);
                // recalculate total
                var newTotal = dataT.column(3).data().reduce( function (a,b) {
                      return parseInt(a) + parseInt(b);
                  });
                $('#TotalAmount').val(newTotal);
              });
          });
        }
      }
    });
  });

  // hide based on system settings
  if (!EnableNonDeductible) {
    $("#AddNonDeductible").hide();
    $("#AddNonDeductibleLabel").hide();
  }
  if (iContributorID) { // edit contributions
    $("#addNewContrib").prop('disabled',false);
    initContribution();
    initContributor();
    // $("#AddFund").focus();
  } else { // if new contribution
    // get previous sunday since data entry normally occurs later if dDate not set
    // if (dDate == '') {
    //   dDate = new Date()
    //   dDate.setDate(dDate.getDate() - dDate.getDay());
    // }
    
  $("#contribDate").datepicker({format: window.CRM.datePickerformat, language: window.CRM.lang}).datepicker("setDate", dDate);
    // set payment type
    $('#contribType').val(iMethod);
  }
  // search for contributors
  $("#ContributorName").select2({
    minimumInputLength: 2,
    ajax: {
        url: function (params){
          var a = window.CRM.root + '/api/persons/search2/'+ params.term;
          return a;
        },
        dataType: 'json',
        delay: 250,
        data: "People",
        processResults: function (data, params) {
          var results = [];
          var people = JSON.parse(data).People
          $.each(people, function(key, object) {
            results.push({
              id: object.Id,
              text: object.displayName,
              envelope: object.envelope,
              typeofmbr: object.TypeOfMbr
            });
          });
          
          return {
            results: results
          };
          
        }
      }
  });

  // after selection from search, upate the following
  $("#ContributorName").on("select2:select", function (e) {
    // update Contributor
    iContributorID = parseInt(e.params.data.id);
    $('[name=ContributorID]').val(e.params.data.id);
    $('[name=TypeOfMbr]').val(e.params.data.typeofmbr);
    $('[name=Envelope]').val(e.params.data.envelope);
    $("#addNewContrib").prop('disabled', false);
    // $("#PledgeSubmit").prop('disabled', false)
  });
  // toggle table rows when clicked on
  $("#splitTable tbody").on('click', 'tr', function () {
    $(this).toggleClass('selected');
    var selectedRows = dataT.rows('.selected').data().length;
    $("#deleteSelectedRows").prop('disabled', !(selectedRows));
    $("#deleteSelectedRows").text("Delete (" + selectedRows + ") Selected Rows");
    // $("#exportSelectedRows").prop('disabled', !(selectedRows));
    // $("#exportSelectedRows").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (OFX)");
    // $("#exportSelectedRowsCSV").prop('disabled', !(selectedRows));
    // $("#exportSelectedRowsCSV").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (CSV)");
    // $("#generateDepositSlip").prop('disabled', !(selectedRows));
    // $("#generateDepositSlip").html("<i class=\"fa fa-download\"></i> Generate Deposit Split for Selected (" + selectedRows + ") Rows (PDF)");
  });
});

</script>


<?php require 'Include/Footer.php' ?>
