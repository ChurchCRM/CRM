<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h4><?= _("Add Menu") ?></h4>
            </div>
            <div class="box-body">
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header">
                <h4><?= _("Menus") ?></h4>
            </div>
            <div class="box-body">
                <table id="menus" class="table table-striped table-bordered table-responsive data-table">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>


<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        $("#menus").DataTable({
            "language": {
                "url": window.CRM.plugin.dataTable.language.url
            },
            ajax: {
                url: window.CRM.root + "/api/system/menu",
                dataSrc: "menus"
            },
            responsive: true,
            columns: [
                {
                    width: '15px',
                    sortable: false,
                    title: i18next.t('Delete'),
                    data: 'Id',
                    render: function (data, type, row) {
                        return '<a class="btn" onclick="deleteMenu(' + row.Id + ')"><i class="fa fa-trash bg-red"></i></a>';
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
                },
                {
                    title: i18next.t('Order'),
                    data: 'Order',
                    searchable: false
                }
            ],
            order: [[3, "asc"]]
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
