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
require "Include/MICRFunctions.php";


$financialService = new FinancialService();
$linkBack = "";
$iDepositSlipID = 0;
$dep_Type = "";
$sDateError = "";
$sDepositType = "";
$sComment = "";
$bClosed = false;

if (array_key_exists ("linkBack", $_GET))
	$linkBack = FilterInput($_GET["linkBack"]);
if (array_key_exists ("DepositSlipID", $_GET))
	$iDepositSlipID = FilterInput($_GET["DepositSlipID"], 'int');

if ($iDepositSlipID) {
	// Get the current deposit slip
	$sSQL = "SELECT * from deposit_dep WHERE dep_ID = " . $iDepositSlipID;
	$rsDeposit = RunQuery($sSQL);
	extract(mysql_fetch_array($rsDeposit));
	// Set current deposit slip
	$_SESSION['iCurrentDeposit'] = $iDepositSlipID;

	// Set the session variable for default payment type so the new payment form will come up correctly
	if ($dep_Type == "Bank") 
		$_SESSION['idefaultPaymentMethod'] = "CHECK";
	else if ($dep_Type == "CreditCard")
		$_SESSION['idefaultPaymentMethod'] = "CREDITCARD";
	else if ($dep_Type == "BankDraft")
		$_SESSION['idefaultPaymentMethod'] = "BANKDRAFT";
	else if ($dep_Type == "eGive")
		$_SESSION['idefaultPaymentMethod'] = "EGIVE";
	
	// Security: User must have finance permission or be the one who created this deposit
	if (! ($_SESSION['bFinance'] || $_SESSION['iUserID']==$dep_EnteredBy)) {
		Redirect("Menu.php");
		exit;
	}
}

//Set the page title
if (! $iDepositSlipID)
	$sPageTitle = $dep_Type . " " . gettext("Deposit Slip Number: TBD");
else
	$sPageTitle = $dep_Type . " " . gettext("Deposit Slip Number: ") . $iDepositSlipID;
	
//Is this the second pass?
if (isset($_POST["DepositSlipSubmit"])) {
	//Get all the variables from the request object and assign them locally
	$dDate = FilterInput($_POST["Date"]);
	$sComment = FilterInput($_POST["Comment"]);
	$bClosed = false;
	if (array_key_exists ("Closed", $_POST))
		$bClosed = FilterInput($_POST["Closed"]);
	$sDepositType = FilterInput($_POST["DepositType"]);

	if (! $bClosed)
		$bClosed = 0;

	//Initialize the error flag
	$bErrorFlag = false;

	// Validate Date
	if (strlen($dDate) > 0)
	{
		list($iYear, $iMonth, $iDay) = sscanf($dDate,"%04d-%02d-%02d");
		if ( !checkdate($iMonth,$iDay,$iYear) )
		{
			$sDateError = "<span style=\"color: red; \">" . gettext("Not a valid Date") . "</span>";
			$bErrorFlag = true;
		}
	}

	//If no errors, then let's update...
	if (!$bErrorFlag)
	{
		// New deposit slip
		if (! $iDepositSlipID)
		{
			$sSQL = "INSERT INTO deposit_dep (dep_Date, dep_Comment, dep_EnteredBy, dep_Closed, dep_Type) 
			VALUES ('" . $dDate . "','" . $sComment . "'," . $_SESSION['iUserID'] . "," . $bClosed . ",'" . $sDepositType . "')";
			$bGetKeyBack = True;

		// Existing record (update)
		} else {
			$sSQL = "UPDATE deposit_dep SET dep_Date = '" . $dDate . "', dep_Comment = '" . $sComment . "', dep_EnteredBy = ". $_SESSION['iUserID'] . ", dep_Closed = " . $bClosed . " WHERE dep_ID = " . $iDepositSlipID . ";";
			$bGetKeyBack = false;

			if ($bClosed && ($dep_Type=='CreditCard' || $dep_Type == 'BankDraft')) {
				// Delete any failed transactions on this deposit slip now that it is closing
				$q = "DELETE FROM pledge_plg WHERE plg_depID = " . $iDepositSlipID . " AND plg_PledgeOrPayment=\"Payment\" AND plg_aut_Cleared=0" ;
				RunQuery($q);
			}
		}

		//Execute the SQL
		RunQuery($sSQL);

		// If this is a new deposit slip, get the key back
		if ($bGetKeyBack)
		{
			$sSQL = "SELECT MAX(dep_ID) AS iDepositSlipID FROM deposit_dep";
			$rsDepositSlipID = RunQuery($sSQL);
			extract(mysql_fetch_array($rsDepositSlipID));
			$_SESSION['iCurrentDeposit'] = $iDepositSlipID;
		}

		if (isset($_POST["DepositSlipSubmit"]))
		{
			if ($linkBack != "") {
				Redirect($linkBack);
			} else { //Send to the view of this DepositSlip
				Redirect("DepositSlipEditor.php?linkBack=" . $linkBack . "&DepositSlipID=" . $iDepositSlipID);
			}
		}
	}
} 
else if (isset($_POST["DepositSlipLoadAuthorized"])) 
{
    $financialService->loadAuthorized($iDepositSlipID);
}
else if (isset($_POST["DepositSlipRunTransactions"])) 
{
    $financialService->runTransactions($iDepositSlipID);
} 
else 
{
    //Get all the data on this record
                                                                    
    $sSQL = "SELECT * FROM deposit_dep WHERE dep_ID = " . $iDepositSlipID;
    $rsDepositSlip = RunQuery($sSQL);
    extract(mysql_fetch_array($rsDepositSlip));

    $dDate = $dep_Date;
    $sComment = $dep_Comment;
    $bClosed = $dep_Closed;
    $sDepositType = $dep_Type;
	
}

	$_SESSION['iCurrentDeposit'] = $iDepositSlipID;		// Probably redundant
	$sSQL = "UPDATE user_usr SET usr_currentDeposit = '$iDepositSlipID' WHERE usr_per_id = \"".$_SESSION['iUserID']."\"";
	$rsUpdate = RunQuery($sSQL);


require "Include/Header.php";
?>

<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>

<div class="box">
<div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext("Deposit Details: ");?></h3>
</div>
<div class="box-body">

<form method="post" action="DepositSlipEditor.php?<?php echo "linkBack=" . $linkBack . "&DepositSlipID=".$iDepositSlipID?>" name="DepositSlipEditor">

<table cellpadding="3" align="center">

	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?php echo gettext("Save"); ?>" name="DepositSlipSubmit">
			<input type="button" class="btn" value="<?php echo gettext("Cancel"); ?>" name="DepositSlipCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';">
			<input type="button" class="btn" value="<?php echo gettext("Deposit Slip Report"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='Reports/PrintDeposit.php?BankSlip=<?php echo ($dep_Type == 'Bank')?>';">
			<input type="button" class="btn" value="Download OFX" name="DownloadOFX" onclick="javascript:document.location='Reports/ExportOFX.php?deposit=<?php echo $iDepositSlipID; ?>';">
			<input type="button" class="btn" value="<?php echo gettext("More Reports"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='FinancialReports.php';">
			<?php 
			if ($iDepositSlipID and $sDepositType and !$dep_Closed) {
				if ($sDepositType == "eGive") {
					echo "<input type=button class=btn value=\"".gettext("Import eGive")."\" name=ImporteGive onclick=\"javascript:document.location='eGive.php?DepositSlipID=$iDepositSlipID&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
				} else {
					echo "<input type=button class=btn value=\"".gettext("Add Payment")."\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?CurrentDeposit=$iDepositSlipID&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
				} ?>

				<?php if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') { ?>
					<input type="submit" class="btn" value="<?php echo gettext("Load Authorized Transactions"); ?>" name="DepositSlipLoadAuthorized">
    					<input type="submit" class="btn" value="<?php echo gettext("Run Transactions"); ?>" name="DepositSlipRunTransactions">
			    	<?php } ?>
		    <?php } ?>
		</td>
	</tr>

	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="DepositDate" size="11"><font color="red"><?php echo $sDateError ?></font></td>
			</tr>

			
			<?php
			if (!$iDepositSlipID or !$sDepositType) 
			{
				$selectOther = "";
				$selectCreditCard = "";
				$selectBankDraft = "";
				$selecteGive = "";
				
				echo "<tr><td class=LabelColumn>".gettext("Deposit Type:")."</td>";
				if ($sDepositType == "BankDraft")
					$selectBankDraft = "Checked ";
				elseif ($sDepositType == "CreditCard")
					$selectCreditCard = "Checked ";
				elseif ($sDepositType == "eGive")
					$selecteGive = "Checked ";
				else
					$selectOther = "Checked ";
				echo "<td class=TextColumn><input type=radio name=DepositType id=DepositType value=\"Bank\" $selectOther>".gettext("Bank")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"CreditCard\" $selectCreditCard>".gettext("Credit Card")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"BankDraft\" $selectBankDraft>".gettext("Bank Draft")." &nbsp; ";
				echo "<input type=radio name=DepositType id=DepositType value=\"eGive\" $selecteGive>".gettext("eGive")."</td></td>";
			} else {
				echo "<input type=hidden name=DepositType id=DepositType value=\"$sDepositType\"></td></td>";
			}
			?>
			
			<tr>
				<td class="LabelColumn"><?php echo gettext("Comment:"); ?></td>
				<td class="TextColumn"><input type="text" size=40 name="Comment" id="Comment" value="<?php echo $sComment; ?>"></td>
			</tr>

			<tr>
				<td class="LabelColumn"><?php echo gettext("Closed:"); ?></td>
				<td class="TextColumn"><input type="checkbox" name="Closed" value="1" <?php if ($bClosed) echo " checked";?>><?php echo gettext("Close deposit slip (remember to press Save)"); ?>
<?php 
				if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') {
					echo "<p>" . gettext("Important note: failed transactions will be deleted permanantly when the deposit slip is closed.") . "</p>";
				}
?>
			</tr>

		</table>
		</td>
	</form>
</table>

<br>
<?php if ($iDepositSlipID) {

	// Get deposit totals
	$sSQL = "SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($deposit_total) = mysql_fetch_row($rsDepositTotal);
	$sSQL = "SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CASH'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalCash) = mysql_fetch_row($rsDepositTotal);
	$sSQL = "SELECT SUM(plg_amount) FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CHECK'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalChecks) = mysql_fetch_row($rsDepositTotal);
	$sSQL = "SELECT COUNT(plg_plgID) AS deposit_total FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalItems) = mysql_fetch_row($rsDepositTotal);
	if (!$totalItems)
		$totalItems = "0";
	$sSQL = "SELECT COUNT(plg_plgID) AS deposit_total FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CASH'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalCashItems) = mysql_fetch_row($rsDepositTotal);
	if (!$totalCashItems)
		$totalCashItems = "0";
	$sSQL = "SELECT COUNT(plg_plgID) AS deposit_total FROM pledge_plg WHERE plg_depID = '$iDepositSlipID' AND plg_PledgeOrPayment = 'Payment' AND plg_method = 'CHECK'";
	$rsDepositTotal = RunQuery($sSQL);
	list ($totalCheckItems) = mysql_fetch_row($rsDepositTotal);
	if (!$totalCheckItems)
		$totalCheckItems = "0";
	echo "<b>\$$deposit_total - TOTAL AMOUNT </b> &nbsp; (Items: $totalItems)<br>";
	if ($totalCash)
		echo "<i><b>\$$totalCash - Total Cash </b> &nbsp; (Items: $totalCashItems)</i><br>";
	if ($totalChecks)
		echo "<i><b>\$$totalChecks - Total Checks</b> &nbsp; (Items: $totalCheckItems)</i><br>";
	echo "<br>";
?>

</div>
</div>

<div class="box">
<div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext("Payments on this deposit slip:"); ?></h3>
</div>
<div class="box-body">
<table class="table" id="paymentsTable">
</table>

<?php
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
        <p>Are you sure you want to delete the Payment?</p>
		<button type="button" class="btn btn-primary" id="deleteConfirmed" ><?php echo gettext("Delete"); ?></button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- End Delete Confirm Modal -->

<script type="text/javascript" src="js/DepositSlipEditor.js"></script>

<script>
var paymentData = <?php echo $financialService->getPayments($iDepositSlipID); ?>;
$("#DepositDate").datepicker({format:'yyyy-mm-dd'});

$(document).ready(function() {
    
    dataT = $("#paymentsTable").DataTable({
    data:paymentData.pledges,
    columns: [
    {
        width: '100px',
        title:'Family',
        data:'familyName',
        render: function  (data, type, full, meta ) {
            return '<a href=\'PledgeEditor.php?GroupKey='+full.plg_GroupKey+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>'+data; 
        }
    },
    {
        width: 'auto',
        title:'Date',
        data:'plg_date'
    },
    {
        width: 'auto',
        title:'Fiscal Year',
        data:'FiscalYear'
    },
    {
        width: 'auto',
        title:'Check Number',
        data:'plg_CheckNo',
    },
    {
        width: 'auto',
        title:'Fund',
        data:'plg_fundID',
    },
    {
        width: 'auto',
        title:'Amount',
        data:'plg_amount',
    }
    ,
    {
        width: 'auto',
        title:'Non Deductible',
        data:'plg_NonDeductible',
    }
    ,
    {
        width: 'auto',
        title:'Method',
        data:'plg_method',
    }
    ,
    {
        width: 'auto',
        title:'Comment',
        data:'plg_comment',
    }<?php
    if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') {?>,
    ,{
        width: 'auto',
        title:'Cleared',
        data:'plg_aut_Cleared',
    }<?php } 
    if ($dep_Type == 'BankDraft' || $dep_Type == 'CreditCard') {  ?>
    ,{
        width: 'auto',
        title:'Details',
        data:'plg_plgID',
        render: function  (data, type, full, meta ) {
            return '<a href=\'PledgeDetails.php?PledgeID='+data+'\'>Details</a>'
        }
    }<?php } ?>
    
    ]
});
});
   

</script>

<?php
require "Include/Footer.php";
?>
