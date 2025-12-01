<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><?= _("Add Menu") ?></h4>
            </div>
            <div class="card-body">
                <form id="menu-form" novalidate>
                    <div class="form-group">
                        <label for="MENU_NAME"><?= _("Menu Name")?></label>
                        <input type="text" name="MENU_NAME" id="MENU_NAME" class="form-control"
                               aria-describedby="MENU_NAME_HELP" required minlength="2" maxlength="50">
                        <small id="MENU_NAME_HELP" class="form-text text-muted"><?= _("2-50 characters")?></small>
                        <div class="invalid-feedback"><?= _("Please enter a menu name (2-50 characters)")?></div>
                    </div>
                    <div class="form-group">
                        <label for="MENU_LINK"><?= _("Link Address")?></label>
                        <input type="url" name="MENU_LINK" id="MENU_LINK" class="form-control"
                               aria-describedby="MENU_LINK_HELP" required 
                               placeholder="https://example.com">
                        <small id="MENU_LINK_HELP" class="form-text text-muted"><?= _("Start with http:// or https://")?></small>
                        <div class="invalid-feedback"><?= _("Please enter a valid URL starting with http:// or https://")?></div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-success" id="add-Menu">
                            <i class="fa-solid fa-plus"></i> <?= _("Add")?>
                        </button>
                    </div>
                </form>
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
                <div class="table-responsive">
                <table id="menus" class="table table-striped table-bordered data-table">
                    <tbody></tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializeMenusAdmin() {
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + "/api/system/menu",
                dataSrc: "menus"
            },
            responsive: false,
            autoWidth: false,
            columns: [
                {
                    width: '15%',
                    sortable: false,
                    title: i18next.t('Delete'),
                    data: 'Id',
                    render: function (data, type, row) {
                        // Escape ID to prevent XSS in onclick handler
                        var safeId = parseInt(data, 10);
                        return '<button type="button" class="btn btn-danger btn-sm delete-menu" data-menu-id="' + safeId + '"><i class="fa-solid fa-trash"></i></button>';
                    },
                    searchable: false
                },
                {
                    width: '40%',
                    title: i18next.t('Name'),
                    data: 'Name',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            // Escape HTML to prevent XSS
                            return $('<div>').text(data).html();
                        }
                        return data;
                    }
                },
                {
                    width: '45%',
                    title: i18next.t('Address'),
                    data: 'Uri',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            // Escape HTML to prevent XSS
                            return $('<div>').text(data).html();
                        }
                        return data;
                    }
                }
            ],
            order: [[1, "asc"]]
        }
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#menus").DataTable(dataTableConfig);

        // Use event delegation for delete buttons
        $('#menus').on('click', '.delete-menu', function() {
            var menuId = $(this).data('menu-id');
            deleteMenu(menuId);
        });
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializeMenusAdmin);

        // Form submission with HTML5 validation
        $("#menu-form").on('submit', function(e){
            e.preventDefault();
            e.stopPropagation();
            
            var form = this;
            var $button = $("#add-Menu");
            
            // Prevent double submission
            if ($button.prop('disabled')) {
                return false;
            }
            
            // Trim values first
            var $nameInput = $("#MENU_NAME");
            var $linkInput = $("#MENU_LINK");
            $nameInput.val($nameInput.val().trim());
            $linkInput.val($linkInput.val().trim());
            
            var menuName = $nameInput.val();
            var menuLink = $linkInput.val();
            
            // Validate for HTML/script tags BEFORE other validation
            if (/<[^>]*>/g.test(menuName)) {
                window.CRM.notify(i18next.t("Menu name cannot contain HTML tags"), {
                    type: 'error',
                    delay: 3000
                });
                $nameInput.val('');
                $(form).addClass('was-validated');
                return false;
            }
            
            if (/<[^>]*>/g.test(menuLink)) {
                window.CRM.notify(i18next.t("Link address cannot contain HTML tags"), {
                    type: 'error',
                    delay: 3000
                });
                $linkInput.val('');
                $(form).addClass('was-validated');
                return false;
            }
            
            // Check HTML5 validation after trimming
            if (form.checkValidity() === false) {
                // Show Bootstrap validation styling
                $(form).addClass('was-validated');
                return false;
            }

            // Additional JavaScript validation as fallback
            if (menuName.length < 2 || menuName.length > 50) {
                window.CRM.notify(i18next.t("Menu name must be 2-50 characters"), {
                    type: 'error',
                    delay: 3000
                });
                $(form).addClass('was-validated');
                return false;
            }

            // More permissive URL validation - just check it starts with http:// or https://
            if (!menuLink.match(/^https?:\/\//i)) {
                window.CRM.notify(i18next.t("Link must start with http:// or https://"), {
                    type: 'error',
                    delay: 3000
                });
                $(form).addClass('was-validated');
                return false;
            }

            // Validate URL has at least a domain after protocol
            var urlMatch = menuLink.match(/^https?:\/\/([^\/\s?#]+)/i);
            if (!urlMatch || !urlMatch[1] || urlMatch[1].length < 1) {
                window.CRM.notify(i18next.t("Link must include a valid domain name"), {
                    type: 'error',
                    delay: 3000
                });
                $(form).addClass('was-validated');
                return false;
            }

            // Strip HTML tags to prevent XSS (defense in depth - shouldn't be needed due to validation above)
            var cleanName = $('<div>').text(menuName).text();
            var cleanLink = $('<div>').text(menuLink).text();

            // Disable button during submission
            $button.prop('disabled', true);
            var originalText = $button.html();
            $button.html('<i class="fa-solid fa-spinner fa-spin"></i> ' + i18next.t('Adding...'));

            window.CRM.APIRequest({
                method: "PUT",
                path: "system/menu/",
                data: JSON.stringify({
                    "Name": cleanName,
                    "Uri": cleanLink,
                    "Order": 0
                })
            }).done(function () {
                window.CRM.notify(i18next.t("Menu added successfully"), {
                    type: 'success',
                    delay: 3000
                });
                // Reset form and remove validation styling
                form.reset();
                $(form).removeClass('was-validated');
                $("#menus").DataTable().ajax.reload();
            }).fail(function (xhr) {
                var errorMsg = i18next.t("Failed to add menu");
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMsg = xhr.responseJSON.error;
                    // Show validation failures if available
                    if (xhr.responseJSON.failures) {
                        var failures = xhr.responseJSON.failures;
                        var failureMessages = [];
                        for (var key in failures) {
                            failureMessages.push(failures[key]);
                        }
                        if (failureMessages.length > 0) {
                            errorMsg += ': ' + failureMessages.join(', ');
                        }
                    }
                }
                window.CRM.notify(errorMsg, {
                    type: 'error',
                    delay: 5000
                });
            }).always(function() {
                // Re-enable button
                $button.prop('disabled', false);
                $button.html(originalText);
            });
            
            return false;
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
                        path: "system/menu/" + parseInt(menuId, 10)
                    }).done(function () {
                        window.CRM.notify(i18next.t("Menu deleted successfully"), {
                            type: 'success',
                            delay: 3000
                        });
                        $("#menus").DataTable().ajax.reload();
                    }).fail(function () {
                        window.CRM.notify(i18next.t("Failed to delete menu"), {
                            type: 'error',
                            delay: 3000
                        });
                    });
                }
            }
        });
    }
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
