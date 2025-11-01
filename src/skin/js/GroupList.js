$(document).ready(function () {
    window.CRM.groupsInCart = 0;
    $.ajax({
        method: "GET",
        url: window.CRM.root + "/api/groups/groupsInCart",
        dataType: "json",
    }).done(function (data) {
        window.CRM.groupsInCart = data.groupsInCart;
    });

    $("#addNewGroup").click(function (e) {
        var groupName = $("#groupName").val(); // get the name of the group from the textbox
        if (groupName) {
            // ensure that the user entered a group name
            var newGroup = { groupName: groupName }; //create a newgroup JSON object, and prepare it for transport
            $.ajax({
                method: "POST",
                url: window.CRM.root + "/api/groups/", //call the groups api handler located at window.CRM.root
                data: JSON.stringify(newGroup), // stringify the object we created earlier, and add it to the data payload
                contentType: "application/json; charset=utf-8",
                dataType: "json",
            }).done(function (data) {
                //yippie, we got something good back from the server
                dataT.row.add(data); //add the group data to the existing DataTable
                dataT.rows().invalidate().draw(true); //redraw the dataTable
                $("#groupName").val(null);
                dataT.ajax.reload(); // PL : We should reload the table after we add a group so the button add to group is disabled
            });
        }
    });

    var dataTableConfig = {
        ajax: {
            url: window.CRM.root + "/api/groups/",
            type: "GET",
            dataSrc: "",
        },
        columns: [
            {
                width: "auto",
                title: i18next.t("Group Name"),
                data: "Name",
                render: function (data, type, full, meta) {
                    const container = document.createElement("div");

                    // clickable icon for viewing
                    const link1 = document.createElement("a");
                    link1.href = "GroupView.php?GroupID=" + full.Id;

                    const icon1 = document.createElement("i");
                    icon1.className = "fa fa-eye";

                    link1.appendChild(icon1);

                    // clickable icon for editing
                    const link2 = document.createElement("a");
                    link2.href = "GroupEditor.php?GroupID=" + full.Id;

                    const icon2 = document.createElement("i");
                    icon2.className = "fa fa-pen";

                    link2.appendChild(icon2);

                    // add it all to the encapsulating element
                    container.appendChild(link1);
                    container.appendChild(document.createTextNode(" "));
                    container.appendChild(link2);
                    container.appendChild(document.createTextNode(" "));

                    const dataText = document.createTextNode(data);
                    container.appendChild(dataText);

                    return container.outerHTML;
                },
            },
            {
                width: "auto",
                title: i18next.t("Members"),
                data: "memberCount",
                searchable: false,
                defaultContent: "0",
            },
            {
                width: "auto",
                title: i18next.t("Group Cart Status"),
                searchable: false,
                render: function (data, type, full, meta) {
                    // we add the memberCount, so we could disable the button Add All
                    return (
                        '<span class="cartStatusButton" data-groupid="' +
                        full.Id +
                        '" data-memberCount="' +
                        full.memberCount +
                        '">' +
                        i18next.t("Checking Cart Status") +
                        "</span>"
                    );
                },
            },
            {
                width: "auto",
                title: i18next.t("Group Type"),
                data: "groupType",
                defaultContent: "",
                searchable: true,
                render: function (data, type, full, meta) {
                    if (data) {
                        return data;
                    } else {
                        return i18next.t("Unassigned");
                    }
                },
            },
        ],
    };

    $.extend(dataTableConfig, window.CRM.plugin.dataTable);

    dataT = $("#groupsTable")
        .DataTable(dataTableConfig)
        .on("draw.dt", function () {
            $(".cartStatusButton").each(function (index, element) {
                var objectID = $(element).data("groupid");
                var numberOfMembers = $(element).data("membercount"); // PL : we know the number of members

                var activLink = "";
                if (numberOfMembers === 0) {
                    activLink = " disabled"; // PL : We disable the button Add All when there isn't any member in the group
                }

                if ($.inArray(objectID, window.CRM.groupsInCart) > -1) {
                    $(element).html(
                        "<span>" +
                            i18next.t(
                                "All members of this group are in the cart",
                            ) +
                            '</span><button class="RemoveFromCart btn btn-danger" data-cart-id="' +
                            objectID +
                            '" data-cart-type="group">' +
                            i18next.t("Remove all") +
                            "</button>",
                    );
                } else {
                    $(element).html(
                        "<span>" +
                            i18next.t(
                                "Not all members of this group are in the cart",
                            ) +
                            '</span> <button class="AddToCart btn' +
                            activLink +
                            '" data-cart-id="' +
                            objectID +
                            '" data-cart-type="group"><i class="fa-solid fa-cart-plus"></i></button>',
                    );
                }
            });
        });
});
