$(document).ready(function () {

  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/roles",
    dataType: "json"
  }).done(function (data) {
    window.CRM.groupRoles = data.ListOptions;
    $("#newRoleSelection").select2({
      data: $(window.CRM.groupRoles).map(function () {
        return {
          id: this.OptionId,
          text: i18next.t(this.OptionName)
        };
      })
    });
    initDataTable();
    //echo '<option value="' . $role['lst_OptionID'] . '">' . $role['lst_OptionName'] . '</option>';
  });

  $(".personSearch").select2({
    minimumInputLength: 2,
    language: window.CRM.shortLocale,
    ajax: {
      url: function (params) {
        return window.CRM.root + "/api/persons/search/" + params.term;
      },
      dataType: 'json',
      delay: 250,
      data: function (params) {
        return {
          q: params.term, // search term
          page: params.page
        };
      },
      processResults: function (rdata, page) {
        return {results: rdata};
      },
      cache: true
    }
  });

  $(".personSearch").on("select2:select", function (e) {
    
    window.CRM.groups.addPerson(window.CRM.currentGroup,e.params.data.objid).done(function (data) {
      var person = data.Person2group2roleP2g2rs[0];
      var node = window.CRM.DataTableAPI.row.add(person).node();
      window.CRM.DataTableAPI.rows().invalidate().draw(true);
      $(".personSearch").val(null).trigger('change')
    });
  });

  $("#deleteSelectedRows").click(function () {
    var deletedRows = window.CRM.DataTableAPI.rows('.selected').data()
    bootbox.confirm({
      message: i18next.t("Are you sure you want to remove the selected group members?") + " (" + deletedRows.length + ") ",
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
          $.each(deletedRows, function (index, value) {
            $.ajax({
              type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
              url: window.CRM.root + '/api/groups/' + window.CRM.currentGroup + '/removeuser/' + value.PersonId, // the url where we want to POST
              dataType: 'json', // what type of data do we expect back from the server
              data: {"_METHOD": "DELETE"},
              encode: true
            }).done(function (data) {
              window.CRM.DataTableAPI.row(function (idx, data, node) {
                if (data.PersonId == value.PersonId) {
                  return true;
                }
              }).remove();
              window.CRM.DataTableAPI.rows().invalidate().draw(true);
            });
          });
        }
      }
    });

  });

  $("#addSelectedToCart").click(function () {
    if (window.CRM.DataTableAPI.rows('.selected').length > 0)
    {
      var selectedPersons = $.map(window.CRM.DataTableAPI.rows('.selected').data(), function (val, i) {
        return val.PersonId;
      });

      window.CRM.cart.addPerson(selectedPersons);
    }

  });

  //copy membership
  $("#addSelectedToGroup").click(function () {
    window.CRM.groups.promptSelection(function (selectedRole) {
      var selectedRows = window.CRM.DataTableAPI.rows('.selected').data()
      $.each(selectedRows, function (index, value) {
        window.CRM.groups.addPerson(selectedRole.GroupID, value.PersonId);
      });
    });
  });

  $("#moveSelectedToGroup").click(function () {
    window.CRM.groups.promptSelection(function (selectedRole) {
      var selectedRows = window.CRM.DataTableAPI.rows('.selected').data()
      $.each(selectedRows, function (index, value) {
        window.CRM.groups.addPerson(selectedRole.GroupID, value.PersonId);
        window.CRM.groups.removePerson(window.CRM.currentGroup, value.PersonId, function(data) {
          window.CRM.DataTableAPI.row(function (idx, data, node) {
                if (data.PersonId == value.PersonId) {
                  return true;
                }
              }).remove();
              window.CRM.DataTableAPI.rows().invalidate().draw(true);
        });
      });
    });
  });

  $("#AddGroupMembersToCart").click(function () {
    window.CRM.cart.addGroup($(this).data("groupid"));
  })

  $(document).on("click", ".changeMembership", function (e) {
    var userid = $(e.currentTarget).data("personid");
    $("#changingMemberID").val(window.CRM.DataTableAPI.row(function (idx, data, node) {
      if (data.PersonId == userid) {
        return true;
      }
    }).data().PersonId);
    $("#changingMemberName").text(window.CRM.DataTableAPI.row(function (idx, data, node) {
      if (data.PersonId == userid) {
        return true;
      }
    }).data().firstName);
    $('#changeMembership').modal('show');
    e.stopPropagation();
  });

  $(document).on("click", "#confirmMembershipChange", function (e) {
    var changingMemberID = $("#changingMemberID").val();
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/userRole/" + changingMemberID,
      data: JSON.stringify({'roleID': $("#newRoleSelection option:selected").val()}),
      dataType: "json",
      contentType: "application/json; charset=utf-8",
    }).done(function (data) {
      window.CRM.DataTableAPI.row(function (idx, data, node) {
        if (data.PersonId == changingMemberID) {
          data.RoleId = $("#newRoleSelection option:selected").val();
          return true;
        }
      }).data();
      window.CRM.DataTableAPI.rows().invalidate().draw(true);
      $('#changeMembership').modal('hide');
    });
  });

});

function initDataTable() {
  dataT = $("#membersTable").DataTable({
    "language": {
      "url": window.CRM.plugin.dataTable.language.url
    },
    "dom": 'T<"clear">lfrtip',
    "tableTools": {
      "sSwfPath": window.CRM.plugin.dataTable.tableTools.sSwfPath,
      "aButtons": [
        {
          "sExtends": "csv",
          "bSelectedOnly": true
        }]
    },
    responsive: true,
    ajax: {
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/members",
      dataSrc: "Person2group2roleP2g2rs"
    },
    columns: [
      {
          width: 'auto',
          title: '',
          data: 'PersonId',
          render: function (data, type, full, meta) {
            	return '<img data-name="'+full.Person.FirstName + ' ' + full.Person.LastName + '" data-src="' + window.CRM.root + '/api/persons/' + full.PersonId + '/thumbnail" class="direct-chat-img initials-image">';
          }
      },
      {
        width: 'auto',
        title:i18next.t( 'Name'),
        data: 'PersonId',
        render: function (data, type, full, meta) {
          return '<a target="_top" href="PersonView.php?PersonID=' + full.PersonId + '">' + full.Person.FirstName + " " + full.Person.LastName + '</a>';
        }
      },
      {
        width: 'auto',
        title:i18next.t( 'Group Role'),
        data: 'RoleId',
        render: function (data, type, full, meta) {
          thisRole = $(window.CRM.groupRoles).filter(function (index, item) {
            return item.OptionId == data
          })[0];
          return i18next.t(thisRole.OptionName) + '<button class="changeMembership" data-personid=' + full.PersonId + '><i class="fa fa-pencil"></i></button>';
        }
      },
      {
        width: 'auto',
        title:i18next.t( 'Address'),
        render: function (data, type, full, meta) {
          return full.Person.Address1 + " " + full.Person.Address2;
        }
      },
      {
        width: 'auto',
        title:i18next.t( 'City'),
        data: 'Person.City'
      },
      {
        width: 'auto',
        title:i18next.t( 'State'),
        data: 'Person.State'
      },
      {
        width: 'auto',
        title:i18next.t( 'ZIP'),
        data: 'Person.Zip'
      },
      {
        width: 'auto',
        title:i18next.t( 'Cell Phone'),
        data: 'Person.CellPhone'
      },
      {
        width: 'auto',
        title:i18next.t( 'E-mail'),
        data: 'Person.Email'
      }
    ],
    "fnDrawCallback": function (oSettings) {
      $("#iTotalMembers").text(oSettings.aoData.length);
      $("#membersTable .initials-image").initial();
    },
    "createdRow": function (row, data, index) {
      $(row).addClass("groupRow");
    }
  });

  $('#isGroupActive').change(function () {
    $.ajax({
      type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
      url: window.CRM.root + '/api/groups/' + window.CRM.currentGroup + '/settings/active/' + $(this).prop('checked'),
      dataType: 'json', // what type of data do we expect back from the server
      encode: true
    });
  });

  $('#isGroupEmailExport').change(function () {
    $.ajax({
      type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
      url: window.CRM.root + '/api/groups/' + window.CRM.currentGroup + '/settings/email/export/' + $(this).prop('checked'),
      dataType: 'json', // what type of data do we expect back from the server
      encode: true
    });
  });

  $(document).on('click', '.groupRow', function () {
    var selectedRows = window.CRM.DataTableAPI.rows('.selected').data().length;
    $("#deleteSelectedRows").prop('disabled', !(selectedRows));
    $("#deleteSelectedRows").text(i18next.t("Remove")+" (" + selectedRows + ") "+i18next.t("Members from group"));
    $("#buttonDropdown").prop('disabled', !(selectedRows));
    $("#addSelectedToGroup").prop('disabled', !(selectedRows));
    $("#addSelectedToGroup").html(i18next.t("Add")+"  (" + selectedRows + ") "+i18next.t("Members to another group"));
    $("#addSelectedToCart").prop('disabled', !(selectedRows));
    $("#addSelectedToCart").html(i18next.t("Add")+"  (" + selectedRows + ") "+i18next.t("Members to cart"));
    $("#moveSelectedToGroup").prop('disabled', !(selectedRows));
    $("#moveSelectedToGroup").html(i18next.t("Move")+"  (" + selectedRows + ") "+i18next.t("Members to another group"));
  });

}
