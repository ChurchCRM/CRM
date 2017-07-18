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
          text: this.OptionName
        };
      })
    });
    initDataTable();
    //echo '<option value="' . $role['lst_OptionID'] . '">' . $role['lst_OptionName'] . '</option>';
  });

  $(".personSearch").select2({
    minimumInputLength: 2,
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
        var idKey = 1;
        var results = new Array();
        data = JSON.parse(rdata);
        $.each(data[0].persons, function (index, cvalue) {
          var childObject = {
            id: idKey,
            objid: cvalue.id,
            text: cvalue.displayName,
            uri: cvalue.uri
          };
          idKey++;
          results.push(childObject);
        });
        return {results: results};
      },
      cache: true
    }
  });

  $("#targetGroupSelection").select2({
    ajax: {
      url: window.CRM.root + "/api/groups/",
      dataType: 'json',
      processResults: function (rdata, page) {
        var p = $.map(rdata.Groups, function (item) {
          var o = {
            text: item.Name,
            id: item.Id
          };
          return o;
        });
        return {results: p};
      }
    },
    minimumResultsForSearch: Infinity
  });

  $(".personSearch").on("select2:select", function (e) {
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/adduser/" + e.params.data.objid,
      dataType: "json"
    }).done(function (data) {
      var person = data.Person2group2roleP2g2rs[0];
      var node = dataT.row.add(person).node();
      dataT.rows().invalidate().draw(true);
      $(".personSearch").val(null).trigger('change')
    });
  });

  $("#deleteSelectedRows").click(function () {
    var deletedRows = dataT.rows('.selected').data()
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
              dataT.row(function (idx, data, node) {
                if (data.PersonId == value.PersonId) {
                  return true;
                }
              }).remove();
              dataT.rows().invalidate().draw(true);
            });
          });
        }
       }
    });

  });

  $("#addSelectedToCart").click(function () {
    if (dataT.rows('.selected').length > 0)
    {
      var selectedPersons = {
        "Persons" : $.map(dataT.rows('.selected').data(), function(val,i){
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
    $("#selectTargetGroupModal").modal("show");
    $("#targetGroupAction").val("copy");

  });

  $("#moveSelectedToGroup").click(function () {
    $("#selectTargetGroupModal").modal("show");
    $("#targetGroupAction").val("move");

  });


  $("#confirmTargetGroup").click(function () {
    var selectedRows = dataT.rows('.selected').data()
    var targetGroupId = $("#targetGroupSelection option:selected").val()
    var action = $("#targetGroupAction").val();

    $.each(selectedRows, function (index, value) {
      $.ajax({
        type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
        url: window.CRM.root + '/api/groups/' + targetGroupId + '/adduser/' + value.PersonId,
        dataType: 'json', // what type of data do we expect back from the server
        encode: true
      });
      if (action == "move") {
        $.ajax({
          type: 'POST', // define the type of HTTP verb we want to use (POST for our form)
          url: window.CRM.root + '/api/groups/' + window.CRM.currentGroup + '/removeuser/' + value.PersonId,
          dataType: 'json', // what type of data do we expect back from the server
          encode: true,
          data: {"_METHOD": "DELETE"},
        }).done(function (data) {
          dataT.row(function (idx, data, node) {
            if (data.PersonId == value.PersonId) {
              return true;
            }
          }).remove();
          dataT.rows().invalidate().draw(true);
        });
      }
    });
    $(document).ajaxStop(function () {
      $("#selectTargetGroupModal").modal("hide");
    });
  });


  $(document).on("click", ".changeMembership", function (e) {
    var userid = $(e.currentTarget).data("personid");
    $("#changingMemberID").val(dataT.row(function (idx, data, node) {
      if (data.PersonId == userid) {
        return true;
      }
    }).data().PersonId);
    $("#changingMemberName").text(dataT.row(function (idx, data, node) {
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
      dataT.row(function (idx, data, node) {
        if (data.PersonId == changingMemberID) {
          data.RoleId = $("#newRoleSelection option:selected").val();
          return true;
        }
      }).data();
      dataT.rows().invalidate().draw(true);
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
          return thisRole.OptionName + '<button class="changeMembership" data-personid=' + full.PersonId + '><i class="fa fa-pencil"></i></button>';
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
    var selectedRows = dataT.rows('.selected').data().length;
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
