<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
 *  last change : 2016-02-28
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2016 ChurchCRM
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
require 'Service/FinancialService.php';

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext("Deposit Listing");

// Security: User must have finance permission to use this form
if (!$_SESSION['bFinance'])
{
    Redirect("index.php");
    exit;
}

$financialService=new FinancialService();
require "Include/Header.php";
?>

<link rel="stylesheet" type="text/css" href="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.css">
<link rel="stylesheet" type="text/css" href="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.css">
<script src="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sRootPath; ?>/skin/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>

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
        <p>Are you sure you want to delete the selected <span id="deleteNumber"></span> Deposit(s)?</p>
        <p>This will also delete all payments associated with this deposit</p>
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

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?php echo gettext("Add New Deposit: ");?></h3>
    </div>
    <div class="box-body">
        <form action="#" method="get" class="form">
            <div class="row">
                <div class="col-xs-3">
                    <label for="addNewGruop">Deposit Comment</label>
                    <input class="form-control newDeposit" name="depositComment" id="depositComment" style="width:100%">
                </div>
                <div class="col-xs-3">
                    <label for="depositType">Deposit Type</label>
                    <select  class="form-control" id="depositType" name="depositType">
                        <option value="Bank">Bank</option>
                        <option value="CreditCard">Credit Card</option>
                        <option value="BankDraft">Bank Draft</option>
                        <option value="eGive">eGive</option>
                    </select>
                </div>
                <div class="col-xs-3">
                    <label for="addNewGruop">Deposit Date</label>
                    <input class="form-control" name="depositDate" id="depositDate" style="width:100%">
                </div>
            </div>
            <p>
            <div class="row">
                <div class="col-xs-3">
                    <button type="button" class="btn btn-primary" id ="addNewDeposit" >Add New Deposit</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?php echo gettext("Deposits: ");?></h3>
    </div>
    <div class="box-body">
        <table class="table" id="depositsTable">
        </table>

        <button type="button" id="deleteSelectedRows" class="btn btn-danger" disabled> <?= gettext("Delete Selected Rows") ?> </button>
        <button type="button" id="exportSelectedRows" class="btn btn-success exportButton" data-exportType="ofx" disabled><i class="fa fa-download"></i> <?= gettext("Export Selected Rows (OFX)") ?></button>
        <button type="button" id="exportSelectedRowsCSV" class="btn btn-success exportButton" data-exportType="csv" disabled><i class="fa fa-download"></i> <?= gettext("Export Selected Rows (CSV)") ?></button>
        <button type="button" id="generateDepositSlip" class="btn btn-success exportButton" data-exportType="pdf" disabled> <?= gettext("Generate Deposit Split for Selected Rows (PDF)") ?></button>
    </div>
</div>

<script>
var depositData = <?php $json = $financialService->getDepositJSON($financialService->getDeposits()); if ($json) { echo $json; } else { echo 0; } ?>;
</script>

<script src="<?= $sRootPath; ?>/skin/js/FindDepositSlip.js"></script>

<?php require "Include/Footer.php" ?>
