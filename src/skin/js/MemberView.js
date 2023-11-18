function contentExists(contentUrl, callback) {
    $.ajax({
        method: "HEAD",
        url: contentUrl,
        processData: false,
        global: false,
        success: function (data, textStatus, jqXHR) {
            callback(true, data, textStatus, jqXHR);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            callback(false, jqXHR, textStatus, errorThrown);
        },
    });
}

$(".delete-person").click(function (event) {
    event.preventDefault();
    var thisLink = $(this);
    bootbox.confirm({
        title: i18next.t("Delete this person?"),
        message:
            i18next.t(
                "Do you want to delete this person?  This cannot be undone.",
            ) +
            " <b>" +
            thisLink.data("person_name"),
        buttons: {
            cancel: {
                label: '<i class="fa fa-times"></i> ' + i18next.t("Cancel"),
            },
            confirm: {
                label: '<i class="fa fa-trash"></i> ' + i18next.t("Delete"),
                className: "btn-danger",
            },
        },
        callback: function (result) {
            if (result) {
                $.ajax({
                    type: "DELETE",
                    url:
                        window.CRM.root +
                        "/api/person/" +
                        thisLink.data("person_id"),
                    dataType: "json",
                    success: function (data, status, xmlHttpReq) {
                        if (thisLink.data("view") === "family") {
                            location.reload();
                        } else {
                            location.replace(window.CRM.root + "/");
                        }
                    },
                });
            }
        },
    });
});
