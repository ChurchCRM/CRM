<?php
/*******************************************************************************
 *
 *  filename    : ElectronicPaymentLIst.php
 *  last change : 2014-11-29
 *  description : displays a list of all automatic payment records
 *
 *  http://www.churchcrm.io/
 *  Copyright 2014 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Security: User must be an Admin to access this page.
// Otherwise, re-direct them to the main menu.
if (!$_SESSION['bAdmin'])
{
	Redirect("Menu.php");
	exit;
}

// Get all the electronic payment records
$sSQL = "SELECT * FROM autopayment_aut INNER JOIN family_fam ON autopayment_aut.aut_FamID = family_fam.fam_ID LEFT JOIN donationfund_fun ON autopayment_aut.aut_Fund=donationfund_fun.fun_ID ORDER BY fam_Name";
$rsAutopayments = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Electronic Payment Listing");
require "Include/Header.php";
?>

<script language="javascript">
	function ConfirmDeleteAutoPayment (AutID)
{
	var famName = document.getElementById("FamName"+AutID).innerHTML;
	var r = confirm("Delete automatic payment for "+famName);
	if (r == true) {
		DeleteAutoPayment (AutID);
	} 
}

function ConfirmClearAccounts (AutID)
{
	var famName = document.getElementById("FamName"+AutID).innerHTML;
	var r = confirm("Clear account numbers for "+famName);
	if (r == true) {
		ClearAccounts (AutID);
	} 
}

function ClearAccounts (AutID)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.uniqueid = AutID;

    xmlhttp.open("GET","<?= RedirectURL("AutoPaymentClearAccounts.php") ?>?customerid="+AutID,true);
    xmlhttp.PaymentID = AutID; // So we can see it when the request finishes
    
    xmlhttp.onreadystatechange=function() {
		if (this.readyState==4 && this.status==200) { // Hide them as the requests come back, deleting would mess up the outside loop
            document.getElementById("Select"+this.PaymentID).checked = false;
        	ccVal = document.getElementById("CreditCard"+this.PaymentID).innerHTML;
        	document.getElementById("CreditCard"+this.PaymentID).innerHTML = "************" + ccVal.substr (ccVal.length-4,4);
        	aVal = document.getElementById("Account"+this.PaymentID).innerHTML;
        	document.getElementById("Account"+this.PaymentID).innerHTML = "*****" + aVal.substr (aVal.length-4,4);
        }
    };
    xmlhttp.send();
}

function DeleteAutoPayment (AutID)
{
    var xmlhttp = new XMLHttpRequest();
    xmlhttp.uniqueid = AutID;

    var params="Delete=1"; // post with Delete already set so the page goes straight into the delete
    	    
    xmlhttp.open("POST","<?= RedirectURL("AutoPaymentDelete.php") ?>?linkBack=<?= RedirectURL("ElectronicPaymentList.php") ?>&AutID="+AutID,true);
    xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xmlhttp.setRequestHeader("Content-length", params.length);
    xmlhttp.setRequestHeader("Connection", "close");
    xmlhttp.PaymentID = AutID; // So we can see it when the request finishes
    
    xmlhttp.onreadystatechange=function() {
		if (this.readyState==4 && this.status==200) { // Hide them as the requests come back, deleting would mess up the outside loop
             document.getElementById("Select"+this.PaymentID).checked = false;
			 document.getElementById("PaymentMethodRow"+this.PaymentID).style.display = 'none';
        }
    };
    xmlhttp.send(params);
}

function DeleteChecked()
{
	var checkboxes = document.getElementsByName("SelectForAction");
	for(var i=0, n=checkboxes.length;i<n;i++) {
	    if (checkboxes[i].checked) {
		    var id = checkboxes[i].id.split("Select")[1];
		    ConfirmDeleteAutoPayment (id);
	    }
	}
}

function ClearAccountsChecked()
{
	var checkboxes = document.getElementsByName("SelectForAction");
	for(var i=0, n=checkboxes.length;i<n;i++) {
	    if (checkboxes[i].checked) {
		    var id = checkboxes[i].id.split("Select")[1];
		    ConfirmClearAccounts (id);
	    }
	}
}

<?php if ($sElectronicTransactionProcessor == "Vanco") { ?>
function CreatePaymentMethodsForChecked()
{
	var checkboxes = document.getElementsByName("SelectForAction");
	for(var i=0, n=checkboxes.length;i<n;i++) {
	    if (checkboxes[i].checked) {
		    var id = checkboxes[i].id.split("Select")[1];
		    var xmlhttp = new XMLHttpRequest();
		    xmlhttp.uniqueid = id;
		    xmlhttp.open("GET","<?= RedirectURL("ConvertOnePaymentXML.php") ?>?autid="+id,true);
		    xmlhttp.onreadystatechange=function() {
				if (this.readyState==4 && this.status==200) {
		            var jsonresp=JSON.parse(this.response);
		            var index;
		            
		            var Success = false;
		            var ErrStr = "";
		            var AutID = 0;
		            var PaymentMethod = 0;
		            var PaymentType = "";
		            
		            for (index = 0; index < jsonresp.length; ++index) {
		                var oneResp = jsonresp[index];
		                if (oneResp.hasOwnProperty("Error"))
			                ErrStr += oneResp.Error;
		                if (oneResp.hasOwnProperty("AutID"))
		                	AutID = oneResp.AutID;
		                if (oneResp.hasOwnProperty("PaymentMethod"))
		                	PaymentMethod = oneResp.PaymentMethod[0];
		                if (oneResp.hasOwnProperty("Success"))
			                Success = oneResp.Success;
		                if (oneResp.hasOwnProperty("PaymentType"))
			                PaymentType = oneResp.PaymentType;
		            }

		            // Update fields on the page to show status of this action
		            if (Success && PaymentType=="CC")
			            document.getElementById("CreditCardVanco"+AutID).innerHTML = PaymentMethod;
		            if (Success && PaymentType=="C")
			            document.getElementById("AccountVanco"+AutID).innerHTML = PaymentMethod;
		            
		            if (!Success && PaymentType=="CC")
			            document.getElementById("CreditCardVanco"+AutID).innerHTML = ErrStr;
		            if (!Success && PaymentType=="C")
			            document.getElementById("AccountVanco"+AutID).innerHTML = ErrStr;

		            document.getElementById("Select"+AutID).checked = false;
	            }
		    };
		    xmlhttp.send();
	    }
	}
}
<?php } ?>
</script>

<script language="javascript">
	function toggle(source, groupName) {
	  var checkboxes = document.getElementsByName(groupName);
	  for(var i=0, n=checkboxes.length;i<n;i++) {
	    checkboxes[i].checked = source.checked;
  }
}
</script>

<p align="center"><a href="AutoPaymentEditor.php?linkBack=ElectronicPaymentList.php"><?= gettext("Add a New Electronic Payment Method") ?></a></p>

<table id="PaymentMethodTable" cellpadding="4" align="center" cellspacing="0" width="100%">
	<tr class="TableHeader">
		<td>
		<input type=checkbox onclick="toggle(this, 'SelectForAction')" />
		</td>
		<td align="center"><b><?= gettext("Family") ?></b></td>
		<td align="center"><b><?= gettext("Type") ?></b></td>
		<td align="center"><b><?= gettext("Fiscal Year") ?></b></td>
		<td align="center"><b><?= gettext("Next Date") ?></b></td>
		<td align="center"><b><?= gettext("Amount") ?></b></td>
		<td align="center"><b><?= gettext("Interval") ?></b></td>
		<td align="center"><b><?= gettext("Fund") ?></b></td>
		<td align="center"><b><?= gettext("Bank") ?></b></td>
		<td align="center"><b><?= gettext("Routing") ?></b></td>
		<td align="center"><b><?= gettext("Account") ?></b></td>
		<?php if ($sElectronicTransactionProcessor == "Vanco") {?> 
		<td align="center"><b><?= gettext("Vanco ACH") ?></b></td>
		<?php }?>
		<td align="center"><b><?= gettext("Credit Card") ?></b></td>
		<td align="center"><b><?= gettext("Month") ?></b></td>
		<td align="center"><b><?= gettext("Year") ?></b></td>
		<?php if ($sElectronicTransactionProcessor == "Vanco") {?> 
		<td align="center"><b><?= gettext("Vanco CC") ?></b></td>
		<?php }?>
		<td><b><?= gettext("Edit") ?></b></td>
		<td><b><?= gettext("Delete") ?></b></td>
	</tr>
<?php

//Set the initial row color
$sRowClass = "RowColorA";

//Loop through the autopayment records
while ($aRow = mysql_fetch_array($rsAutopayments)) {

	extract($aRow);

	//Alternate the row color
	$sRowClass = AlternateRowStyle($sRowClass);

	//Display the row
?>
	<tr id="PaymentMethodRow<?= $aut_ID ?>" class="<?= $sRowClass ?>">
		<td>
		<?php
			echo "<input type=checkbox id=Select$aut_ID name=SelectForAction />"; 
		?>
		</td>
		
		<td>
		<?php
			echo "<a id=\"FamName$aut_ID\" href=\"FamilyView.php?FamilyID=" . $fam_ID . "\">" . $fam_Name . " " . $fam_Address1 . ", " . $fam_City . ", " . $fam_State . "</a>";
		?>
		</td>

		<td>
		<?php 
			if ($aut_EnableBankDraft) 
		        echo "Bank ACH";
		    elseif ($aut_EnableCreditCard)
		    	echo "Credit Card";
		    else
		    	echo "Disabled";
		?>
		</td>

		<td><?= MakeFYString ($aut_FYID) ?></td>
		<td><?= $aut_NextPayDate ?></td>
		<td><?= $aut_Amount ?></td>
		<td><?= $aut_Interval ?></td>
		<td><?= $fun_Name ?></td>
		<td><?= $aut_BankName ?></td>
		<td><?php if (strlen($aut_Route)==9) echo "*****".substr($aut_Route,5,4);?></td>
		<td id="Account<?= $aut_ID ?>"><?php if (strlen($aut_Account)>4) echo "*****".substr($aut_Account,strlen($aut_Account)-4,4);?></td>
		<?php if ($sElectronicTransactionProcessor == "Vanco") {?> 
		<td align="center" id="AccountVanco<?= $aut_ID ?>"><?= $aut_AccountVanco ?></td>
		<?php }?>
		<td id="CreditCard<?= $aut_ID ?>"><?php if (strlen($aut_CreditCard)==16) echo "*************".substr($aut_CreditCard,12,4);?></td>
		<td><?= $aut_ExpMonth ?></td>
		<td><?= $aut_ExpYear ?></td>
		<?php if ($sElectronicTransactionProcessor == "Vanco") {?> 
		<td align="center" id="CreditCardVanco<?= $aut_ID ?>"><?= $aut_CreditCardVanco ?></td>
		<?php }?>
		<td><a href="AutoPaymentEditor.php?AutID=<?= $aut_ID ?>&amp;FamilyID=<?php echo $fam_ID?>&amp;linkBack=ElectronicPaymentList.php"><?= gettext("Edit") ?></a></td>
		<td><button onclick="ConfirmDeleteAutoPayment(<?= $aut_ID ?>)"><?= gettext("Delete") ?></button></td>
	</tr>
	<?php
}
?>
</table>
<b>With checked:</b>
<?php if ($sElectronicTransactionProcessor == "Vanco") { ?>
<input type="button" id="CreatePaymentMethodsForChecked" value="Store Private Data at Vanco" onclick="CreatePaymentMethodsForChecked();" />
<?php } ?>
<input type="button" id="DeleteChecked" value="Delete" onclick="DeleteChecked();" />
<input type="button" id="DeleteChecked" value="Clear Account Numbers" onclick="ClearAccountsChecked();" />

<?php require "Include/Footer.php" ?>
