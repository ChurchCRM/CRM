<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= _("Add Menu") ?></h4>
            </div>
            <div class="card-body">
                <section>
                    <div class="form-group">
                        <label for="MENU_NAME"><?= _("Menu Name")?></label>
                        <input type="text" name="MENU_NAME" id="MENU_NAME" class="form-control"
                               aria-describedby="MENU_NAME_HELP" required maxlength="20" max="20" size="20">
                        <small id="MENU_NAME_HELP" class="form-text text-muted"><?= _("Max 20 char")?></small>
                    </div>
                    <div class="form-group">
                        <label for="MENU_LINK"><?= _("Link Address")?></label>
                        <input type="text" name="MENU_LINK" id="MENU_LINK" class="form-control"
                               aria-describedby="MENU_LINK_HELP" required>
                        <small id="MENU_LINK_HELP" class="form-text text-muted"><?= _("Start with http:// or https://")?></small>
                    </div>
                </section>
                <div align="right">
                    <a class="btn btn-success" id="add-Menu"><?= _("Add")?></a>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= _("Menus") ?></h4>
            </div>
            <div class="card-body">
                <table id="menus" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/system/menu",
                dataSrc: "menus"
            },
            columns: [
                {
                    width: '15px',
                    sortable: false,
                    title: i18next.t('Delete'),
                    data: 'Id',
                    render: function (data, type, row) {
                        return '<a class="btn btn-default" onclick="deleteMenu(' + row.Id + ')"><i class="fa fa-trash bg-red"></i></a>';
                    },
                    searchable: false
                },
                {
                    title: i18next.t('Name'),
                    data: 'Name'
                },
                {
                    title: i18next.t('Address'),
                    data: 'Uri'
                }
            ],
            order: [[1, "asc"]]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#menus").DataTable(dataTableConfig);
    });

    $("#add-Menu").click(function(){
        menuName = $("#MENU_NAME").val();
        menuLink = $("#MENU_LINK").val();
        window.CRM.APIRequest({
            method: "PUT",
            path: "system/menu/",
            data: JSON.stringify(    {
                "Name": menuName,
                "Uri": menuLink,
                "Order": 0
            })
        }).done(function () {
            $("#MENU_NAME").val("");
            $("#MENU_LINK").val("");
            $("#menus").DataTable().ajax.reload()
        });
    });

    function deleteMenu(menuId) {
        bootbox.confirm({
            message: i18next.t("Are you sure you want to remove the selected menu?"),
            buttons: {
                confirm: {
                    label: i18next.t('Yes'),
                    className: 'btn-success'
                },
                cancel: {
                    label: i18next.t('No'),
                    className: 'btn-danger'
                }
            },
            callback: function (result) {
                if (result) {
                    window.CRM.APIRequest({
                        method: "DELETE",
                        path: "system/menu/" + menuId
                    }).done(function () {
                        $("#menus").DataTable().ajax.reload()
                    });
                }
            }
        });

    };
</script>


<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
