<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Breadcrumb Navigation -->
<div class="row mb-3">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item">
                    <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard">
                        <i class="fa-solid fa-home"></i>
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?= SystemURLs::getRootPath() ?>/plugins/management"><?= gettext('Plugins') ?></a>
                </li>
                <li class="breadcrumb-item active"><?= gettext('Custom Menu Links') ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <h4><i class="fa-solid fa-plus mr-2"></i><?= gettext('Add Link') ?></h4>
            </div>
            <div class="card-body">
                <form id="link-form" novalidate>
                    <div class="form-group">
                        <label for="LINK_NAME"><?= gettext('Link Name') ?></label>
                        <input type="text" name="LINK_NAME" id="LINK_NAME" class="form-control"
                               aria-describedby="LINK_NAME_HELP" required minlength="2" maxlength="50"
                               placeholder="<?= gettext('e.g., Church Website') ?>">
                        <small id="LINK_NAME_HELP" class="form-text text-muted"><?= gettext('2-50 characters') ?></small>
                        <div class="invalid-feedback"><?= gettext('Please enter a link name (2-50 characters)') ?></div>
                    </div>
                    <div class="form-group">
                        <label for="LINK_URL"><?= gettext('URL') ?></label>
                        <input type="url" name="LINK_URL" id="LINK_URL" class="form-control"
                               aria-describedby="LINK_URL_HELP" required 
                               placeholder="https://example.com">
                        <small id="LINK_URL_HELP" class="form-text text-muted"><?= gettext('Must start with http:// or https://') ?></small>
                        <div class="invalid-feedback"><?= gettext('Please enter a valid URL starting with http:// or https://') ?></div>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-success" id="add-link">
                            <i class="fa-solid fa-plus"></i> <?= gettext('Add Link') ?>
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
                <h4><i class="fa-solid fa-link mr-2"></i><?= gettext('Menu Links') ?></h4>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    <i class="fa-solid fa-info-circle"></i>
                    <?= gettext('These links appear in the "Links" menu in the navigation sidebar when this plugin is enabled.') ?>
                </p>
                <div class="table-responsive">
                    <table id="links-table" class="table table-striped table-bordered data-table">
                        <thead>
                            <tr>
                                <th width="15%"><?= gettext('Actions') ?></th>
                                <th width="35%"><?= gettext('Name') ?></th>
                                <th width="50%"><?= gettext('URL') ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
    // Initialize DataTable
    var table = $("#links-table").DataTable({
        ajax: {
            url: window.CRM.root + "/plugins/custom-links/api/links",
            dataSrc: "data"
        },
        responsive: false,
        autoWidth: false,
        columns: [
            {
                sortable: false,
                data: 'Id',
                render: function(data, type, row) {
                    var safeId = parseInt(data, 10);
                    return '<button type="button" class="btn btn-danger btn-sm delete-link" data-id="' + safeId + '" title="' + i18next.t('Delete') + '">' +
                           '<i class="fa-solid fa-trash"></i></button>';
                },
                searchable: false
            },
            {
                data: 'Name',
                render: function(data, type, row) {
                    if (type === 'display') {
                        return $('<div>').text(data).html();
                    }
                    return data;
                }
            },
            {
                data: 'Uri',
                render: function(data, type, row) {
                    if (type === 'display') {
                        var escaped = $('<div>').text(data).html();
                        return '<a href="' + escaped + '" target="_blank" rel="noopener">' + escaped + ' <i class="fa-solid fa-external-link-alt fa-xs"></i></a>';
                    }
                    return data;
                }
            }
        ],
        order: [[1, "asc"]],
        language: {
            emptyTable: i18next.t("No custom links configured. Add one above!")
        }
    });

    // Delete handler using event delegation
    $('#links-table').on('click', '.delete-link', function() {
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        var name = table.row(row).data().Name;
        
        bootbox.confirm({
            message: i18next.t("Are you sure you want to delete the link") + ': <strong>' + $('<div>').text(name).html() + '</strong>?',
            buttons: {
                confirm: { label: i18next.t('Yes'), className: 'btn-danger' },
                cancel: { label: i18next.t('No'), className: 'btn-secondary' }
            },
            callback: function(result) {
                if (result) {
                    fetch(window.CRM.root + '/plugins/custom-links/api/links/' + id, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' }
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            window.CRM.notify(i18next.t('Link deleted'), { type: 'success' });
                            table.ajax.reload();
                        } else {
                            window.CRM.notify(data.message || i18next.t('Failed to delete link'), { type: 'error' });
                        }
                    })
                    .catch(function() {
                        window.CRM.notify(i18next.t('Failed to delete link'), { type: 'error' });
                    });
                }
            }
        });
    });

    // Form submission
    $("#link-form").on('submit', function(e) {
        e.preventDefault();
        
        var form = this;
        var $button = $("#add-link");
        
        if ($button.prop('disabled')) return false;
        
        // Trim inputs
        var $nameInput = $("#LINK_NAME");
        var $urlInput = $("#LINK_URL");
        $nameInput.val($nameInput.val().trim());
        $urlInput.val($urlInput.val().trim());
        
        var linkName = $nameInput.val();
        var linkUrl = $urlInput.val();
        
        // Validate for HTML tags
        if (/<[^>]*>/g.test(linkName)) {
            window.CRM.notify(i18next.t("Link name cannot contain HTML tags"), { type: 'error' });
            $nameInput.val('');
            $(form).addClass('was-validated');
            return false;
        }
        
        if (/<[^>]*>/g.test(linkUrl)) {
            window.CRM.notify(i18next.t("URL cannot contain HTML tags"), { type: 'error' });
            $urlInput.val('');
            $(form).addClass('was-validated');
            return false;
        }
        
        // HTML5 validation
        if (!form.checkValidity()) {
            $(form).addClass('was-validated');
            return false;
        }
        
        // URL format validation
        if (!linkUrl.match(/^https?:\/\//i)) {
            window.CRM.notify(i18next.t("URL must start with http:// or https://"), { type: 'error' });
            $(form).addClass('was-validated');
            return false;
        }
        
        // Disable button during submission
        $button.prop('disabled', true);
        var originalText = $button.html();
        $button.html('<i class="fa-solid fa-spinner fa-spin"></i> ' + i18next.t('Adding...'));
        
        fetch(window.CRM.root + '/plugins/custom-links/api/links', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                Name: linkName,
                Uri: linkUrl,
                Order: 0
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                window.CRM.notify(i18next.t('Link added successfully'), { type: 'success' });
                form.reset();
                $(form).removeClass('was-validated');
                table.ajax.reload();
            } else {
                var errorMsg = data.message || i18next.t('Failed to add link');
                if (data.errors && data.errors.length > 0) {
                    errorMsg += ': ' + data.errors.join(', ');
                }
                window.CRM.notify(errorMsg, { type: 'error' });
            }
        })
        .catch(function() {
            window.CRM.notify(i18next.t('Failed to add link'), { type: 'error' });
        })
        .finally(function() {
            $button.prop('disabled', false);
            $button.html(originalText);
        });
        
        return false;
    });
});
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
