$(document).ready(function () {
  window.CRM.groupsInCart = 0;
  $.ajax({
    method: "GET",
    url: window.CRM.root + "/api/groups/groupsInCart",
    dataType: "json"
  }).done(function (data) {
    window.CRM.groupsInCart = data.groupsInCart;
  });

  $("#addNewGroup").click(function (e) {
    var groupName = $("#groupName").val(); // get the name of the group from the textbox
    if (groupName) // ensure that the user entered a group name
    {
      var newGroup = {'groupName': groupName};    //create a newgroup JSON object, and prepare it for transport
      $.ajax({
        method: "POST",
        url: window.CRM.root + "/api/groups/",               //call the groups api handler located at window.CRM.root
        data: JSON.stringify(newGroup),                      // stringify the object we created earlier, and add it to the data payload
        contentType: "application/json; charset=utf-8",
        dataType: "json"
      }).done(function (data) {                               //yippie, we got something good back from the server
        dataT.row.add(data);                                //add the group data to the existing DataTable
        dataT.rows().invalidate().draw(true);               //redraw the dataTable
        $("#groupName").val(null);
      });
    }
    else {

    }
  });

  dataT = $("#groupsTable").DataTable({
    "language": {
      "url": window.CRM.root + "/skin/locale/datatables/" + window.CRM.locale + ".json"
    },
    responsive: true,
    ajax: {
      url: window.CRM.root + "/api/groups/",
      dataSrc: "Groups"
    },
    columns: [
      {
        width: 'auto',
        title: 'Group Name',
        data: 'Name',
        render: function (data, type, full, meta) {
          return '<a href=\'GroupView.php?GroupID=' + full.Id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a><a href=\'GroupEditor.php?GroupID=' + full.Id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>' + data;
        }
      },
      {
        width: 'auto',
        title: 'Members',
        data: 'memberCount',
        searchable: false,
        defaultContent: "0"
      },
      {
        width: 'auto',
        title: 'Group Cart Status',
        searchable: false,
        render: function (data, type, full, meta) {
          return '<span class="cartStatusButton" data-groupid="' + full.Id + '">Checking Cart Status</span>';

        }
      },
      {
        width: 'auto',
        title: 'Group Type',
        data: 'groupType',
        defaultContent: "",
        searchable: true
      }
    ]
  }).on('draw.dt', function () {
    $(".cartStatusButton").each(function (index, element) {
      var objectID = $(element).data("groupid");
      if ($.inArray(objectID, window.CRM.groupsInCart) > -1) {
        $(element).html("All members of this group are in the cart<a onclick=\"saveScrollCoordinates()\" class=\"btn btn-danger\"  href=\"GroupList.php?RemoveGroupFromPeopleCart=" + objectID + "\">Remove all</a>");
      }
      else {
        $(element).html("Not all members of this group are in the cart<br><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-primary\" href=\"GroupList.php?AddGroupToPeopleCart=" + objectID + "\">Add all</a>");
      }
    });
  });
});
