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
<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/jquery.dataTables.min.css">
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/dataTables.bootstrap.js"></script>


<link rel="stylesheet" type="text/css" href="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/css/dataTables.tableTools.css">
<script type="text/javascript" language="javascript" src="<?= $sURLPath; ?>/vendor/almasaeed2010/adminlte/plugins/datatables/extensions/TableTools/js/dataTables.tableTools.min.js"></script>

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

<button type="button" id="deleteSelectedRows"  class="btn btn-danger" disabled>Delete Selected Rows</button>
<button type="button" id="exportSelectedRows"  class="btn btn-success" disabled><i class="fa fa-download"></i>Export Selected Rows (OFX)</button>
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
    
    $("#depositDate").datepicker({format:'yyyy-mm-dd'}).datepicker("setDate", new Date());
   
    $("#addNewDeposit").click(function (e){
        var newDeposit = {
            'depositType':$("#depositType option:selected").val(),
            'depositComment':$("#depositComment").val(),
            'depositDate':$("#depositDate").val()
        };
        console.log(newDeposit);
        $.ajax({
            method: "POST",
            url:   "/api/deposits",
            data:  JSON.stringify(newDeposit)
        }).done(function(data){
            console.log(data[0]);
            dataT.row.add(data[0]);
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
            if (type === 'display')
            {
                return '<a href=\'DepositSlipEditor.php?DepositSlipID='+full.dep_ID+'\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a>'+full.dep_ID; 
            }
            else
            {
                return parseInt(full.dep_ID);
            }
        },
        type:'num'
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
        title:'Closed',
        data:'dep_Closed',
        searchable: true,
        render: function (data,type,full,meta) {
            if (data == 1)
            {
                return "Yes";
            }
            else
            {
                return "No";
            }
        }
    },
    {
        width: 'auto',
        title:'Deposit Type',
        data:'dep_Type',
        searchable: true
    }
    ],
    order:[0,'desc']
    });
    


     $("#depositsTable tbody").on('click', 'tr', function() {
         console.log("clicked");
         $(this).toggleClass('selected');
         var selectedRows = dataT.rows('.selected').data().length;
          $("#deleteSelectedRows").prop('disabled', !(selectedRows));
          $("#deleteSelectedRows").text("Delete ("+selectedRows+") Selected Rows");
          $("#exportSelectedRows").prop('disabled', !(selectedRows));
          $("#exportSelectedRows").html("<i class=\"fa fa-download\"></i>Export ("+selectedRows+") Selected Rows (OFX)");
        
     });
     
     $('#deleteSelectedRows').click(function() {
        var deletedRows = dataT.rows('.selected').data()
        console.log("delete-button" + deletedRows.length);
        $("#deleteNumber").text(deletedRows.length);
        $("#confirmDelete").modal('show');
    });
    
    
    $('#exportSelectedRows').click(function() {
        var selectedRows = dataT.rows('.selected').data()
        $.each(selectedRows, function(index, value){
            window.open('/api/deposits/'+value.dep_ID+'/ofx');
        });
    });
    
    
    $("#deleteConfirmed").click(function() {
	 var deletedRows = dataT.rows('.selected').data()
        $.each(deletedRows, function(index, value){
            console.log(value);
             $.ajax({
            type        : 'DELETE', // define the type of HTTP verb we want to use (POST for our form)
            url         : '/api/deposits/'+value.dep_ID, // the url where we want to POST
            dataType    : 'json', // what type of data do we expect back from the server
            encode      : true
            })
             .done(function(data) {
                console.log(data);
                $('#confirmDelete').modal('hide');
                dataT.rows('.selected').remove().draw(false);
            });
        });
       
		
    });

});
</script>

<?php
require "Include/Footer.php";
?>
