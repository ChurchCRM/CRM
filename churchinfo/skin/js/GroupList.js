$(document).ready(function () {

    $("#addNewGroup").click(function (e) {
        var groupName = $("#groupName").val(); // get the name of the group from the textbox
        if (groupName) // ensure that the user entered a group name 
        {
            var newGroup = {'groupName': $("#groupName").val()};    //create a newgroup JSON object, and prepare it for transport
            $.ajax({
                method: "POST",
                url: window.CRM.root + "/api/groups",               //call the groups api handler located at window.CRM.root
                data: JSON.stringify(newGroup)                      // stringify the object we created earlier, and add it to the data payload
            }).done(function (data) {                               //yippie, we got something good back from the server
                dataT.row.add(data);                                //add the group data to the existing DataTable
                dataT.rows().invalidate().draw(true);               //redraw the dataTable
            });
        }
        else
        {
            
        }
    });

    dataT = $("#groupsTable").DataTable({
        data: groupData.groups,
        columns: [
            {
                width: 'auto',
                title: 'Group Name',
                data: 'groupName',
                render: function (data, type, full, meta) {
                    return '<a href=\'GroupView.php?GroupID=' + full.id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-search-plus fa-stack-1x fa-inverse"></i></span></a><a href=\'GroupEditor.php?GroupID=' + full.id + '\'><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-pencil fa-stack-1x fa-inverse"></i></span></a>' + data;
                }
            },
            {
                width: 'auto',
                title: 'Members',
                data: 'memberCount',
                searchable: false
            },
            {
                width: 'auto',
                title: 'Group Cart Status',
                data: 'groupCartStatus',
                searchable: false,
                render: function (data, type, full, meta) {

                    if (data)
                    {
                        return "<span>All members of this group are in the cart</span><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-danger\"  href=\"GroupList.php?RemoveGroupFromPeopleCart=" + full.id + "\">Remove all</a>";
                    } else
                    {
                        return "<span>Not all members of this group are in the cart</span><br><a onclick=\"saveScrollCoordinates()\" class=\"btn btn-primary\" href=\"GroupList.php?AddGroupToPeopleCart=" + full.id + "\">Add all</a>";
                    }
                }
            },
            {
                width: 'auto',
                title: 'Group Type',
                data: 'groupType',
                searchable: true
            }
        ]
    });
});