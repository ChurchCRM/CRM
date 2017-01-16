$(document).ready(function () {

  $("#onlineVerify").click(function () {
    $.ajax({
      type: 'POST',
      url: window.CRM.root + '/api/families/' + familyId + "/verify"
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
      url: window.CRM.root + '/api/families/verify/' + familyId + "/now"
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
    window.open(window.CRM.root + 'Reports/ConfirmReport.php?familyId=' + familyId, '_blank');
    $('#confirm-verify').modal('hide');
  });
});
