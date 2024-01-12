$(document).ready(function () {
    $.ajax({
        method: "GET",
        url:
            window.CRM.root +
            "/api/groups/" +
            window.CRM.currentGroup +
            "/roles",
        dataType: "json",
    }).done(function (data) {
        window.CRM.groupRoles = data?.ListOptions ?? [];
        $("#newRoleSelection").select2({
            data: window.CRM.groupRoles.map((groupRole)=> {
                return {
                    id: groupRole.OptionId,
                    text: i18next.t(groupRole.OptionName),
                };
            }),
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
            dataType: "json",
            delay: 250,
            data: function (params) {
                return {
                    q: params.term, // search term
                    page: params.page,
                };
            },
            processResults: function (rdata, page) {
                return { results: rdata };
            },
            cache: true,
        },
    });

    $(".personSearch").on("select2:select", function (e) {
        window.CRM.groups.promptSelection(
            {
                Type: window.CRM.groups.selectTypes.Role,
                GroupID: window.CRM.currentGroup,
            },
            function (selection) {
                window.CRM.groups
                    .addPerson(
                        window.CRM.currentGroup,
                        e.params.data.objid,
                        selection.RoleID,
                    )
                    .done(function (data) {
                        $(".personSearch").val(null).trigger("change");
                        window.CRM.DataTableAPI.ajax.reload(); /* we reload the data no need to add the person inside the dataTable */
                    });
            },
        );
    });

    $("#deleteSelectedRows").click(function () {
        var deletedRows = window.CRM.DataTableAPI.rows(".selected").data();
        bootbox.confirm({
            message:
                i18next.t(
                    "Are you sure you want to remove the selected group members?",
                ) +
                " (" +
                deletedRows.length +
                ") ",
            buttons: {
                confirm: {
                    label: i18next.t("Delete"),
                    className: "btn-danger",
                },
                cancel: {
                    label: i18next.t("No"),
                    className: "btn-default",
                },
            },
            callback: function (result) {
                if (result) {
                    $.each(deletedRows, function (index, value) {
                        window.CRM.groups
                            .removePerson(
                                window.CRM.currentGroup,
                                value.PersonId,
                            )
                            .done(function () {
                                window.CRM.DataTableAPI.row(
                                    function (idx, data, node) {
                                        if (data.PersonId == value.PersonId) {
                                            return true;
                                        }
                                    },
                                ).remove();
                                window.CRM.DataTableAPI.rows()
                                    .invalidate()
                                    .draw(true);
                            });
                    });
                }
            },
        });
    });

    $("#addSelectedToCart").click(function () {
        if (window.CRM.DataTableAPI.rows(".selected").length > 0) {
            var selectedPersons = {
                Persons: $.map(
                    window.CRM.DataTableAPI.rows(".selected").data(),
                    function (val, i) {
                        return val.PersonId;
                    },
                ),
            };
            window.CRM.cart.addPerson(selectedPersons.Persons);
        }
    });

    //copy membership
    $("#addSelectedToGroup").click(function () {
        window.CRM.groups.promptSelection(
            {
                Type:
                    window.CRM.groups.selectTypes.Group |
                    window.CRM.groups.selectTypes.Role,
            },
            function (data) {
                selectedRows = window.CRM.DataTableAPI.rows(".selected").data();
                $.each(selectedRows, function (index, value) {
                    window.CRM.groups.addPerson(
                        data.GroupID,
                        value.PersonId,
                        data.RoleID,
                    );
                });
            },
        );
    });

    $("#moveSelectedToGroup").click(function () {
        window.CRM.groups.promptSelection(
            {
                Type:
                    window.CRM.groups.selectTypes.Group |
                    window.CRM.groups.selectTypes.Role,
            },
            function (data) {
                selectedRows = window.CRM.DataTableAPI.rows(".selected").data();
                $.each(selectedRows, function (index, value) {
                    console.log(data);
                    window.CRM.groups.addPerson(
                        data.GroupID,
                        value.PersonId,
                        data.RoleID,
                    );
                    window.CRM.groups
                        .removePerson(window.CRM.currentGroup, value.PersonId)
                        .done(function () {
                            window.CRM.DataTableAPI.row(
                                function (idx, data, node) {
                                    if (data.PersonId == value.PersonId) {
                                        return true;
                                    }
                                },
                            ).remove();
                            window.CRM.DataTableAPI.rows()
                                .invalidate()
                                .draw(true);
                        });
                });
            },
        );
    });

    $("#AddGroupMembersToCart").click(function () {
        window.CRM.cart.addGroup($(this).data("groupid"));
    });

    $(document).on("click", ".changeMembership", function (e) {
        var PersonID = $(e.currentTarget).data("personid");
        window.CRM.groups.promptSelection(
            {
                Type: window.CRM.groups.selectTypes.Role,
                GroupID: window.CRM.currentGroup,
            },
            function (selection) {
                window.CRM.groups
                    .addPerson(
                        window.CRM.currentGroup,
                        PersonID,
                        selection.RoleID,
                    )
                    .done(function () {
                        window.CRM.DataTableAPI.row(function (idx, data, node) {
                            if (data.PersonId == PersonID) {
                                data.RoleId = selection.RoleID;
                                return true;
                            }
                        });
                        window.CRM.DataTableAPI.rows().invalidate().draw(true);
                    });
            },
        );
        e.stopPropagation();
    });
});

function initDataTable() {
    var DataTableOpts = {
        ajax: {
            url:
                window.CRM.root +
                "/api/groups/" +
                window.CRM.currentGroup +
                "/members",
            dataSrc: "Person2group2roleP2g2rs",
        },
        columns: [
            {
                width: "auto",
                title: i18next.t("Name"),
                data: "PersonId",
                render: function (data, type, full, meta) {
                    return (
                        '<img src="' +
                        window.CRM.root +
                        "/api/person/" +
                        full.PersonId +
                        '/thumbnail" class="direct-chat-img initials-image" style="width:' +
                        window.CRM.iProfilePictureListSize +
                        "px; height:" +
                        window.CRM.iProfilePictureListSize +
                        'px"> &nbsp <a href="PersonView.php?PersonID="' +
                        full.PersonId +
                        '"><a target="_top" href="PersonView.php?PersonID=' +
                        full.PersonId +
                        '">' +
                        full.Person.FirstName +
                        " " +
                        full.Person.LastName +
                        "</a>"
                    );
                },
            },
            {
                width: "auto",
                title: i18next.t("Group Role"),
                data: "RoleId",
                render: function (data, type, full, meta) {
                    thisRole = $(window.CRM.groupRoles).filter(
                        function (index, item) {
                            return item.OptionId == data;
                        },
                    )[0];
                    return (
                        i18next.t(thisRole?.OptionName) +
                        '<button class="changeMembership" data-personid=' +
                        full.PersonId +
                        '><i class="fas fa-pen"></i></button>'
                    );
                },
            },
            {
                width: "auto",
                title: i18next.t("Address"),
                render: function (data, type, full, meta) {
                    var address = full.Person.Address1;
                    if (full.Person.Address2) {
                        address += " " + full.Person.Address2;
                    }
                    return address;
                },
            },
            {
                width: "auto",
                title: i18next.t("City"),
                data: "Person.City",
            },
            {
                width: "auto",
                title: i18next.t("State"),
                data: "Person.State",
            },
            {
                width: "auto",
                title: i18next.t("Zip Code"),
                data: "Person.Zip",
            },
            {
                width: "auto",
                title: i18next.t("Cell Phone"),
                data: "Person.CellPhone",
            },
            {
                width: "auto",
                title: i18next.t("Email"),
                data: "Person.Email",
            },
        ],
        fnDrawCallback: function (oSettings) {
            $("#iTotalMembers").text(oSettings.aoData.length);
        },
        createdRow: function (row, data, index) {
            $(row).addClass("groupRow");
        },
    };
    $.extend(DataTableOpts, window.CRM.plugin.DataTable);
    window.CRM.DataTableAPI = $("#membersTable").DataTable(DataTableOpts);

    $("#isGroupActive").change(function () {
        $.ajax({
            type: "POST", // define the type of HTTP verb we want to use (POST for our form)
            url:
                window.CRM.root +
                "/api/groups/" +
                window.CRM.currentGroup +
                "/settings/active/" +
                $(this).prop("checked"),
            dataType: "json", // what type of data do we expect back from the server
            encode: true,
        });
    });

    $("#isGroupEmailExport").change(function () {
        $.ajax({
            type: "POST", // define the type of HTTP verb we want to use (POST for our form)
            url:
                window.CRM.root +
                "/api/groups/" +
                window.CRM.currentGroup +
                "/settings/email/export/" +
                $(this).prop("checked"),
            dataType: "json", // what type of data do we expect back from the server
            encode: true,
        });
    });

    $(document).on("click", ".groupRow", function () {
        $(this).toggleClass("selected");
        var selectedRows =
            window.CRM.DataTableAPI.rows(".selected").data().length;
        $("#deleteSelectedRows").prop("disabled", !selectedRows);
        $("#deleteSelectedRows").text(
            i18next.t("Remove") +
                " (" +
                selectedRows +
                ") " +
                i18next.t("Members from group"),
        );
        $("#buttonDropdown").prop("disabled", !selectedRows);
        $("#addSelectedToGroup").prop("disabled", !selectedRows);
        $("#addSelectedToGroup").html(
            i18next.t("Add") +
                "  (" +
                selectedRows +
                ") " +
                i18next.t("Members to another group"),
        );
        $("#addSelectedToCart").prop("disabled", !selectedRows);
        $("#addSelectedToCart").html(
            i18next.t("Add") +
                "  (" +
                selectedRows +
                ") " +
                i18next.t("Members to cart"),
        );
        $("#moveSelectedToGroup").prop("disabled", !selectedRows);
        $("#moveSelectedToGroup").html(
            i18next.t("Move") +
                "  (" +
                selectedRows +
                ") " +
                i18next.t("Members to another group"),
        );
    });
}
