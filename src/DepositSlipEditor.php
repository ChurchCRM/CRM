<?php
/*******************************************************************************
 *
 *  filename    : DepositSlipEditor.php
 *  last change : 2014-12-14
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003-2014 Deane Barker, Chris Gebhardt, Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "service/FinancialService.php";

$financialService = new FinancialService();
$iDepositSlipID = 0;
$thisDeposit = 0;

if (array_key_exists("DepositSlipID", $_GET))
  $iDepositSlipID = FilterInput($_GET["DepositSlipID"], 'int');

if ($iDepositSlipID) {

  $thisDeposit = $financialService->GetDeposits($iDepositSlipID)[0];
  // Set the session variable for default payment type so the new payment form will come up correctly
  if ($thisDeposit->dep_Type == "Bank")
    $_SESSION['idefaultPaymentMethod'] = "CHECK";
  else if ($thisDeposit->dep_Type == "CreditCard")
    $_SESSION['idefaultPaymentMethod'] = "CREDITCARD";
  else if ($thisDeposit->dep_Type == "BankDraft")
    $_SESSION['idefaultPaymentMethod'] = "BANKDRAFT";
  else if ($thisDeposit->dep_Type == "eGive")
    $_SESSION['idefaultPaymentMethod'] = "EGIVE";

  // Security: User must have finance permission or be the one who created this deposit
  if (!($_SESSION['bFinance'] || $_SESSION['iUserID'] == $thisDeposit->dep_EnteredBy)) {
    Redirect("Menu.php");
    exit;
  }
}
else {
  Redirect("Menu.php");
}

//Set the page title
$sPageTitle = $thisDeposit->dep_Type . " " . gettext("Deposit Slip Number: ") . $iDepositSlipID;

//Is this the second pass?
if (isset($_POST["DepositSlipLoadAuthorized"])) {
  $financialService->loadAuthorized($iDepositSlipID);
}
else if (isset($_POST["DepositSlipRunTransactions"])) {
  $financialService->runTransactions($iDepositSlipID);
}

$_SESSION['iCurrentDeposit'] = $iDepositSlipID;  // Probably redundant
$sSQL = "UPDATE user_usr SET usr_currentDeposit = '$iDepositSlipID' WHERE usr_per_id = \"" . $_SESSION['iUserID'] . "\"";
$rsUpdate = RunQuery($sSQL);

require "Include/Header.php";
?>

<link rel="stylesheet" type="text/css" href="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.css">
<link rel="stylesheet" type="text/css" href="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.css">
<script src="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>
<div class="row">
  <div class="col-lg-8">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?php echo gettext("Deposit Details: "); ?></h3>
      </div>
      <div class="box-body">
        <form method="post" action="#" name="DepositSlipEditor" id="DepositSlipEditor">
          <div class="row">
            <div class="col-lg-4">
              <label for="Date"><?php echo gettext("Date:"); ?></label>
              <input type="text" class="form-control" name="Date" value="<?php echo $thisDeposit->dep_Date; ?>" id="DepositDate" >
            </div>
            <div class="col-lg-4">
              <label for="Comment"><?php echo gettext("Comment:"); ?></label>
              <input type="text" class="form-control" name="Comment" id="Comment" value="<?php echo $thisDeposit->dep_Comment; ?>"/>
            </div>
            <div class="col-lg-4">
              <label for="Closed"><?php echo gettext("Closed:"); ?></label>
              <input type="checkbox"  name="Closed" id="Closed" value="1" <?php if ($thisDeposit->dep_Closed) echo " checked"; ?>/><?php echo gettext("Close deposit slip (remember to press Save)"); ?>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-6" style="text-align:center">
              <input type="submit" class="btn" value="<?php echo gettext("Save"); ?>" name="DepositSlipSubmit">
            </div>
            <div class="col-lg-6" style="text-align:center">
              <input type="button" class="btn" value="<?php echo gettext("Deposit Slip Report"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location = 'Reports/PrintDeposit.php?BankSlip=<?php echo ($thisDeposit->dep_Type == 'Bank') ?>';">
            </div>
          </div>
          <?php
          if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {
            echo "<p>" . gettext("Important note: failed transactions will be deleted permanantly when the deposit slip is closed.") . "</p>";
          }
          ?>
      </div>
    </div>
  </div>
  <?php 
  if ($thisDeposit->countTotal > 0)
  { 
?>
  <div class="col-lg-4">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?php echo gettext("Deposit Summary: "); ?></h3>
      </div>
      <div class="box-body">
        <script src="<?= $sRootPath ?>/skin/adminlte/plugins/chartjs/Chart.min.js"></script>
        <div class="col-lg-6">
          <canvas id="type-donut" style="height:250px"></canvas>
          <?php
          // Get deposit totals
          echo "<b>" . $thisDeposit->dep_Total . " - TOTAL AMOUNT </b> &nbsp; (Items: $thisDeposit->countTotal)<br>";
          if ($thisDeposit->totalCash)
          echo "<i><b>" . $thisDeposit->totalCash . " - Total Cash </b> &nbsp; (Items: $thisDeposit->countCash)</i><br>";
          if ($thisDeposit->totalChecks)
          echo "<i><b>" . $thisDeposit->totalChecks . " - Total Checks</b> &nbsp; (Items: $thisDeposit->countCheck)</i><br>";
          ?>
        </div>
         <div class="col-lg-6">
          <canvas id="fund-donut" style="height:250px"></canvas>
          <?php
          // Get deposit totals
          echo "<b>" . $thisDeposit->dep_Total . " - TOTAL AMOUNT </b> &nbsp; (Items: $thisDeposit->countTotal)<br>";
          if ($thisDeposit->totalCash)
          echo "<i><b>" . $thisDeposit->totalCash . " - Total Cash </b> &nbsp; (Items: $thisDeposit->countCash)</i><br>";
          if ($thisDeposit->totalChecks)
          echo "<i><b>" . $thisDeposit->totalChecks . " - Total Checks</b> &nbsp; (Items: $thisDeposit->countCheck)</i><br>";
          ?>
        </div>
        
        
        
         
       


      </div>

    </div>
  </div>
  <?php
  }
  ?>
</div>
<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext("Payments on this deposit slip:"); ?></h3>
    <div class="pull-right">
      <?php
      if ($iDepositSlipID and $thisDeposit->dep_Type and ! $thisDeposit->dep_Closed) {
        if ($thisDeposit->dep_Type == "eGive") {
          echo "<input type=button class=btn value=\"" . gettext("Import eGive") . "\" name=ImporteGive onclick=\"javascript:document.location='eGive.php?DepositSlipID=$iDepositSlipID&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
        }
        else {
          echo "<input type=button class=\"btn btn-success\" value=\"" . gettext("Add Payment") . "\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?CurrentDeposit=$iDepositSlipID&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
        }
        if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {
          ?>
          <input type="submit" class="btn btn-success" value="<?php echo gettext("Load Authorized Transactions"); ?>" name="DepositSlipLoadAuthorized">
          <input type="submit" class="btn btn-warning" value="<?php echo gettext("Run Transactions"); ?>" name="DepositSlipRunTransactions">
          <?php
        }
      }
      ?>
    </div>
  </div>
  <div class="box-body">
    <table class="table" id="paymentsTable"></table>
    <?php
    if ($iDepositSlipID and $thisDeposit->dep_Type and ! $thisDeposit->dep_Closed) {
      if ($thisDeposit->dep_Type == "Bank") {
        ?>
        <button type="button" id="deleteSelectedRows"  class="btn btn-danger" disabled>Delete Selected Rows</button>
        <?php
      }
    }
    ?>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div id="confirmDelete" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Confirm Delete</h4>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete the selected <span id="deleteNumber"></span> payments(s)?</p>
        <p>This action CANNOT be undone, and may have legal implications!</p>
        <p>Please ensure this what you want to do.</p>
        <button type="button" class="btn btn-danger" id="deleteConfirmed" ><?php echo gettext("Delete"); ?></button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- End Delete Confirm Modal -->


<script type="text/javascript" src="<?= $sRootPath ?>/skin/js/DepositSlipEditor.js"></script>
<script>
var paymentData = <?php echo $financialService->getPaymentJSON($financialService->getPayments($iDepositSlipID)); ?>;
var typePieData = [
  {
    value: <?= $thisDeposit->totalCash ?> , 
    color: "#197A05", 
    highlight: "#4AFF23", 
    label: "Cash" 
  },
  {
    value:  <?= $thisDeposit->totalChecks ?  $thisDeposit->totalChecks : "0" ?>, 
    color: "#003399", 
    highlight: "#3366ff", 
    label: "Checks" 
  }
  ];
  
var fundPieData = [
  {
    value: <?= $thisDeposit->totalCash ?> , 
    color: "#003399", 
    highlight: "#3366ff", 
    label: "Cash" 
  },
  {
    value:  <?= $thisDeposit->totalChecks ?  $thisDeposit->totalChecks : "0" ?>, 
    color: "#003399", 
    highlight: "#3366ff", 
    label: "Checks" 
  }
  ];
var depositType = '<?php echo $thisDeposit->dep_Type; ?>';
var depositSlipID = <?php echo $iDepositSlipID; ?>;

$(document).ready(function() {


  dataT = $("#paymentsTable").DataTable({
  data:paymentData.payments,
          columns: [
          {
          "className":      'details-control',
                  "orderable":      false,
                  "data":           null,
                  "defaultContent": '<i class="fa fa-plus-circle"></i>'
          },
          {
          width: 'auto',
                  title:'Family',
                  data:'familyName',
                  render: function(data, type, full, meta) {
                    return '<a href=\'PledgeEditor.php?GroupKey=' + full.plg_GroupKey + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>' + data;
                  }
          },
          {
          width: 'auto',
                  title:'Check Number',
                  data:'plg_CheckNo',
          },
          {
          width: 'auto',
                  title:'Amount',
                  data:'plg_amount',
          }
          ,
          {
          width: 'auto',
                  title:'Method',
                  data:'plg_method',
          }
<?php if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') { ?>,
                      , {
                      width: 'auto',
                              title:'Cleared',
                              data:'plg_aut_Cleared',
                      }<?php
}
if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {
?>
              , {
              width: 'auto',
                      title:'Details',
                      data:'plg_plgID',
                      render: function(data, type, full, meta)
                      {
                        return '<a href=\'PledgeDetails.php?PledgeID=' + data + '\'>Details</a>'
                        }
              }<?php } ?>
          ]
});

initDepositSlipEditor();
});

</script>



<?php
require "Include/Footer.php";
?>
