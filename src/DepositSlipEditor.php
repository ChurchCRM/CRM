<?php
/* * *****************************************************************************
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
 * **************************************************************************** */

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "service/FinancialService.php";

$financialService = new FinancialService();
$linkBack = "";
$iDepositSlipID = 0;
$sDateError = "";
$thisDeposit = "";

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

<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?php echo gettext("Deposit Details: "); ?></h3>
  </div>
  <div class="box-body">
    <form method="post" action="#" name="DepositSlipEditor" id="DepositSlipEditor">
      <div class="container">
        <div class="row">
          <div class="col-md-3">
            <label for="Date"><?php echo gettext("Date:"); ?></label>
            <input type="text" name="Date" value="<?php echo $thisDeposit->dep_Date; ?>" id="DepositDate" >
          </div>
          <div class="col-md-4">
            <label for="Comment"><?php echo gettext("Comment:"); ?></label>
            <input type="text" name="Comment" id="Comment" value="<?php echo $thisDeposit->dep_Comment; ?>" style="width:100%;">
          </div>
          <div class="col-md-2">
            <label for="Closed"><?php echo gettext("Closed:"); ?></label>
            <input type="checkbox" name="Closed" id="Closed" value="1" <?php if ($thisDeposit->dep_Closed) echo " checked"; ?>><?php echo gettext("Close deposit slip (remember to press Save)"); ?>
          </div>
        </div>
        <div class="row">
          <div class="col-md-3">
            <input type="submit" class="btn" value="<?php echo gettext("Save"); ?>" name="DepositSlipSubmit">
          </div>
          <div class="col-md-3">
            <input type="button" class="btn" value="<?php echo gettext("Deposit Slip Report"); ?>" name="DepositSlipGeneratePDF" onclick="javascript:document.location = 'Reports/PrintDeposit.php?BankSlip=<?php echo ($thisDeposit->dep_Type == 'Bank') ?>';">
          </div>
        </div>

        <?php
        if ($thisDeposit->dep_Type == 'BankDraft' || $thisDeposit->dep_Type == 'CreditCard') {
          echo "<p>" . gettext("Important note: failed transactions will be deleted permanantly when the deposit slip is closed.") . "</p>";
        }
        ?>

        <div class="row">
          <div class="col-md-3">
            <?php
            // Get deposit totals
            echo "<b>" . $thisDeposit->dep_Total . " - TOTAL AMOUNT </b> &nbsp; (Items: $thisDeposit->countTotal)<br>";
            ?>
          </div>

          <div class="col-md-3">
            <?php
            if ($thisDeposit->totalCash)
              echo "<i><b>" . $thisDeposit->totalCash . " - Total Cash </b> &nbsp; (Items: $thisDeposit->countCash)</i><br>";
            ?>
          </div>
          <div class="col-md-3">
            <?php
            if ($thisDeposit->totalChecks)
              echo "<i><b>" . $thisDeposit->totalChecks . " - Total Checks</b> &nbsp; (Items: $thisDeposit->countCheck)</i><br>";
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
    <button type="button" id="deleteSelectedRows"  class="btn btn-danger" disabled>Delete Selected Rows</button>
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
              console.log(paymentData);
              $("#DepositDate").datepicker({format: 'yyyy-mm-dd'});

              function format(d)
              {
                // `d` is the original data object for the row
                return '<table cellpadding="5" cellspacing="0" border="0" style="padding-left:50px;">' +
                        '<tr>' +
                        '<td>Date:</td>' +
                        '<td>' + d.plg_date + '</td>' +
                        '</tr>' +
                        '<tr>' +
                        '<td>Fiscal Year:</td>' +
                        '<td>' + d.FiscalYear + '</td>' +
                        '</tr>' +
                        '<tr>' +
                        '<td>Fund(s):</td>' +
                        '<td>' + d.fun_Name + '</td>' +
                        '</tr>' +
                        '<tr>' +
                        '<td>Non Deductible:</td>' +
                        '<td>' + d.plg_NonDeductible + '</td>' +
                        '</tr>' +
                        '<tr>' +
                        '<td>Comment:</td>' +
                        '<td>' + d.plg_comment + '</td>' +
                        '</tr>' +
                        '</table>';
              }

              $(document).ready(function()
              {

                $("#DepositSlipEditor").submit(function(e)
                {
                  e.preventDefault();
                  var formData = {
                    'depositDate': $('#DepositDate').val(),
                    'depositComment': $("#Comment").val(),
                    'depositClosed': $('#Closed').is(':checked'),
                    'depositType': '<?php echo $thisDeposit->dep_Type; ?>'

                  };
                  $("#backupstatus").css("color", "orange");
                  $("#backupstatus").html("Backup Running, Please wait.");
                  console.log(formData);

                  //process the form
                  $.ajax({
                    type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
                    url: '/api/deposits/<?php echo $iDepositSlipID; ?>', // the url where we want to POST
                    data: JSON.stringify(formData), // our data object
                    dataType: 'json', // what type of data do we expect back from the server
                    encode: true
                  })
                          .done(function(data)
                          {
                            console.log(data);
                            var downloadButton = "<button class=\"btn btn-primary\" id=\"downloadbutton\" role=\"button\" onclick=\"javascript:downloadbutton('" + data.filename + "')\"><i class='fa fa-download'></i>  " + data.filename + "</button>";
                            $("#backupstatus").css("color", "green");
                            $("#backupstatus").html("Backup Complete, Ready for Download.");
                            $("#resultFiles").html(downloadButton);
                          }).fail(function()
                  {
                    $("#backupstatus").css("color", "red");
                    $("#backupstatus").html("Backup Error.");
                  });

                });

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
                                render: function(data, type, full, meta)
                                {
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

              $('#paymentsTable tbody').on('click', 'td.details-control', function()
              {
                var tr = $(this).closest('tr');
                var row = dataT.row(tr);

                if(row.child.isShown())
                {
                  // This row is already open - close it
                  row.child.hide();
                  tr.removeClass('shown');
                  tr.innerHTML('<i class="fa fa-plus-circle"></i>');
                }
                else
                {
                  // Open this row
                  row.child(format(row.data())).show();
                  tr.addClass('shown');
                  tr.innerHTML('<i class="fa fa-minus-circle"></i>');
                }
              });

              $("#paymentsTable tbody").on('click', 'tr', function()
              {
                console.log("clicked");
                $(this).toggleClass('selected');
                var selectedRows = dataT.rows('.selected').data().length;
                $("#deleteSelectedRows").prop('disabled', !(selectedRows));
                $("#deleteSelectedRows").text("Delete (" + selectedRows + ") Selected Rows");

              });

              $('#deleteSelectedRows').click(function()
              {
                var deletedRows = dataT.rows('.selected').data()
                console.log(deletedRows);
                console.log("delete-button" + deletedRows.length);
                $("#deleteNumber").text(deletedRows.length);
                $("#confirmDelete").modal('show');
              });

              $("#deleteConfirmed").click(function()
              {
                var deletedRows = dataT.rows('.selected').data()
                $.each(deletedRows, function(index, value)
                {
                  console.log(value);
                  $.ajax({
                    type: 'DELETE', // define the type of HTTP verb we want to use (POST for our form)
                    url: '/api/payments/' + value.plg_GroupKey, // the url where we want to POST
                    dataType: 'json', // what type of data do we expect back from the server
                    encode: true
                  })
                          .done(function(data)
                          {
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
