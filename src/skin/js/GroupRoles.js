function UpdateRoles()
{
  var group_ID = $('#GroupID option:selected').val();  // get the selected group ID
  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/" + group_ID+ "/roles",
    dataType: "json"
  }).done(function (data) {
    var html = "";
    $.each(data.ListOptions, function (index, value) {
      html += "<option value=\"" + value.OptionId + "\"";
      html += ">" + i18next.t(value.OptionName) + "</option>";
    });
    $("#GroupRole").html(html);
  });
}

$(document).ready(function (e, confirmed) {
  $("#addToGroup").click(function () {
    bootbox.prompt({
      title: i18next.t("Add A Group Name"),
      value: i18next.t("Default Name Group"),
      onEscape: true,
      closeButton: true,
      buttons: {
        confirm: {
          label:  i18next.t('Yes'),
            className: 'btn-success'
        },
        cancel: {
          label:  i18next.t('No'),
          className: 'btn-danger'
        }
      },
      callback: function (result)
      {
      	if (result)
      	{
	      	var newGroup = {'groupName': result};
	      	
			$.ajax({
				method: "POST",
				url: window.CRM.root + "/api/groups/",               //call the groups api handler located at window.CRM.root
			    data: JSON.stringify(newGroup),                      // stringify the object we created earlier, and add it to the data payload
				contentType: "application/json; charset=utf-8",
				dataType: "json"
			}).done(function (data) {                               //yippie, we got something good back from the server
				location.href = 'CartToGroup.php?groupeCreationID='+data.Id;
			});
		}
      }
    });
  });
});
	
