<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h4><?= gettext("Reset Members") ?></h4>
            </div>
            <div class="card-body">
                <div class="">
                    <?= gettext("This will remove all the member data, people, and families and can't be undone.") ?>
                </div>
                <p><br/></p>
                <div class="text-center">
                    <button type="button" class="btn btn-danger"
                            id="confirm-people"><?= gettext("Reset Families/People") ?></button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h4><?= gettext("Reset Database") ?></h4>
            </div>
            <div class="card-body">
                <div class="">
                    <?= gettext("This will reset the system data and will restart the system as a new install.") ?>
                </div>
                <p><br/></p>
                <div class="text-center">
                    <button type="button" class="btn btn-danger"
                            id="confirm-db"><?= gettext("Reset Database") ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        bootbox.prompt({
            title: i18next.t("Warning")+ "!!!",
            message: i18next.t("This page contains operations that reset the ChurchCRM database. The operations available on this page are irreversible. Ensure that you no longer need the data or data source before you proceed with any operation on this page.") + '<br/> <br/>' + i18next.t("Please type ") + "<b>" + i18next.t("I AGREE") + "</b>" + i18next.t(" to access the database reset functions page."),
            size: 'large',
            className: 'rubberBand animated',
            buttons: {
                confirm: {
                    label: i18next.t('OK'),
                    className: 'btn-success'
                },
                cancel: {
                    label: i18next.t('Cancel'),
                    className: 'btn-danger'
                }
            },
            callback: function (result) {
                if (result !== i18next.t("I AGREE")) {
                    window.location.href = window.CRM.root + "/";
                }
            }
        });

        $("#confirm-people").click(function () {
            bootbox.confirm({
                title: i18next.t("Warning") + "!!!",
                message: i18next.t("This will remove all the member data, people, and families and can't be undone."),
                size: 'small',
                callback: function (result) {
                    window.CRM.APIRequest({
                        method: "DELETE",
                        path: "database/people/clear",
                    }).done(function (data) {
                        window.location.href = window.CRM.root + "/";
                    });
                }
            });
        });

        $("#confirm-db").click(function () {
            bootbox.confirm({
                title: i18next.t("Warning") + "!!!",
                message: i18next.t("This will reset the system data and will restart the system as a new install."),
                size: 'small',
                callback: function (result) {
                    window.CRM.APIRequest({
                        method: "DELETE",
                        path: "database/reset",
                    }).done(function (data) {
                        window.location.href = window.CRM.root + "/";
                    });
                }
            });
        });
    });
</script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
