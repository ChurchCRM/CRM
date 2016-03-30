function UpdateRoles()
{
  var group_ID = $('#GroupID option:selected').val();  // get the selected group ID
  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/" + group_ID,
  }).done(function (data) {
    var defaultRole = data.groups.grp_DefaultRole;
    var html = "";
    $.each(data.groups.roles, function (index, value) {
      html += "<option value=\"" + value.lst_OptionID + "\"";
      if (value.lst_OptionID === defaultRole) {
        html += " selected";
      }
      html += ">" + value.lst_OptionName + "</option>";
    });
    $("#GroupRole").html(html);
  });
}
