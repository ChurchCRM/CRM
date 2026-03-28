function UpdateRoles() {
  var group_ID = $("#GroupID option:selected").val(); // get the selected group ID
  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/" + group_ID + "/roles",
    dataType: "json",
  }).done(function (data) {
    // i18next-disable-next-line
    var html = '<option value="">' + i18next.t("All Roles") + "</option>";
    $.each(data, function (index, value) {
      html += '<option value="' + value.OptionId + '"';
      // i18next-disable-next-line
      html += ">" + i18next.t(value.OptionName) + "</option>";
    });
    $("#GroupRole").html(html);
  });
}

$(document).ready(function (e, confirmed) {
  $("#addToGroup").click(function () {
    window.CRM.groups.addGroup(function (data) {
      location.href = "CartToGroup.php?groupeCreationID=" + data.Id;
    });
  });
});
