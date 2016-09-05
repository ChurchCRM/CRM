$(document).ready(function() {
  
  $.ajax({
    method:"GET",
    url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/roles",
    dataType: "json"
  }).done(function(data){
    window.CRM.groupRoles = data.ListOptions;
    $("#newRoleSelection").select2({
      data:$(window.CRM.groupRoles).map(function(){
            return {
              id:this.OptionId,
              text:this.OptionName
            };
          })
    });
    initDataTable();
    //echo '<option value="' . $role['lst_OptionID'] . '">' . $role['lst_OptionName'] . '</option>';
  });
  
  $(".personSearch").select2({
    minimumInputLength: 2,
    ajax: {
      url: function(params) {
        return window.CRM.root + "/api/persons/search/" + params.term;
      },
      dataType: 'json',
      delay: 250,
      data: function(params) {
        return {
          q: params.term, // search term
          page: params.page
        };
      },
      processResults: function(rdata, page) {
        var idKey = 1;
        var results = new Array();
        data = JSON.parse(rdata);
        $.each(data[0].persons, function(index, cvalue) {
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

  $(".personSearch").on("select2:select", function(e) {
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/adduser/" + e.params.data.objid,
      dataType: "json"
    }).done(function(data) {
      var person = data.Person2group2roleP2g2rs[0];
      var node = dataT.row.add(person).node();
      dataT.rows().invalidate().draw(true);
    });
    $(".personSearch").select2("val", "");
  });

  $("#deleteGroupButton").on("click", function(e) {
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup,
      dataType: "json",
      encode: true,
      data: {"_METHOD": "DELETE"}
    }).done(function(data) {
      if(data.success)
        window.location.href = "GroupList.php";
    });
  });

  $(document).on("click", ".removeUserGroup", function(e) {
    var userid = $(e.currentTarget).data("personid");
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/removeuser/" + userid,
      dataType: "json"
    }).done(function(data) {
      dataT.row(function(idx, data, node) {
        if(data.personid == userid) {
          return true;
        }
      }).remove();
      dataT.rows().invalidate().draw(true);
    });
  });

  $(document).on("click", ".changeMembership", function(e) {
    var userid = $(e.currentTarget).data("personid");
    $("#changingMemberID").val(dataT.row(function(idx, data, node) {
      if(data.PersonId == userid) {
        return true;
      }
    }).data().PersonId);
    $("#changingMemberName").text(dataT.row(function(idx, data, node) {
      if(data.PersonId == userid) {
        return true;
      }
    }).data().firstName);
    $('#changeMembership').modal('show');

  });

  $(document).on("click", "#confirmMembershipChange", function(e) {
    var changingMemberID = $("#changingMemberID").val();
    $.ajax({
      method: "POST",
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/userRole/" + changingMemberID,
      data: JSON.stringify({'roleID': $("#newRoleSelection option:selected").val()}),
      dataType: "json",
      contentType: "application/json; charset=utf-8",
    }).done(function(data) {
      dataT.row(function(idx, data, node) {
        if(data.PersonId == changingMemberID) {
          data.RoleId = $("#newRoleSelection option:selected").val();
          return true;
        }
      }).data();
      dataT.rows().invalidate().draw(true);
      $('#changeMembership').modal('hide');
    });
  });

});

function initDataTable()
{
  dataT = $("#membersTable").DataTable({
    ajax: {
      url: window.CRM.root + "/api/groups/" + window.CRM.currentGroup + "/members",
      dataSrc: "Person2group2roleP2g2rs"
    },
    columns: [
      {
        width: 'auto',
        title: 'Name',
        data: 'PersonId',
        render: function(data, type, full, meta) {
          return '<img src="' + full.photo + '" class="direct-chat-img"> &nbsp <a href="PersonView.php?PersonID="' + full.PersonId + '"><a target="_top" href="PersonView.php?PersonID=' + full.PersonId + '">' + full.Person.FirstName + " " + full.Person.LastName + '</a>';
        }
      },
      {
        width: 'auto',
        title: 'Group Role',
        data: 'RoleId',
        render: function(data, type, full, meta) {
          thisRole =  $(window.CRM.groupRoles).filter(function(index,item){return item.OptionId == data })[0];
          return thisRole.OptionName + '<button class="changeMembership" data-personid=' + full.PersonId + '><i class="fa fa-pencil"></i></button>';
        }
      },
      {
        width: 'auto',
        title: 'Address',
        render: function(data, type, full, meta) {
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
    ]
  });

  $("#membersTable tbody").on('click', 'tr', function() {
    $(this).toggleClass('selected');
    var selectedRows = dataT.rows('.selected').data().length;
    $("#deleteSelectedRows").prop('disabled', !(selectedRows));
    $("#deleteSelectedRows").text("Remove (" + selectedRows + ") Members from group");
    $("#exportSelectedRowsCSV").prop('disabled', !(selectedRows));
    $("#exportSelectedRowsCSV").html("<i class=\"fa fa-download\"></i> Export (" + selectedRows + ") Selected Rows (CSV)");
    $("#addSelectedToGroup").prop('disabled', !(selectedRows));
    $("#addSelectedToGroup").html("Add  (" + selectedRows + ") Members to another group");
    $("#addSelectedToCart").prop('disabled', !(selectedRows));
    $("#addSelectedToCart").html("Add  (" + selectedRows + ") Members to cart");
  });


  
}

function initHandlers() {
  $("#chkClear").click(function(e) {
    $("#deleteGroupButton").prop("disabled", !e.target.checked);
  });








}
