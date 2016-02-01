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
$linkBack = "";
$iDepositSlipID = 0;
$sDateError = "";
$thisDeposit = "";

if (array_key_exists ("DepositSlipID", $_GET))
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
	if (! ($_SESSION['bFinance'] || $_SESSION['iUserID']==$thisDeposit->dep_EnteredBy)) {
		Redirect("Menu.php");
		exit;
	}
}
else
{
    Redirect("Menu.php");
}

//Set the page title
$sPageTitle = $thisDeposit->dep_Type . " " . gettext("Deposit Slip Number: ") . $iDepositSlipID;
	
//Is this the second pass?
if (isset($_POST["DepositSlipLoadAuthorized"])) 
{
    $financialService->loadAuthorized($iDepositSlipID);
}
else if (isset($_POST["DepositSlipRunTransactions"])) 
{
    $financialService->runTransactions($iDepositSlipID);
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
<form method="post" action="#" name="DepositSlipEditor" id="DepositSlipEditor">
<table cellpadding="3" align="center">
	<tr>
		<td align="center">
			<input type="submit" class="btn" value="<?php echo gettext("Save"); ?>" name="DepositSlipSubmit">
			<input type="button" class="btn" value="<?php echo gettext("Deposit Slip Report"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='Reports/PrintDeposit.php?BankSlip=<?php echo ($thisDeposit->dep_Type == 'Bank')?>';">
			<input type="button" class="btn" value="Download OFX" name="DownloadOFX" onclick="javascript:document.location='Reports/ExportOFX.php?deposit=<?php echo $iDepositSlipID; ?>';">
			<input type="button" class="btn" value="<?php echo gettext("More Reports"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location='FinancialReports.php';">
			
		</td>
	</tr>
	<tr>
		<td>
		<table cellpadding="3">
			<tr>
				<td class="LabelColumn"><?php echo gettext("Date:"); ?></td>
				<td class="TextColumn"><input type="text" name="Date" value="<?php echo $thisDeposit->dep_Date; ?>" maxlength="10" id="DepositDate" size="11"><font color="red"><?php echo $sDateError ?></font></td>
			</tr>			
			<tr>
				<td class="LabelColumn"><?php echo gettext("Comment:"); ?></td>
				<td class="TextColumn"><input type="text" size=40 name="Comment" id="Comment" value="<?php echo $thisDeposit->dep_Comment; ?>"></td>
			</tr>
			<tr>
				<td class="LabelColumn"><?php echo gettext("Closed:"); ?></td>
				<td class="TextColumn"><input type="checkbox" name="Closed" id="Closed" value="1" <?php if ($thisDeposit->dep_Closed) echo " checked";?>><?php echo gettext("Close deposit slip (remember to press Save)"); ?>
<?php 
				if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {
					echo "<p>" . gettext("Important note: failed transactions will be deleted permanantly when the deposit slip is closed.") . "</p>";
				}
?>
			</tr>
		</table>
		</td>
	</form>
</table>

<br>
<?php 
	// Get deposit totals
	echo "<b>".$thisDeposit->dep_Total." - TOTAL AMOUNT </b> &nbsp; (Items: $thisDeposit->countTotal)<br>";
	if ($thisDeposit->totalCash)
		echo "<i><b>".$thisDeposit->totalCash." - Total Cash </b> &nbsp; (Items: $thisDeposit->countCash)</i><br>";
	if ($thisDeposit->totalChecks)
		echo "<i><b>".$thisDeposit->totalChecks." - Total Checks</b> &nbsp; (Items: $thisDeposit->countCheck)</i><br>";
	echo "<br>";
?>

</div>
</div>

<div class="box">
<div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext("Payments on this deposit slip:"); ?></h3>
    <div class="pull-right">
    <?php 
			if ($iDepositSlipID and $thisDeposit->dep_Type and !$thisDeposit->dep_Closed) {
				if ($thisDeposit->dep_Type == "eGive") {
					echo "<input type=button class=btn value=\"".gettext("Import eGive")."\" name=ImporteGive onclick=\"javascript:document.location='eGive.php?DepositSlipID=$iDepositSlipID&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
				} else {
					echo "<input type=button class=\"btn btn-success\" value=\"".gettext("Add Payment")."\" name=AddPayment onclick=\"javascript:document.location='PledgeEditor.php?CurrentDeposit=$iDepositSlipID&PledgeOrPayment=Payment&linkBack=DepositSlipEditor.php?DepositSlipID=$iDepositSlipID&PledgeOrPayment=Payment&CurrentDeposit=$iDepositSlipID';\">";
				} ?>
				<?php if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') { ?>
					<input type="submit" class="btn btn-success" value="<?php echo gettext("Load Authorized Transactions"); ?>" name="DepositSlipLoadAuthorized">
    					<input type="submit" class="btn btn-warning" value="<?php echo gettext("Run Transactions"); ?>" name="DepositSlipRunTransactions">
			    	<?php } ?>
		    <?php } ?>
    </div>
</div>
<div class="box-body">
<table class="table" id="paymentsTable">
</table>



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
    
    $("#DepositSlipEditor").submit(function(e){
        e.preventDefault();
        var formData = {
            'date'              : $('#DepositDate').val(),
            'comment'            : $("#Comment").val(),
            'closed'                  : $('#Closed').is(':checked')
        };
        $("#backupstatus").css("color","orange");
        $("#backupstatus").html("Backup Running, Please wait.");
        console.log(formData);

        //process the form
        $.ajax({
            type        : 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/deposits', // the url where we want to POST
            data        : JSON.stringify(formData), // our data object
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
        })
        .done(function(data) {
            console.log(data);
            var downloadButton = "<button class=\"btn btn-primary\" id=\"downloadbutton\" role=\"button\" onclick=\"javascript:downloadbutton('"+data.filename+"')\"><i class='fa fa-download'></i>  "+data.filename+"</button>";
            $("#backupstatus").css("color","green");
            $("#backupstatus").html("Backup Complete, Ready for Download.");
            $("#resultFiles").html(downloadButton);
        }).fail(function()  {
            $("#backupstatus").css("color","red");
            $("#backupstatus").html("Backup Error.");
        });
        
    });
    
    
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
        data:'fun_Name',
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
    if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {?>,
    ,{
        width: 'auto',
        title:'Cleared',
        data:'plg_aut_Cleared',
    }<?php } 
    if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {  ?>
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
