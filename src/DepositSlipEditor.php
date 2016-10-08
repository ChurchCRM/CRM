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

use ChurchCRM\PledgeQuery;
use ChurchCRM\DepositQuery;

$iDepositSlipID = 0;
$thisDeposit = 0;

if (array_key_exists("DepositSlipID", $_GET))
  $iDepositSlipID = FilterInput($_GET["DepositSlipID"], 'int');

if ($iDepositSlipID) {

  $thisDeposit = DepositQuery::create()->findOneById($iDepositSlipID);
  // Set the session variable for default payment type so the new payment form will come up correctly
  if ($thisDeposit->getType() == "Bank")
    $_SESSION['idefaultPaymentMethod'] = "CHECK";
  else if ($thisDeposit->getType() == "CreditCard")
    $_SESSION['idefaultPaymentMethod'] = "CREDITCARD";
  else if ($thisDeposit->getType()== "BankDraft")
    $_SESSION['idefaultPaymentMethod'] = "BANKDRAFT";
  else if ($thisDeposit->getType() == "eGive")
    $_SESSION['idefaultPaymentMethod'] = "EGIVE";

  // Security: User must have finance permission or be the one who created this deposit
  if (!($_SESSION['bFinance'] || $_SESSION['iUserID'] == $thisDeposit->getEnteredby())) {
    Redirect("Menu.php");
    exit;
  }
}
else {
  Redirect("Menu.php");
}

//Set the page title
$sPageTitle = $thisDeposit->getType() . " " . gettext("Deposit Slip Number: ") . $iDepositSlipID;

//Is this the second pass?
if (isset($_POST["DepositSlipLoadAuthorized"])) {
  $thisDeposit->loadAuthorized();
}
else if (isset($_POST["DepositSlipRunTransactions"])) {
  $thisDeposit->runTransactions();
}

$_SESSION['iCurrentDeposit'] = $iDepositSlipID;  // Probably redundant
$sSQL = "UPDATE user_usr SET usr_currentDeposit = '$iDepositSlipID' WHERE usr_per_id = \"" . $_SESSION['iUserID'] . "\"";
$rsUpdate = RunQuery($sSQL);

require "Include/Header.php";
?>
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/chartjs/Chart.min.js"></script>

<div class="row">
  <div class="col-lg-7">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?php echo gettext("Deposit Details: "); ?></h3>
      </div>
      <div class="box-body">
        <form method="post" action="#" name="DepositSlipEditor" id="DepositSlipEditor">
          <div class="row">
            <div class="col-lg-4">
              <label for="Date"><?php echo gettext("Date:"); ?></label>
              <input type="text" class="form-control" name="Date" value="<?php echo $thisDeposit->getDate('Y-m-d'); ?>" id="DepositDate" >
            </div>
            <div class="col-lg-4">
              <label for="Comment"><?php echo gettext("Comment:"); ?></label>
              <input type="text" class="form-control" name="Comment" id="Comment" value="<?php echo $thisDeposit->getComment(); ?>"/>
            </div>
            <div class="col-lg-4">
              <label for="Closed"><?php echo gettext("Closed:"); ?></label>
              <input type="checkbox"  name="Closed" id="Closed" value="1" <?php if ($thisDeposit->getClosed()) echo " checked"; ?>/><?php echo gettext("Close deposit slip (remember to press Save)"); ?>
            </div>
          </div>
          <div class="row">
            <div class="col-lg-6" style="text-align:center">
              <input type="submit" class="btn" value="<?php echo gettext("Save"); ?>" name="DepositSlipSubmit">
            </div>
            <div class="col-lg-6" style="text-align:center">
              <input type="button" class="btn" value="<?php echo gettext("Deposit Slip Report"); ?>" name="DepositSlipGeneratePDF" onclick="verifyThenLoadAPIContent(window.CRM.root + '/api/deposits/<?php echo ($thisDeposit->getId()) ?>/pdf');">
            </div>
          </div>
          <?php
          if ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard') {
            echo "<p>" . gettext("Important note: failed transactions will be deleted permanantly when the deposit slip is closed.") . "</p>";
          }
          ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title"><?php echo gettext("Deposit Summary: "); ?></h3>
      </div>
      <div class="box-body">
        <div class="col-lg-6">
          <canvas id="type-donut" style="height:250px"></canvas>
          <ul style="margin:0px; border:0px; padding:0px;">
          <?php
          // Get deposit totals
          echo "<li><b>TOTAL (".$thisDeposit->getPledges()->count(). "):</b> $".$thisDeposit->getVirtualColumn("totalAmount")."</li>";
          if ($thisDeposit->getCountChecks())
            echo "<li><b>CASH (" . $thisDeposit->getCountCash() . "):</b> $" . $thisDeposit->getTotalCash()."</li>";
          if ($thisDeposit->getCountCash())
            echo "<li><b>CHECKS (" . $thisDeposit->getCountChecks() . "):</b> $". $thisDeposit->getTotalChecks() . " </li>";
          ?>
            </ul>
        </div>
         <div class="col-lg-6">
          <canvas id="fund-donut" style="height:250px"></canvas>
          <ul style="margin:0px; border:0px; padding:0px;">
          <?php
          foreach ($thisDeposit->getFundTotals() as $fund)
          {
            echo "<li><b>". $fund->Name . "</b>: $" . $fund->Total."</li>";
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext("Payments on this deposit slip:"); ?></h3>
    <div class="pull-right">
      <?php
      if ($iDepositSlipID and $thisDeposit->getType() and ! $thisDeposit->getClosed()) {
        if ($thisDeposit->getType() == "eGive") {
          echo "<input type=button class=btn value=\"" . gettext("Import eGive") . "\" name=ImporteGive onclick=\"javascript:document.location='eGive.php?DepositSlipID=$iDepositSlipID&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
        }
        else {
          echo "<input type=button class=\"btn btn-success\" value=\"" . gettext("Add Payment") . "\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?CurrentDeposit=$iDepositSlipID&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
        }
        if ($thisDeposit->getType() == 'BankDraft' || $thisDeposit->getType() == 'CreditCard') {
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
    if ($iDepositSlipID and $thisDeposit->getType() and !$thisDeposit->getClosed()) {
      if ($thisDeposit->getType() == "Bank") {
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
<?php
  $fundData = array() ;
  foreach ($thisDeposit->getFundTotals() as $tmpfund)
  {
    $fund = new StdClass();
    $fund->color = "#".random_color() ;
    $fund->highlight= "#".random_color() ;
    $fund->label = $tmpfund->Name;
    $fund->value = $tmpfund->Total;
    array_push($fundData,$fund);
  }
  $pledgeTypeData = array();
  $t1 = new stdClass();
  $t1->value = $thisDeposit->getTotalamount() ? $thisDeposit->getTotalCash() : "0";
  $t1->color = "#197A05";
  $t1->highlight ="#4AFF23";
  $t1->label ="Cash";
  array_push($pledgeTypeData, $t1);
  $t1 = new stdClass();
  $t1->value = $thisDeposit->getTotalamount() ? $thisDeposit->getTotalChecks() : "0";
  $t1->color = "#003399";
  $t1->highlight ="#3366ff";
  $t1->label ="Checks";
  array_push($pledgeTypeData, $t1);
?>

<script>
  var depositType = '<?php echo $thisDeposit->getType(); ?>';
  var depositSlipID = <?php echo $iDepositSlipID; ?>;
  var isDepositClosed = Boolean(<?=  $thisDeposit->getClosed();  ?>);
  var fundData = <?= json_encode($fundData) ?>; 
  var pledgeData = <?= json_encode($pledgeTypeData) ?>;
  
  $(document).ready(function() {
    initPaymentTable();
    initCharts(pledgeData, fundData);
    initDepositSlipEditor();
  });
</script>
<?php
  require "Include/Footer.php";
?>
