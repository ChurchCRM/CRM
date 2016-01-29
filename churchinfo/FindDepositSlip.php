<?php
/*******************************************************************************
 *
 *  filename    : FindDepositSlip.php
 *  last change : 2005-02-06
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001-2005 Deane Barker, Chris Gebhardt, Michael Wilt, Tim Dearborn
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
require 'service/FinancialService.php';

$iDepositSlipID = $_SESSION['iCurrentDeposit'];

//Set the page title
$sPageTitle = gettext("Deposit Listing");

// Security: User must have finance permission to use this form
//if (!$_SESSION['bFinance'])
//{
//	Redirect("Menu.php");
//	exit;
//}

//Filter Values

// Build SQL Criteria
$sCriteria = "";
if (!$_SESSION['bFinance'])
	$sCriteria = "WHERE dep_EnteredBy=" . $_SESSION['iUserID'];

$financialService=new FinancialService();
require "Include/Header.php";
?>

<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.css">
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>



<div class="box">
<div class="box-body">
<table class="table" id="depositsTable">
</table>
<
</div>
</div>
<script>
var depositData = <?php $json = $financialService->getDepositJSON($financialService->getDeposits()); if ($json) { echo $json; } else { echo 0; } ?>;

if (!$.isArray(depositData.deposits))
{
    depositData.deposits=[depositData.deposits];
}
console.log(depositData.deposits);
var dataT = 0;
$(document).ready(function() {
   
    $("#addNewGroup").click(function (e){
        var newGroup = {'groupName':$("#groupName").val()};
        console.log(newGroup);
        $.ajax({
            method: "POST",
            url:   "/api/groups",
            data:  JSON.stringify(newGroup)
        }).done(function(data){
            console.log(data);
            dataT.row.add(data);
            dataT.rows().invalidate().draw(true);
        });
    });
   
    dataT = $("#depositsTable").DataTable({
    data:depositData.deposits,
    columns: [
    {
        width: 'auto',
        title:'Deposit ID',
        data:'dep_ID',
        render: function  (data, type, full, meta ) {
            return '<a href=\'DepositSlipEditor.php?DepositSlipID='+full.dep_ID+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a><a href=\'GroupEditor.php?GroupID='+full.id+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>'+data; 
        }
    },
    {
        width: 'auto',
        title:'Deposit Date',
        data:'dep_Date',
        searchable: true
    },
    {
        width: 'auto',
        title:'Deposit Total',
        data:'dep_Total',
        searchable: false,
    },
    {
        width: 'auto',
        title:'Deposit Comment',
        data:'dep_Comment',
        searchable: true
    },
    {
        width: 'auto',
        title:'Export Deposit as OFX',
        data:'dep_ID',
        searchable: true,
        render: function  (data, type, full, meta ) {
            return '<a href=\'Reports/ExportOFX.php?deposit='+full.dep_ID+'\'><i class="fa fa-download"></i></a>'; 
        }
    }
    ]
});
});
</script>

<?php
require "Include/Footer.php";
?>
