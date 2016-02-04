<?php
/*******************************************************************************
 *
 *  filename    : PledgeEditor.php
 *  last change : 2012-06-29
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt
 *                Copyright 2004-2012Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

global $iChecksPerDepositForm;

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "service/FinancialService.php";

$financialService = new FinancialService();


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
$iFamily = 0;

$nAmount = array (); // this will be the array for collecting values for each fund
$sAmountError = array ();
$sComment = array ();

$checkHash = array();

$funds = $financialService->getFund();
$currencies = $financialService->getCurrency();


// Handle URL via _GET first
if (array_key_exists ("PledgeOrPayment", $_GET))
	$PledgeOrPayment = FilterInput($_GET["PledgeOrPayment"],'string');
$sGroupKey = "";
if (array_key_exists ("GroupKey", $_GET))
	$sGroupKey = FilterInput($_GET["GroupKey"],'string'); // this will only be set if someone pressed the 'edit' button on the Pledge or Deposit line
if (array_key_exists ("CurrentDeposit", $_GET))
	$iCurrentDeposit = FilterInput($_GET["CurrentDeposit"],'integer');
$linkBack = FilterInput($_GET["linkBack"],'string');
if (array_key_exists ("FamilyID", $_GET))
	$iFamily = FilterInput($_GET["FamilyID"],'int');

$fund2PlgIds = array(); // this will be the array cross-referencing funds to existing plg_plgid's

if ($sGroupKey) {
			// Security: User must have Finance permission or be the one who entered this record originally
			if (! ($_SESSION['bFinance'] || $_SESSION['iUserID']==$aRow["plg_EditedBy"])) {
				Redirect("Menu.php");
				exit;
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
<div class="box box-info">
	<div class="box-header">
		<h3 class="box-title">Pledge Details</h3>
	</div>
	<div class="box-body">
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <label for="date"><?php echo gettext("Date"); ?></label>
                <?php	if (!$dDate)	$dDate = $dep_Date ?>
                <input type="text" name="Date" value="<?php echo $dDate; ?>"  id="Date" >        
            
            </div>
           
                <!-- Start Donation Envelope Section -->
                <?php if ($dep_Type == 'Bank' and $bUseDonationEnvelopes) {?>
                    <div class="col-md-3">
                    <label for="Envelope"><?php echo gettext("Envelope #"); ?></label>
                    <input type="text" name="Envelope" size=8 id="Envelope" value="<?php echo $iEnvelope; ?>">
                    <?php if (!$dep_Closed) { ?>

                    <button type="button" class="btn btn-primary" value="<?php echo gettext("Find family->"); ?>" id="MatchEnvelope"><?php echo gettext("Find family->"); ?></button>
                    
                    <?php } ?>
                </div>
            
                <?php } ?>
                <!-- End Donation Envelope Section -->
                <!-- Start Recurring Pledge Section -->
               
                    <?php if ($PledgeOrPayment=='Pledge') { ?>
                         <div class="col-md-4">
                        <label for="Schedule"><?php echo gettext("Payment Schedule"); ?></label>
                            <select name="Schedule">
                                <option value="0"><?php echo gettext("Select Schedule"); ?></option>
                                <option value="Weekly" <?php if ($iSchedule == "Weekly") { echo "selected"; } ?>><?php echo gettext("Weekly"); ?></option>
                                <option value="Monthly" <?php if ($iSchedule == "Monthly") { echo "selected"; } ?>><?php echo gettext("Monthly"); ?></option>
                                <option value="Quarterly" <?php if ($iSchedule == "Quarterly") { echo "selected"; } ?>><?php echo gettext("Quarterly"); ?></option>
                                <option value="Once" <?php if ($iSchedule == "Once") { echo "selected"; } ?>><?php echo gettext("Once"); ?></option>
                                <option value="Other" <?php if ($iSchedule == "Other") { echo "selected"; } ?>><?php echo gettext("Other"); ?></option>
                            </select>
                        </div>
                    <?php }?>
                <!-- End Recurring Pledge Section -->
                <!-- Echo the verbiage for pledge / payment -->
                <div class="col-md-3">	
                    <label for="Method"><?php  echo gettext("Payment by"); ?></label>
                    <!-- Start Payment Method Section -->
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
                </div>
                <!-- End Payment Method Section -->
                 <div class="col-md-3">
                    <label for="grandTotal"><?php echo gettext("Total $"); ?></label>
                    <input id="grandTotal" type="text" name="TotalAmount" id="TotalAmount" value="<?php echo $iTotalAmount; ?>">
                </div>
        </div>
                    
            <div class="row">
                <div class="col-md-3">
                <!-- Start Fiscal Year Selection -->
                    <label for="FYID"><?php echo gettext("Fiscal Year"); ?></label>
                    <?php PrintFYIDSelect ($iFYID, "FYID") ?>
                </div>
                <!-- End Fiscal Year Selection -->
                <div class="col-md-3">
                <!-- Start Fund Selection (or Split Option) -->
                
                    <label for="FundSplit"><?php echo gettext("Fund"); ?></label>
                    <select name="FundSplit" id="FundSplit">
                        <option value="None" selected>Select a Fund</option>
                        <option value=0><?php echo gettext("Split");?></option>
                        <?php foreach ($funds as $fund) {
                            echo "<option value=\"" . $fund->ID . "\""; if ($iSelectedFund==$fund->ID) echo " selected"; echo ">"; echo gettext($fund->Name) . "</option>";
                        } ?>
                    </select>
                </div>
                <!-- End Fund Selection (or Split Option) -->
                <!-- Start Comment Section -->
                <div class="col-md-4" id="SingleComment">
                    <label for="OneComment"><?php  echo gettext("Comment"); ?></label>
                    <input type="text" name="OneComment" id="OneComment" value=" ">
                </div>
                <!-- End Comment Section -->
            </div>
            <div class="row">
                <div class="col-xs-8 col-md-8">
                    <label for="FamilyName"><?php echo gettext("Family"); ?></label>
                    <select style="width:100%" name="FamilyName" id="FamilyName">
                        <option value="<?php echo $sFamilyName; ?>"><?php echo $sFamilyName; ?></option>
                    </select>
                </div>
            </div>	
            <input type="hidden" id="FamilyID" name="FamilyID" value='<?php echo $iFamily; ?>'>
            <input type="hidden" id="DepositID" name="DepositID" value='<?php echo $_GET['CurrentDeposit']; ?>'>
           
        </div>
        
<!-- End Pledge Details -->
</div>
</div>


<div class="container" style="margin:0px; width:100%">
    <div class="row">
        <div class="col-md-6">
            <!--Start Credit card or Bank Draft Section -->
            <?php if (($dep_Type == 'CreditCard') or ($dep_Type == 'BankDraft')) {?>
                <div class="box box-info">
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

            <!-- Start Cash Denomination Enter Section -->
            <div class="box box-info" id="CashEnter" style="display:none">
                <div class="box-header">
                    <h3 class="box-title">Cash Denominations</h3>
                </div><!-- /.box-header -->
                <div class="box-body">
                    <div class="row">
                    <?php 
                        $lastClass=$currencies[0]->cClass;
                        foreach ($currencies as $currency)
                        {
                            if (!($lastClass == $currency->cClass) )
                            {
                                $lastClass=$currency->cClass;
                                echo '</div><br><br><div class="row">';
                            }
                        echo '<div class="col-md-4">';
                        echo '<label for="currencyCount-'.$currency->id.'">'.$currency->Name.'</label>';
                        echo "<input type=\"text\" class=\"denominationInputBox\" data-cur-value=\"".$currency->Value."\" name=\"currencyCount-".$currency->id."\"></div>";
                        
                        }?>
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
            <!-- End Check Denomination Enter Section -->
        </div>

        <div class="col-md-6">
            <!-- Start Fund Selection Section -->
                    <div class="box box-info" id="FundSelection" style="display:none">
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
                                        <?php foreach ($funds as $fund) {
                                            echo "<tr class=\"fundrow\" id=\"fundrow_". $fund->ID."\" >";
                                            echo "<td>".$fund->Name."</td>";
                                            echo "<td><input type=\"text\" class=\"fundSplitInputBox\" name=\"" . $fund->ID . "_Amount\" id=\"" . $fund->ID . "_Amount\" value=\"" . $nAmount[$fun_id] . "\"><br><font color=\"red\">" . $sAmountError[$fun_id] . "</font></td>";
                                            if ($bEnableNonDeductible) {
                                            echo "<td><input type=\"text\" class=\"fundSplitInputBox\" name=\"" . $fund->ID . "_NonDeductible\" id=\"" . $fund->ID . "_Amount\" value=\"" . $nNonDeductible[$fun_id] . "\"><br><font color=\"red\">" . $sAmountError[$fun_id] . "</font></td>";
                                            }
                                            echo "<td><input type=\"text\" name=\"" . $fund->ID . "_Comment\" id=\"" . $fund->ID . "_Comment\" value=\"" . $sComment[$fun_id] . "\"></td>";
                                            echo "</tr>";
                                        }
                                        ?>
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
            <!-- End Fund Selection Section -->
        </div>
    </div>
</div>


<!--Start Save button section -->
		<div class="box box-info">
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

<script>
$("#Date").datepicker({format:'yyyy-mm-dd'});

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

<?php
if ($sGroupKey) {
	?><script type="text/javascript">
		<?php 
			require_once "service/FinancialService.php"; 
			$FinancialService = new FinancialService();
echo "var thisPayment = " . $FinancialService->getPledgeorPayment($sGroupKey) ;
		?>
	</script><?php
}
require "Include/Footer.php";
?>
