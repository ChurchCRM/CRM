$(document).ready(function () {

    var options = {
        "language": {
            "url": window.CRM.root + "/skin/locale/datatables/" + window.CRM.locale + ".json"
        }
    };
    $("#pledge-payment-table").DataTable(options);


  $("#onlineVerify").click(function () {
    $.ajax({
      type: 'POST',
      url: window.CRM.root + '/api/families/' + window.CRM.currentFamily + '/verify'
    })
      .done(function(data, textStatus, xhr) {
        $('#confirm-verify').modal('hide');
        if (xhr.status == 200) {
          showGlobalMessage("Verification email sent", "success")
        } else {
          showGlobalMessage("Failed to send verification email ", "danger")
        }
      });
  });

  $("#verifyNow").click(function () {
    $.ajax({
      type: 'POST',
      url: window.CRM.root + '/api/families/verify/' + window.CRM.currentFamily + '/now'
    })
      .done(function(data, textStatus, xhr) {
        $('#confirm-verify').modal('hide');
        if (xhr.status == 200) {
          location.reload();
        } else {
          showGlobalMessage("Failed to add verification", "danger")
        }
      });
  });


  $("#verifyDownloadPDF").click(function () {
    window.open(window.CRM.root + 'Reports/ConfirmReport.php?familyId=' + window.CRM.currentFamily, '_blank');
    $('#confirm-verify').modal('hide');
  });
});
