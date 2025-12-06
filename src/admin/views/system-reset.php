<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
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
        window.CRM.onLocalesReady(function () {
            bootbox.prompt({
                title: i18next.t("Warning")+ "!!!",
                message: i18next.t("This page contains operations that reset the ChurchCRM database. The operations available on this page are irreversible. Ensure that you no longer need the data or data source before you proceed with any operation on this page.") + '<br/> <br/>' + i18next.t("Please type ") + "<b>" + i18next.t("I AGREE") + "</b>" + i18next.t(" to access the database reset functions page."),
                size: 'large',
                className: 'rubberBand animated',
                buttons: {
                    confirm: {
                        label: i18next.t('OK'),
                        className: 'btn-danger'
                    },
                    cancel: {
                        label: i18next.t('Cancel'),
                        className: 'btn-secondary'
                    }
                },
                callback: function (result) {
                    if (result !== i18next.t("I AGREE")) {
                        // If the user cancels the initial warning, go back to the
                        // system maintenance dashboard instead of the site root.
                        window.location.href = window.CRM.root + '/admin/';
                    }
                }
            });

            $("#confirm-db").click(function () {
                bootbox.confirm({
                    title: i18next.t("Warning") + "!!!",
                    message: i18next.t("This will reset the system data and will restart the system as a new install."),
                    size: 'small',
                    buttons: {
                        confirm: {
                            label: i18next.t('OK'),
                            className: 'btn-danger'
                        },
                        cancel: {
                            label: i18next.t('Cancel'),
                            className: 'btn-secondary'
                        }
                    },
                    callback: function (result) {
                        if (result) {
                            window.CRM.AdminAPIRequest({
                                path: 'database/reset',
                                method: 'DELETE'
                            })
                            .done(function (data) {
                                // Show default credentials to the admin after reset (matches other flows)
                                var username = (data && data.defaultUsername) ? data.defaultUsername : 'admin';
                                var password = (data && data.defaultPassword) ? data.defaultPassword : 'changeme';
                                var message = i18next.t('The database has been cleared.') + '<br><br>' +
                                    '<strong>' + i18next.t('Default admin credentials') + ':</strong><br>' +
                                    '<code>' + username + '</code> / <code>' + password + '</code>';

                                bootbox.alert({
                                    title: i18next.t('Reset Complete'),
                                    message: message,
                                    callback: function () {
                                        window.location.href = window.CRM.root + "/";
                                    }
                                });
                            })
                            .fail(function (xhr, status, error) {
                                var errorMessage = i18next.t('Database reset failed');
                                if (xhr.responseJSON && xhr.responseJSON.msg) {
                                    errorMessage = xhr.responseJSON.msg;
                                }
                                window.CRM.notify(errorMessage, {
                                    type: 'error',
                                    delay: 5000
                                });
                            });
                        }
                    }
                });
            });
        });
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
