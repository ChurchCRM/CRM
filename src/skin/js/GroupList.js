$(document).ready(function () {

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
                console.log(data);
                dataT.row.add(data);                                //add the group data to the existing DataTable
                dataT.rows().invalidate().draw(true);               //redraw the dataTable
            });
        }
        else
        {
            
        }
    });

    dataT = $("#groupsTable").DataTable({
        ajax:{
          url :window.CRM.root+"/api/groups/",
          dataSrc:"Groups"
        },
        columns: [
            {
                width: 'auto',
                title: 'Group Name',
                data: 'Name',
                render: function (data, type, full, meta) {
                    return '<a href=\'GroupView.php?GroupID=' + full.Id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a><a href=\'GroupEditor.php?GroupID=' + full.id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>' + data;
                }
            },
            {
                width: 'auto',
                title: 'Members',
                data: 'memberCount',
                searchable: false,
                defaultContent:"0"
            },
            {
                width: 'auto',
                title: 'Group Cart Status',
                searchable: false,
                render: function (data, type, full, meta) {
                    $.ajax({
                      method: "GET",
                      url: window.CRM.root + "/api/groups/"+full.Id+"/cartStatus",
                      dataType: "json"
                    }).done(function (data) {        
                      console.log(data.status);
                      if (data.bAllInCart=="true")
                      {
                          $("#cart-"+full.Id).html("All members of this group are in the cart<a onclick=\"saveScrollCoordinates()\" class=\"btn btn-danger\"  href=\"GroupList.php?RemoveGroupFromPeopleCart=" + full.Id + "\">Remove all</a>");
                      } else
                      {
                          $("#cart-"+full.Id).html("Not all members of this group are in the cart><br><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-primary\" href=\"GroupList.php?AddGroupToPeopleCart=" + full.Id + "\">Add all</a>");
                      }
                    });
                    return '<span id="cart-' + full.Id + '">Checking Cart Status</span>';
                   
                }
            },
            {
                width: 'auto',
                title: 'Group Type',
                data: 'groupType',
                defaultContent:"",
                searchable: true
            }
        ]
    });
});
