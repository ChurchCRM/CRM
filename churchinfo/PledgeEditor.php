<?php
/*******************************************************************************
 *
 *  filename    : PledgeEditor.php
 *  last change : 2012-06-29
 *  website     : http://www.churchdb.org
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2012Michael Wilt
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

global $iChecksPerDepositForm;

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";



$thisPledgeID = 0;
$iEnvelope = 0;
$sCheckNoError = "";
$iCheckNo = "";
$sDateError = "";
$sAmountError = "";
$iTotalAmount = 0;
$nNonDeductible = array ();
$sComment = "";
$tScanString = "";
$dep_Closed = false;
$iAutID = 0;
$iCurrentDeposit = 0;

$nAmount = array (); // this will be the array for collecting values for each fund
$sAmountError = array ();
$sComment = array ();

$checkHash = array();

$currencyDenomination2Name=array();
$currencyDenominationValue=array();

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun";
$sSQL .= " WHERE fun_Active = 'true'"; // New donations should show only active funds.

$rsFunds = RunQuery($sSQL);
mysql_data_seek($rsFunds,0);
while ($aRow = mysql_fetch_array($rsFunds)) {
	extract($aRow);
	$fundId2Name[$fun_ID] = $fun_Name;
	$nAmount[$fun_ID] = 0.0;
	$nNonDeductible[$fun_ID] = 0.0;
	$sAmountError[$fun_ID] = "";
	$sComment[$fun_ID] = "";
	if (!isset($defaultFundID)) {
		$defaultFundID = $fun_ID;
	}
	$fundIdActive[$fun_ID] = $fun_Active;
} // end while

// Get the list of Currency denominations
$sSQL = "SELECT * FROM currency_denominations_cdem";
$rscurrencyDenomination = RunQuery($sSQL);
mysql_data_seek($rscurrencyDenomination,0);
while ($aRow = mysql_fetch_array($rscurrencyDenomination)) {
	extract($aRow);
	$currencyDenomination2Name[$cdem_denominationID] = $cdem_denominationName;
	$currencyDenominationValue[$cdem_denominationID] = $cdem_denominationValue;
} // end while


// Handle URL via _GET first
if (array_key_exists ("PledgeOrPayment", $_GET))
	$PledgeOrPayment = FilterInput($_GET["PledgeOrPayment"],'string');
$sGroupKey = "";
if (array_key_exists ("GroupKey", $_GET))
	$sGroupKey = FilterInput($_GET["GroupKey"],'string'); // this will only be set if someone pressed the 'edit' button on the Pledge or Deposit line
if (array_key_exists ("CurrentDeposit", $_GET))
	$iCurrentDeposit = FilterInput($_GET["CurrentDeposit"],'integer');
$linkBack = FilterInput($_GET["linkBack"],'string');
$iFamily = 0;
if (array_key_exists ("FamilyID", $_GET))
	$iFamily = FilterInput($_GET["FamilyID"],'int');

$fund2PlgIds = array(); // this will be the array cross-referencing funds to existing plg_plgid's

if ($sGroupKey) {
	$sSQL = "SELECT plg_plgID, plg_fundID, plg_EditedBy from pledge_plg where plg_GroupKey=\"" . $sGroupKey . "\"";
	$rsKeys = RunQuery($sSQL);
	while ($aRow = mysql_fetch_array($rsKeys)) {
		$onePlgID = $aRow["plg_plgID"];
		$oneFundID = $aRow["plg_fundID"];
		$iOriginalSelectedFund = $oneFundID; // remember the original fund in case we switch to splitting
		$fund2PlgIds[$oneFundID] = $onePlgID;

		// Security: User must have Finance permission or be the one who entered this record originally
		if (! ($_SESSION['bFinance'] || $_SESSION['iUserID']==$aRow["plg_EditedBy"])) {
			Redirect("Menu.php");
			exit;
		}	
	}
}

// Handle _POST input if the form was up and a button press came in
 else { // Form was not up previously, take data from existing records or make default values
	if ($sGroupKey) {
		$sSQL = "SELECT COUNT(plg_GroupKey), plg_PledgeOrPayment, plg_fundID, plg_Date, plg_FYID, plg_CheckNo, plg_Schedule, plg_method, plg_depID FROM pledge_plg WHERE plg_GroupKey='" . $sGroupKey . "' GROUP BY plg_GroupKey";
		$rsResults = RunQuery($sSQL);
		list($numGroupKeys, $PledgeOrPayment, $fundId, $dDate, $iFYID, $iCheckNo, $iSchedule, $iMethod, $iCurrentDeposit) = mysql_fetch_row($rsResults);
		if ($numGroupKeys > 1) {
			$iSelectedFund = 0;
		} else {
			$iSelectedFund = $fundId;
		}
		
		$iTotalAmount = 0;
		$sSQL = "SELECT DISTINCT plg_famID, plg_CheckNo, plg_date, plg_method, plg_FYID from pledge_plg where plg_GroupKey='" . $sGroupKey . "'";
	 	//	don't know if we need plg_date or plg_method here...  leave it here for now
		$rsFam = RunQuery($sSQL);
		extract(mysql_fetch_array($rsFam));
	
		$iFamily = $plg_famID;
		$iCheckNo = $plg_CheckNo;
		$iFYID = $plg_FYID;
	
		$sSQL = "SELECT plg_plgID, plg_fundID, plg_amount, plg_comment from pledge_plg where plg_GroupKey='" . $sGroupKey . "'";
	
		$rsAmounts = RunQuery($sSQL);
		while ($aRow = mysql_fetch_array($rsAmounts)) {
			extract($aRow);
			$nAmount[$plg_fundID] = $plg_amount;
			$sComment[$plg_fundID] = $plg_comment;
			$iTotalAmount += $plg_amount;
		}
	} else {
		if (array_key_exists ('idefaultDate', $_SESSION))
			$dDate = $_SESSION['idefaultDate'];
		else
			$dDate = date ("Y-m-d");
		if (array_key_exists ('iSelectedFund', $_SESSION))
			$iSelectedFund = $_SESSION['iSelectedFund'];
		else
			$iSelectedFund = 0;
	 	$fundId = $iSelectedFund;
	 	if (array_key_exists ('idefaultFY', $_SESSION))
			$iFYID = $_SESSION['idefaultFY'];
		else
			$iFYID = CurrentFY ();
	 	if (array_key_exists ('iDefaultSchedule', $_SESSION))
			$iSchedule = $_SESSION['iDefaultSchedule'];
		else
			$iSchedule = 'Once';
		if (array_key_exists ('idefaultPaymentMethod', $_SESSION))
			$iMethod = $_SESSION['idefaultPaymentMethod'];
		else
			$iMethod = 'Check';
	}
	if (!$iEnvelope and $iFamily) {
		$sSQL = "SELECT fam_Envelope FROM family_fam WHERE fam_ID=\"" . $iFamily . "\";";
		$rsEnv = RunQuery($sSQL);
		extract(mysql_fetch_array($rsEnv));
		if ($fam_Envelope) {
			$iEnvelope = $fam_Envelope;
		}
	}
}

if ($PledgeOrPayment == 'Pledge') { // Don't assign the deposit slip if this is a pledge
	$iCurrentDeposit = 0;
} else { // its a deposit
	if ($iCurrentDeposit > 0) {
		$_SESSION['iCurrentDeposit'] = $iCurrentDeposit;
	} else {
		$iCurrentDeposit = $_SESSION['iCurrentDeposit'];
	}

	// Get the current deposit slip data
	if ($iCurrentDeposit) {
		$sSQL = "SELECT dep_Closed, dep_Date, dep_Type from deposit_dep WHERE dep_ID = " . $iCurrentDeposit;
		$rsDeposit = RunQuery($sSQL);
		extract(mysql_fetch_array($rsDeposit));
	}
}

if ($iMethod == "CASH" or $iMethod == "CHECK")
	$dep_Type = "Bank";
elseif ($iMethod == "CREDITCARD")
	$dep_Type = "CreditCard";
elseif ($iMethod == "BANKDRAFT")
	$dep_Type = "BankDraft";

if ($PledgeOrPayment == 'Payment') {
	$bEnableNonDeductible = 1; // this could/should be a config parm?  regardless, having a non-deductible amount for a pledge doesn't seem possible
}




// Set Current Deposit setting for user
if ($iCurrentDeposit) {
	$sSQL = "UPDATE user_usr SET usr_currentDeposit = '$iCurrentDeposit' WHERE usr_per_id = \"".$_SESSION['iUserID']."\"";
	$rsUpdate = RunQuery($sSQL);
}

//Set the page title
if ($PledgeOrPayment == 'Pledge') {
	$sPageTitle = gettext("Pledge Editor");
} elseif ($iCurrentDeposit) {
	$sPageTitle = gettext("Payment Editor: ") . $dep_Type . gettext(" Deposit Slip #") . $iCurrentDeposit . " ($dep_Date)";

	// form assumed by Reports/PrintDeposit.php. 
	$checksFit = $iChecksPerDepositForm;

	$sSQL = "SELECT plg_FamID, plg_plgID, plg_checkNo, plg_method from pledge_plg where plg_method=\"CHECK\" and plg_depID=" . $iCurrentDeposit;
	$rsChecksThisDep = RunQuery ($sSQL);
	$depositCount = 0;
	while ($aRow = mysql_fetch_array($rsChecksThisDep)) {
		extract($aRow);
		$chkKey = $plg_FamID . "|" . $plg_checkNo;
		if ($plg_method=='CHECK' and (!array_key_exists($chkKey, $checkHash))) {
			$checkHash[$chkKey] = $plg_plgID;
			++$depositCount;
		}
	}

	//$checkCount = mysql_num_rows ($rsChecksThisDep);
	$roomForDeposits = $checksFit - $depositCount;
	if ($roomForDeposits <= 0)
		$sPageTitle .= "<font color=red>";
	$sPageTitle .= "<br>(" . $roomForDeposits . gettext (" more entries will fit.") . ")";
	if ($roomForDeposits <= 0)
		$sPageTitle .= "</font>";
} else { // not a plege and a current deposit hasn't been created yet
	if ($sGroupKey) {
		$sPageTitle = gettext("Payment Editor - Modify Existing Payment");
	} else {
		$sPageTitle = gettext("Payment Editor - New Deposit Slip Will Be Created");
	}
} // end if $PledgeOrPayment

if ($dep_Closed && $sGroupKey && $PledgeOrPayment == 'Payment') {
	$sPageTitle .= " &nbsp; <font color=red>Deposit closed</font>";
}			

//$familySelectHtml = buildFamilySelect($iFamily, $sDirRoleHead, $sDirRoleSpouse);
$sFamilyName = "";
if ($iFamily) {
    $sSQL = "SELECT fam_Name, fam_Address1, fam_City, fam_State FROM family_fam WHERE fam_ID =" . $iFamily;
    $rsFindFam = RunQuery($sSQL);
    while ($aRow = mysql_fetch_array($rsFindFam))
    {
        extract($aRow);
        $sFamilyName = $fam_Name . " " . FormatAddressLine($fam_Address1, $fam_City, $fam_State);
    }
}

require "Include/Header.php";

if (true) //If the requested page is to edit a deposit, then we need to get the data
{
?>
<script>

//Render a JS Object here that represents the currently selected payment entry so that we can use JQuery to set up the form later on.

</script>	
<?php
	
}

?>


<form id="PledgeForm" action="PledgeEditor.php?<?php echo "CurrentDeposit=" . $iCurrentDeposit . "&GroupKey=" . $sGroupKey . "&PledgeOrPayment=" . $PledgeOrPayment. "&linkBack=" . $linkBack; ?>" name="PledgeEditor">

<input type="hidden" name="FamilyID" id="FamilyID" value="<?php echo $iFamily; ?>">
<input type="hidden" name="PledgeOrPayment" id="PledgeOrPayment" value="<?php echo $PledgeOrPayment; ?>">
<!-- Start Pledge Details Section -->
<div class="box box-info clearfix">
	<div class="box-header">
		<h3 class="box-title">Pledge Details</h3>
	</div>
	<div class="box-body">
	<div class="table-responsive">
		<table class="table table-striped">
				<thead>
				</thead>
				<tbody>
				<!-- Start Donation Envelope Section -->
			<?php if ($dep_Type == 'Bank' and $bUseDonationEnvelopes) {?>
			<tr>
				<td class="PaymentLabelColumn"><?php echo gettext("Envelope #"); ?></td>
				<td class="TextColumn"><input type="text" name="Envelope" size=8 id="Envelope" value="<?php echo $iEnvelope; ?>">
				<?php if (!$dep_Closed) { ?>
				<button type="button" class="btn btn-primary" value="<?php echo gettext("Find family->"); ?>" id="MatchEnvelope"><?php echo gettext("Find family->"); ?></button>
				<?php } ?>
			</td>
			</tr>
			<?php } ?>
			<!-- End Donation Envelope Section -->
			<!-- Start Recurring Pledge Section -->
			<tr>
				<?php if ($PledgeOrPayment=='Pledge') { ?>
					<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Payment Schedule"); ?></td>
					<td class="TextColumnWithBottomBorder">
						<select name="Schedule">
							<option value="0"><?php echo gettext("Select Schedule"); ?></option>
							<option value="Weekly" <?php if ($iSchedule == "Weekly") { echo "selected"; } ?>><?php echo gettext("Weekly"); ?></option>
							<option value="Monthly" <?php if ($iSchedule == "Monthly") { echo "selected"; } ?>><?php echo gettext("Monthly"); ?></option>
							<option value="Quarterly" <?php if ($iSchedule == "Quarterly") { echo "selected"; } ?>><?php echo gettext("Quarterly"); ?></option>
							<option value="Once" <?php if ($iSchedule == "Once") { echo "selected"; } ?>><?php echo gettext("Once"); ?></option>
							<option value="Other" <?php if ($iSchedule == "Other") { echo "selected"; } ?>><?php echo gettext("Other"); ?></option>
						</select>
					</td>
				<?php }?>
			<!-- End Recurring Pledge Section -->
			</tr>
			<tr>
			<!-- Echo the verbiage for pledge / payment -->
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; echo gettext("Payment by"); ?></td>
				<!-- Start Payment Method Section -->
				<td class="TextColumnWithBottomBorder">
					<select name="Method" id="PaymentByMethod">
						<option value="None" selected>Select a Payment Method</option>
						<?php if ($PledgeOrPayment=='Pledge' or $dep_Type == "Bank" or !$iCurrentDeposit) { ?>
						<option value="CHECK"><?php echo gettext("CHECK");?></option>
						<option value="CASH"><?php echo gettext("CASH");?></option>
						<?php } ?>
						<?php if ($PledgeOrPayment=='Pledge' or $dep_Type == "CreditCard" or !$iCurrentDeposit) { ?>
						<option value="CREDITCARD"><?php echo gettext("Credit Card"); ?></option>
						<?php } ?>
						<?php if ($PledgeOrPayment=='Pledge' or $dep_Type == "BankDraft" or !$iCurrentDeposit) { ?>
						<option value="BANKDRAFT"><?php echo gettext("Bank Draft"); ?></option>
						<?php } ?>
                                                <?php if ($PledgeOrPayment=='Pledge') { ?>
                                                <option value="EGIVE" <?php if ($iMethod == "EGIVE") { echo "selected"; } ?>><?php echo
                          gettext("eGive"); ?></option>
                                                <?php } ?>
					</select>
				</td>
				<!-- End Payment Method Section -->
			</tr>
			<!-- Start Fiscal Year Selection -->
			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Fiscal Year"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<?php PrintFYIDSelect ($iFYID, "FYID") ?>
				</td>
			</tr>
			<!-- End Fiscal Year Selection -->
			<!-- Start Fund Selection (or Split Option) -->
			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; echo gettext("Fund"); ?></td>
				<td class="TextColumnWithBottomBorder">
					<select name="FundSplit" id="FundSplit">
						<option value="None" selected>Select a Fund</option>
						<option value=0><?php echo gettext("Split");?></option>
						<?php foreach ($fundId2Name as $fun_id => $fun_name) {
							echo "<option value=\"" . $fun_id . "\""; if ($iSelectedFund==$fun_id) echo " selected"; echo ">"; echo gettext($fun_name) . "</option>";
						} ?>
					</select>
				</td>
			</tr>
			<!-- End Fund Selection (or Split Option) -->
			<!-- Start Comment Section -->
			<tr id="SingleComment">
				<td valign="top" align="left" <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; echo gettext("Comment"); ?></td>
				<td <?php echo "class=\"TextColumnWithBottomBorder\">"; echo "<input type=\"text\" name=\"OneComment\" id=\"OneComment\" value=\" \""; ?>">
			</tr>
			<!-- Start Comment Section -->
		
			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\""; else echo "class=\"PaymentLabelColumn\""; ?><?php addToolTip("Select the pledging family from the list."); ?>><?php echo gettext("Family"); ?></td>
				<td class="TextColumn">

<script language="javascript" type="text/javascript">
$(document).ready(function() {
	$("#FamilyName").autocomplete({
		source: function (request, response) {
			$.ajax({
				url: 'api/families/search/'+request.term,
				dataType: 'json',
				type: 'GET',
				success: function (data) {
					response($.map(data.families, function (item) {
						return {
                            value: item.displayName,
                            id: item.id
						}
					}));
				}
			})
		},
		minLength: 2,
		select: function(event,ui) {
			$('[name=FamilyName]').val(ui.item.value);
			$('[name=FamilyID]:eq(1)').val(ui.item.id);
		}
	});
});
</script>

					<input style='width:350px;' type="text" id="FamilyName" name="FamilyName" value='<?php echo $sFamilyName; ?>' />
					<input type="hidden" id="FamilyID" name="FamilyID" value='<?php echo $iFamily; ?>'>
					<input type="hidden" id="DepositID" name="DepositID" value='<?php echo $_GET['CurrentDeposit']; ?>'>
				</td>
			</tr>

			



			<tr>
				<td <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\""; else echo "class=\"PaymentLabelColumn\""; ?><?php addToolTip("Format: YYYY-MM-DD<br>or enter the date by clicking on the calendar icon to the right."); ?>><?php echo gettext("Date"); ?></td>
<?php	if (!$dDate)	$dDate = $dep_Date ?>
	
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $dDate; ?>" maxlength="10" id="sel1" size="11">&nbsp;<input type="image" onclick="return showCalendar('sel1', 'y-mm-dd');" src="Images/calendar.gif"> <span class="SmallText"><?php echo gettext("[format: YYYY-MM-DD]"); ?></span><font color="red"><?php echo $sDateError ?></font></td>
			</tr>
			<tr> 
			<td valign="top" align="left" <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; echo gettext("Total $"); ?></td>
			<td <?php echo "class=\"TextColumnWithBottomBorder\">"; echo "<input id=\"grandTotal\" type=\"text\" name=\"TotalAmount\" id=\"TotalAmount\" value=\" ". $iTotalAmount . "\""; ?>">
		    

<!--Start Credit card or Bank Draft Section -->
<?php if (($dep_Type == 'CreditCard') or ($dep_Type == 'BankDraft')) {?>
	<div class="box box-info clearfix">
	<div class="box-header">
		<h3 class="box-title">Credit Card / Bank Draft</h3>
	</div>
	<div class="box-body">
	<div class="table-responsive">
			<table class="table table-striped">
				<thead>
				</thead>
				<tbody>
					<tr>
						<td <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">";echo gettext("Choose online payment method");?></td>
						<td class="TextColumnWithBottomBorder">
							<select name="AutoPay">
		<?php
							echo "<option value=0";
							if ($iAutID == 0)
								echo " selected";
							echo ">" . gettext ("Select online payment record") . "</option>\n";
							$sSQLTmp = "SELECT aut_ID, aut_CreditCard, aut_BankName, aut_Route, aut_Account FROM autopayment_aut WHERE aut_FamID=" . $iFamily;
							$rsFindAut = RunQuery($sSQLTmp);
							while ($aRow = mysql_fetch_array($rsFindAut))
							{
								extract($aRow);
								if ($aut_CreditCard <> "") {
									$showStr = gettext ("Credit card ...") . substr ($aut_CreditCard, strlen ($aut_CreditCard) - 4, 4);
								} else {
									$showStr = gettext ("Bank account ") . $aut_BankName . " " . $aut_Route . " " . $aut_Account;
								}
								echo "<option value=" . $aut_ID;
								if ($iAutID == $aut_ID)
									echo " selected";
								echo ">" . $showStr . "</option>\n";
							}?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		</div>
	</div>
	<?php } ?>
<!--End Credit card or Bank Draft Section -->

	
		

						</tr>
						</tbody>
					</table>
<!-- End Pledge Details -->
</div>
</div>
</div>




<!-- Start Cash Denomination Enter Section -->

		<div class="box box-info clearfix" id="CashEnter" style="display:none">
			<div class="box-header">
				<h3 class="box-title">Cash Denominations</h3>
			</div><!-- /.box-header -->
			<div class="box-body">
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<th>Denomination</th>
							<th>Count</th>
							<th>Total</th>
						</thead>
						<tbody>
				<?php 
				
					foreach ($currencyDenomination2Name as $cdem_denominationID =>$cdem_denominationName)
					{
					echo "<tr><td>";
					echo $cdem_denominationName;
					echo "</td><td><input type=\"text\" class=\"denominationInputBox\" data-cur-value=\"".$currencyDenominationValue[$cdem_denominationID]."\" name=\"currencyCount-".$cdem_denominationID."\"></td>";
					
					}?>
				
						</tbody>
					</table>
				</div>
			</div>
		</div>
		

	
<!-- End Cash Denomination Enter Section -->


<!-- Start Check Details Enter Section -->

		<div class="box box-info clearfix" id="CheckEnter" style="display:none">
			<div class="box-header">
				<h3 class="box-title">Check Details</h3>
			</div><!-- /.box-header -->
			<div class="box-body">
				<div class="table-responsive">
					<table class="table table-striped">
						<tbody>
						<!-- Start Scanned Check Section -->
		<?php if ($bUseScannedChecks and ($dep_Type == 'Bank' or $PledgeOrPayment=='Pledge')) {?>
		
		
		<td align="center">
		<?php if ($dep_Type == 'Bank' and $bUseScannedChecks) { ?>
			<button type="button" class="btn btn-primary" value="<?php echo gettext("find family from check account #"); ?>" id="MatchFamily"><?php echo gettext("find family from check account #"); ?></button>
			<button  type="button" class="btn btn-primary" value="<?php echo gettext("Set default check account number for family"); ?>" id="SetDefaultCheck"><?php echo gettext("Set default check account number for family"); ?></button>
		<?php } ?>
	
		
		
		
		
		<td <?php  if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\" align=\"center\">"; else echo "class=\"PaymentLabelColumn\" align=\"center\">";echo gettext("Scan check");?>
		<textarea name="ScanInput" rows="2" cols="70"><?php echo $tScanString?></textarea></td>
		<?php } ?>
		<!-- End Scanned Check Section -->
		<!-- Start Paper Check Section -->

											
		<?php if ($PledgeOrPayment=='Payment' and $dep_Type == 'Bank') {?>
		<tr>
		<td class="PaymentLabelColumn"><?php echo gettext("Check #"); ?></td>
		<td class="TextColumn"><input type="text" name="CheckNo" id="CheckNo" value="<?php echo $iCheckNo; ?>"><font color="red"><?php echo $sCheckNoError ?></font></td>
		</tr>
		<?php } ?>
						</tbody>
						
					</table>
				</div>
			</div>
		</div>

	
<!-- End Cash Denomination Enter Section -->


<!-- Start Fund Selection Section -->

		<div class="box box-info clearfix" id="FundSelection" style="display:none">
			<div class="box-header">
				<h3 class="box-title">Fund Split</h3>
				<h4></h4>
			</div><!-- /.box-header -->
			<div class="box-body">
				<div class="table-responsive">
					<table class="table table-striped">
						<thead>
							<th <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Fund Name"); ?></th>
							<th <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Amount"); ?></th>

							<?php if ($bEnableNonDeductible) {?>
								<th <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Non-deductible amount"); ?></th>
							<?php }?>

							<th <?php if ($PledgeOrPayment=='Pledge') echo "class=\"LabelColumn\">"; else echo "class=\"PaymentLabelColumn\">"; ?><?php echo gettext("Comment"); ?></th>
						</thead>
						<tbody>
							<?php foreach ($fundId2Name as $fun_id => $fun_name) {
								echo "<tr class=\"fundrow\" id=\"fundrow_". $fun_id."\" >";
								echo "<td>".$fun_name."</td>";
								echo "<td><input type=\"text\" class=\"fundSplitInputBox\" name=\"" . $fun_id . "_Amount\" id=\"" . $fun_id . "_Amount\" value=\"" . $nAmount[$fun_id] . "\"><br><font color=\"red\">" . $sAmountError[$fun_id] . "</font></td>";
								if ($bEnableNonDeductible) {
								echo "<td><input type=\"text\" class=\"fundSplitInputBox\" name=\"" . $fun_id . "_NonDeductible\" id=\"" . $fun_id . "_Amount\" value=\"" . $nNonDeductible[$fun_id] . "\"><br><font color=\"red\">" . $sAmountError[$fun_id] . "</font></td>";
								}
								echo "<td><input type=\"text\" size=40 name=\"" . $fun_id . "_Comment\" id=\"" . $fun_id . "_Comment\" value=\"" . $sComment[$fun_id] . "\"></td>";
								echo "</tr>";
							}
							?>
							</tbody>
					</table>
				</div>
			</div>
		</div>
		

	
<!-- End Fund Selection Section -->


<!--Start Save button section -->
		<div class="box box-info clearfix">
			<div class="box-body">
				<?php if (!$dep_Closed) { ?>
				<button type="submit" class="btn btn-primary" value="<?php echo gettext("Save"); ?>" id="PledgeSubmit" name="PledgeSubmit"><?php echo gettext("Save"); ?></button>
				<?php if ($_SESSION['bAddRecords']) { echo "<button type=\"submit\" class=\"btn btn-primary\" value=\"" . gettext("Save and Add") . "\" id=\"PledgeSubmitAndAdd\" name=\"PledgeSubmitAndAdd\">". gettext("Save and Add") ."</button>"; } ?>
			<?php } ?>
				<?php if (!$dep_Closed) {
					$cancelText = "Cancel";
				} else {
					$cancelText = "Return";
				} ?>	
				<button type="button" class="btn btn-primary" value="<?php echo gettext($cancelText); ?>" name="PledgeCancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) { echo $linkBack; } else {echo "Menu.php"; } ?>';"><?php echo gettext($cancelText); ?></button>
				<button type="button" class="btn btn-primary" name="ResetForm" id="ResetForm"><?php echo gettext("Reset Form"); ?></button>

			</div>
		</div>
<!--End Save button section -->
</form>
<script type="text/javascript" src="js/PledgeEditor.js"></script>

<?php

require "Include/Footer.php";
?>
