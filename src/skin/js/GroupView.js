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
      window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Role,GroupID:window.CRM.currentGroup},function(selection){
        window.CRM.groups.addPerson(window.CRM.currentGroup, e.params.data.objid,selection.RoleID).done(function (data) {
          var person = data.Person2group2roleP2g2rs[0];
          var node = window.CRM.DataTableAPI.row.add(person).node();
          window.CRM.DataTableAPI.rows().invalidate().draw(true);
          $(".personSearch").val(null).trigger('change');
          dataT.ajax.reload();
        });
      })
      
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
            window.CRM.groups.removePerson(window.CRM.currentGroup,value.PersonId).done(
              function(){
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
      var selectedPersons = {
        "Persons" : $.map(window.CRM.DataTableAPI.rows('.selected').data(), function(val,i){
                      return val.PersonId;
                    })
      };
      $.ajax({
        type: 'POST',
        url: window.CRM.root + '/api/cart/',
        dataType: 'json',
        contentType: "application/json",
        data: JSON.stringify(selectedPersons)
      }).done(function(data) {
          if ( data.status == "success" )
          {
            location.reload();
          }
      });
    }

  });

  //copy membership
  $("#addSelectedToGroup").click(function () {
    window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Group|window.CRM.groups.selectTypes.Role}, function(data){
      selectedRows = window.CRM.DataTableAPI.rows('.selected').data()
      $.each(selectedRows, function (index, value) {
        window.CRM.groups.addPerson(data.GroupID,value.PersonId,data.RoleID);
    });
    });
  });

  $("#moveSelectedToGroup").click(function () {
    window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Group|window.CRM.groups.selectTypes.Role},function(data){
      selectedRows = window.CRM.DataTableAPI.rows('.selected').data()
      $.each(selectedRows, function (index, value) {
        console.log(data);
        window.CRM.groups.addPerson(data.GroupID,value.PersonId,data.RoleID);
        window.CRM.groups.removePerson(window.CRM.currentGroup,value.PersonId).done(
          function () {
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


  $(document).on("click", ".changeMembership", function (e) {
    var PersonID = $(e.currentTarget).data("personid");
    window.CRM.groups.promptSelection({Type:window.CRM.groups.selectTypes.Role,GroupID:window.CRM.currentGroup},function(selection){
      window.CRM.groups.addPerson(window.CRM.currentGroup,PersonID,selection.RoleID).done(function(){
        window.CRM.DataTableAPI.row(function (idx, data, node) {
        if (data.PersonId == PersonID) {
          data.RoleId = selection.RoleID;
          return true;
        }
      });
      window.CRM.DataTableAPI.rows().invalidate().draw(true);
      });
    });
    e.stopPropagation();
  });

});

function initDataTable() {
  var DataTableOpts = {
    ajax: {
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/members",
      dataSrc: "Person2group2roleP2g2rs"
    },
    columns: [
      {
        width: 'auto',
        title: 'Name',
        data: 'PersonId',
        render: function (data, type, full, meta) {
          return '<img data-name="'+full.Person.FirstName + ' ' + full.Person.LastName + '" data-src="' + window.CRM.root + '/api/persons/' + full.PersonId + '/thumbnail" class="direct-chat-img initials-image"> &nbsp <a href="PersonView.php?PersonID="' + full.PersonId + '"><a target="_top" href="PersonView.php?PersonID=' + full.PersonId + '">' + full.Person.FirstName + " " + full.Person.LastName + '</a>';
        }
      },
      {
        width: 'auto',
        title: 'Group Role',
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
        title: 'Address',
        render: function (data, type, full, meta) {
          return full.Person.Address1 + " " + full.Person.Address2;
        }
      },
      {
        width: 'auto',
        title: 'City',
        data: 'Person.City'
      },
      {
        width: 'auto',
        title: 'State',
        data: 'Person.State'
      },
      {
        width: 'auto',
        title: 'ZIP',
        data: 'Person.Zip'
      },
      {
        width: 'auto',
        title: 'Cell Phone',
        data: 'Person.CellPhone'
      },
      {
        width: 'auto',
        title: 'E-mail',
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
  };
  $.extend(DataTableOpts,window.CRM.plugin.DataTable);
  window.CRM.DataTableAPI = $("#membersTable").DataTable(DataTableOpts);

    $('#isGroupActive').change(function() {
        $.ajax({
            type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url: window.CRM.root + '/api/groups/' + window.CRM.currentGroup + '/settings/active/' + $(this).prop('checked'),
            dataType: 'json', // what type of data do we expect back from the server
            encode: true
        });
    });

    $('#isGroupEmailExport').change(function() {
        $.ajax({
            type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
            url: window.CRM.root + '/api/groups/' + window.CRM.currentGroup + '/settings/email/export/' + $(this).prop('checked'),
            dataType: 'json', // what type of data do we expect back from the server
            encode: true
        });
    });

  $(document).on('click', '.groupRow', function () {
    $(this).toggleClass('selected');
    var selectedRows = window.CRM.DataTableAPI.rows('.selected').data().length;
    $("#deleteSelectedRows").prop('disabled', !(selectedRows));
    $("#deleteSelectedRows").text("Remove (" + selectedRows + ") Members from group");
    $("#buttonDropdown").prop('disabled', !(selectedRows));
    $("#addSelectedToGroup").prop('disabled', !(selectedRows));
    $("#addSelectedToGroup").html("Add  (" + selectedRows + ") Members to another group");
    $("#addSelectedToCart").prop('disabled', !(selectedRows));
    $("#addSelectedToCart").html("Add  (" + selectedRows + ") Members to cart");
    $("#moveSelectedToGroup").prop('disabled', !(selectedRows));
    $("#moveSelectedToGroup").html("Move  (" + selectedRows + ") Members to another group");
  });

}
