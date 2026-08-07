<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;

$sPageTitle = gettext('People Verify Dashboard');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/* -----------------------------------------------------------------------
 * Structured email result alert vars (set by the route handler)
 * --------------------------------------------------------------------- */
$emailErrorReason  = htmlspecialchars((string) ($emailErrorReason  ?? ''), ENT_QUOTES, 'UTF-8');
$emailErrorSent    = (int) ($emailErrorSent   ?? 0);
$emailErrorFailed  = (int) ($emailErrorFailed ?? 0);
$emailSuccessCount = (int) ($emailSuccessCount ?? 0);

// Compute a PHP-side alert message for the reason code
$emailAlertClass   = '';
$emailAlertMsg     = '';
$emailAlertIcon    = '';
if ($emailErrorReason !== '') {
    switch ($emailErrorReason) {
        case 'no_recipients':
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-mail-off';
            $emailAlertMsg   = gettext('No families with an email address were found. Nothing was sent.');
            break;
        case 'smtp_failure':
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-cloud-off';
            $emailAlertMsg   = gettext('All email sends failed. Please check your SMTP settings in System Settings.');
            break;
        case 'partial_failure':
            $emailAlertClass = 'warning';
            $emailAlertIcon  = 'ti ti-alert-triangle';
            $total = $emailErrorSent + $emailErrorFailed;
            $emailAlertMsg = sprintf(
                gettext('Sent %1$d of %2$d — %3$d families could not be reached. Check application logs for details.'),
                $emailErrorSent,
                $total,
                $emailErrorFailed
            );
            break;
        default:
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-alert-circle';
            $emailAlertMsg   = gettext('An unexpected error occurred while sending emails. Please check the application logs.');
    }
}
?>

<?php /* ---- Hidden CSRF form (used by AJAX POST from the modal) ---- */ ?>
<form id="verifyEmailAllForm" method="post" action="<?= SystemURLs::getRootPath() ?>/people/report/verify/email" class="d-none">
    <?= CSRFUtils::getTokenInputField('people_report_verify_email') ?>
</form>

<?php /* ---- Error / Partial-failure alert ---- */ ?>
<?php if ($emailAlertMsg !== ''): ?>
<div id="emailResultAlert"
     class="alert alert-<?= $emailAlertClass ?> alert-dismissible fade show mb-3"
     role="alert"
     data-cy="email-result-alert">
    <i class="<?= $emailAlertIcon ?> me-2" aria-hidden="true"></i>
    <strong>
        <?php if ($emailAlertClass === 'danger'): ?>
            <?= gettext('Email Error') ?>
        <?php else: ?>
            <?= gettext('Partial Send') ?>
        <?php endif ?>
    </strong>
    <?= htmlspecialchars($emailAlertMsg, ENT_QUOTES, 'UTF-8') ?>
    <button type="button"
            class="btn btn-sm btn-outline-<?= $emailAlertClass ?> ms-3"
            id="retryVerifyEmail"
            data-cy="retry-email-btn">
        <i class="ti ti-refresh me-1" aria-hidden="true"></i><?= gettext('Retry') ?>
    </button>
    <button type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="<?= gettext('Close') ?>"></button>
</div>
<?php endif ?>

<?php /* ---- Success alert (inline version of the toast) ---- */ ?>
<?php if ($emailSuccessCount > 0): ?>
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert" data-cy="email-success-alert">
    <i class="ti ti-circle-check me-2" aria-hidden="true"></i>
    <?= htmlspecialchars(
        sprintf(
            ngettext('PDF successfully emailed to %d family.', 'PDFs successfully emailed to %d families.', $emailSuccessCount),
            $emailSuccessCount
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= gettext('Close') ?>"></button>
</div>
<?php endif ?>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= gettext('Functions') ?></h3>
    </div>
    <div class="card-body">
        <div class="btn-group" role="group">
            <a href="<?= SystemURLs::getRootPath()?>/people/report/verify" class="btn btn-outline-danger" title="<?= gettext('Generate and download confirmation letters') ?>">
                <i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Letters') ?>
            </a>
            <button type="button" class="btn btn-outline-primary" id="verifyEmail" title="<?= gettext('Send email to families') ?>">
                <i class="fa-solid fa-envelope me-2"></i><?= gettext('Email Families') ?>
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= _("Self Verify") ?></h3>
    </div>
    <div class="table-responsive">
        <table id="families-complete" class="table table-vcenter table-hover card-table">
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= _("Pending Self Verify") ?></h3>
    </div>
    <div class="table-responsive">
        <table id="families-pending" class="table table-vcenter table-hover card-table">
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- ================================================================
     Send Confirmation Preview Modal
     ================================================================ -->
<div class="modal fade" id="verifyEmailModal" tabindex="-1"
     aria-labelledby="verifyEmailModalLabel" aria-hidden="true"
     data-cy="verify-email-modal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="verifyEmailModalLabel">
                    <i class="ti ti-send me-2" aria-hidden="true"></i>
                    <span id="modalTitle"><?= gettext('Send Family Verification Emails') ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="<?= gettext('Close') ?>"></button>
            </div>

            <!-- Loading state -->
            <div id="modalLoading" class="modal-body text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden"><?= gettext('Loading preview…') ?></span>
                </div>
                <p class="mt-2 text-muted"><?= gettext('Loading email preview…') ?></p>
            </div>

            <!-- Preview content (hidden until loaded) -->
            <div id="modalPreview" class="modal-body d-none">

                <!-- Recipient count -->
                <div class="alert alert-info d-flex align-items-center mb-3" data-cy="modal-recipient-count">
                    <i class="ti ti-users me-2 fs-4" aria-hidden="true"></i>
                    <span id="recipientCountText"></span>
                </div>

                <!-- No-email warning -->
                <div id="noEmailWarning" class="alert alert-warning d-none mb-3" data-cy="modal-no-email-warning">
                    <i class="ti ti-alert-triangle me-2" aria-hidden="true"></i>
                    <span id="noEmailWarningText"></span>
                </div>

                <!-- Template preview -->
                <div class="mb-3">
                    <h6><?= gettext('Email Template Preview') ?></h6>
                    <table class="table table-sm table-bordered mb-1">
                        <tr>
                            <th class="text-nowrap" style="width:6rem"><?= gettext('Subject') ?></th>
                            <td id="previewSubject" class="font-monospace"></td>
                        </tr>
                        <tr>
                            <th><?= gettext('Body excerpt') ?></th>
                            <td id="previewBody" style="white-space:pre-wrap;word-break:break-word"></td>
                        </tr>
                    </table>
                </div>

                <!-- Recipient list (collapsible) -->
                <div class="mb-2">
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#recipientListCollapse"
                            aria-expanded="false"
                            aria-controls="recipientListCollapse"
                            data-cy="toggle-recipient-list">
                        <i class="ti ti-list me-1" aria-hidden="true"></i>
                        <span id="recipientListToggleLabel"><?= gettext('Show recipient list') ?></span>
                    </button>
                </div>
                <div class="collapse" id="recipientListCollapse">
                    <div class="mb-2">
                        <input type="search" id="recipientSearch"
                               class="form-control form-control-sm"
                               placeholder="<?= gettext('Filter families…') ?>"
                               data-cy="recipient-search">
                    </div>
                    <div id="recipientList" style="max-height:250px;overflow-y:auto">
                        <table class="table table-sm table-hover" id="recipientTable">
                            <thead>
                                <tr>
                                    <th><?= gettext('Family') ?></th>
                                    <th><?= gettext('Email') ?></th>
                                </tr>
                            </thead>
                            <tbody id="recipientTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- In-modal result banner (shown after AJAX send) -->
            <div id="modalResultBanner" class="px-3 d-none"></div>

            <!-- Footer -->
            <div class="modal-footer" id="modalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                        data-cy="modal-cancel-btn">
                    <i class="ti ti-x me-1" aria-hidden="true"></i>
                    <?= gettext('Cancel') ?>
                </button>
                <button type="button" class="btn btn-primary" id="modalSendBtn"
                        data-cy="modal-send-btn" disabled>
                    <i class="ti ti-send me-1" aria-hidden="true"></i>
                    <?= gettext('Send Emails') ?>
                </button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    function initializePeopleVerify() {

        var verifyModal = new bootstrap.Modal(document.getElementById('verifyEmailModal'));
        var allRecipients = [];

        /* ---- Open modal: fetch preview data ---- */
        document.getElementById('verifyEmail').addEventListener('click', function () {
            resetModal();
            verifyModal.show();
            fetchPreview();
        });

        /* ---- Retry button (in the error alert) re-opens the modal ---- */
        var retryBtn = document.getElementById('retryVerifyEmail');
        if (retryBtn) {
            retryBtn.addEventListener('click', function () {
                resetModal();
                verifyModal.show();
                fetchPreview();
            });
        }

        function resetModal() {
            document.getElementById('modalLoading').classList.remove('d-none');
            document.getElementById('modalPreview').classList.add('d-none');
            document.getElementById('modalResultBanner').classList.add('d-none');
            document.getElementById('modalResultBanner').innerHTML = '';
            document.getElementById('modalSendBtn').disabled = true;
            document.getElementById('modalSendBtn').innerHTML =
                '<i class="ti ti-send me-1" aria-hidden="true"></i>' +
                i18next.t('Send Emails');
        }

        function fetchPreview() {
            fetch(window.CRM.root + '/api/families/verify-email-preview', {
                headers: { 'Accept': 'application/json' }
            })
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (data) {
                populatePreview(data);
            })
            .catch(function (err) {
                document.getElementById('modalLoading').classList.add('d-none');
                document.getElementById('modalPreview').classList.remove('d-none');
                document.getElementById('modalPreview').innerHTML =
                    '<div class="alert alert-danger">' +
                    '<i class="ti ti-alert-circle me-2"></i>' +
                    i18next.t('Could not load email preview. Please check your connection and try again.') +
                    '</div>';
            });
        }

        function populatePreview(data) {
            allRecipients = data.recipients || [];

            // Recipient count
            var countText = i18next.t('About to email {{count}} families.', { count: data.recipientCount });
            document.getElementById('recipientCountText').textContent = countText;
            document.getElementById('recipientListToggleLabel').textContent =
                i18next.t('Show recipient list ({{count}})', { count: allRecipients.length });

            // No-email warning
            var noEmailWarning = document.getElementById('noEmailWarning');
            if (data.familiesWithoutEmail && data.familiesWithoutEmail.length > 0) {
                noEmailWarning.classList.remove('d-none');
                document.getElementById('noEmailWarningText').textContent = i18next.t(
                    '{{count}} families have no email address on file and will not receive this email.',
                    { count: data.familiesWithoutEmail.length }
                );
            } else {
                noEmailWarning.classList.add('d-none');
            }

            // Template preview
            if (data.templatePreview) {
                document.getElementById('previewSubject').textContent =
                    data.templatePreview.subject || '';
                document.getElementById('previewBody').textContent =
                    data.templatePreview.bodyExcerpt || i18next.t('(no body text configured)');
            }

            // Recipient table
            renderRecipientTable(allRecipients);

            // Show preview, hide loading, enable send button
            document.getElementById('modalLoading').classList.add('d-none');
            document.getElementById('modalPreview').classList.remove('d-none');
            document.getElementById('modalSendBtn').disabled = (data.recipientCount === 0);
        }

        function renderRecipientTable(list) {
            var tbody = document.getElementById('recipientTableBody');
            tbody.innerHTML = '';
            list.forEach(function (fam) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + escapeHtml(fam.name) + '</td>' +
                    '<td><small class="text-muted">' + escapeHtml(fam.email) + '</small></td>';
                tbody.appendChild(tr);
            });
        }

        // Filter recipient table as user types
        document.getElementById('recipientSearch').addEventListener('input', function () {
            var q = this.value.toLowerCase();
            var filtered = q
                ? allRecipients.filter(function (f) {
                    return f.name.toLowerCase().includes(q) || f.email.toLowerCase().includes(q);
                })
                : allRecipients;
            renderRecipientTable(filtered);
        });

        /* ---- Send button: AJAX POST with CSRF token ---- */
        document.getElementById('modalSendBtn').addEventListener('click', function () {
            var sendBtn = this;
            sendBtn.disabled = true;
            sendBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                i18next.t('Sending…');

            var csrfToken = document.querySelector('#verifyEmailAllForm input[name="csrf_token"]').value;

            fetch(window.CRM.root + '/people/report/verify/email', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                    'X-CSRF-Token':     csrfToken,
                    'Content-Type':     'application/x-www-form-urlencoded',
                },
                body: 'csrf_token=' + encodeURIComponent(csrfToken),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                showInModalResult(data);
            })
            .catch(function () {
                showInModalResult({ status: 'error', message: i18next.t('Unexpected error. Please try again.') });
            });
        });

        function showInModalResult(data) {
            var banner    = document.getElementById('modalResultBanner');
            var footer    = document.getElementById('modalFooter');
            var isSuccess = (data.status === 'success');
            var isPartial = (data.status === 'partial_failure');
            var alertClass = isSuccess ? 'success' : (isPartial ? 'warning' : 'danger');
            var icon       = isSuccess ? 'ti-circle-check' : (isPartial ? 'ti-alert-triangle' : 'ti-alert-circle');

            banner.innerHTML =
                '<div class="alert alert-' + alertClass + ' alert-dismissible fade show mt-0 mb-0 rounded-0" ' +
                'data-cy="modal-result-banner">' +
                '<i class="ti ' + icon + ' me-2" aria-hidden="true"></i>' +
                escapeHtml(data.message || '') +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>';
            banner.classList.remove('d-none');

            // Replace footer with just a Close button
            footer.innerHTML =
                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' +
                '<i class="ti ti-x me-1" aria-hidden="true"></i>' +
                i18next.t('Close') +
                '</button>';

            // Reload the page on close to show updated alert
            document.getElementById('verifyEmailModal').addEventListener('hidden.bs.modal', function () {
                if (isSuccess) {
                    window.location.href = window.CRM.root + '/people/verify?AllPDFsEmailed=' + (data.sentCount || 0);
                } else if (data.status !== 'no_recipients') {
                    var q = 'EmailsError=1&reason=' + encodeURIComponent(data.status || 'unknown') +
                        '&sent=' + (data.sentCount || 0) + '&failed=' + (data.failedCount || 0);
                    window.location.href = window.CRM.root + '/people/verify?' + q;
                }
            }, { once: true });
        }

        function escapeHtml(str) {
            var d = document.createElement('div');
            d.appendChild(document.createTextNode(str));
            return d.innerHTML;
        }

        /* ---- DataTables ---- */
        var dataTableConfig = {
            ajax: {
                url: window.CRM.root + '/api/families/self-verify',
                dataSrc: 'families'
            },
            autoWidth: false,
            columns: [
                {
                    width: '15%',
                    title: i18next.t('Family Id'),
                    data: 'Family.Id',
                    searchable: false,
                    render: function (data) {
                        return '<a href=' + window.CRM.root + '/people/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    width: '30%',
                    title: i18next.t('Family'),
                    data: 'Family.FamilyString',
                    searchable: true
                },
                {
                    width: '35%',
                    title: i18next.t('Comments'),
                    data: 'Text',
                    searchable: true
                },
                {
                    width: '20%',
                    title: i18next.t('Date'),
                    data: 'DateEntered',
                    searchable: false,
                    render: function (data) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[2, "desc"]]
        };
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#families-complete").DataTable(dataTableConfig);

        dataTableConfig = {
            ajax: {
                url: window.CRM.root + '/api/families/pending-self-verify',
                dataSrc: 'families'
            },
            autoWidth: false,
            columns: [
                {
                    width: '20%',
                    title: i18next.t('Family Id'),
                    data: 'FamilyId',
                    searchable: false,
                    render: function (data) {
                        return '<a href=' + window.CRM.root + '/people/family/' + data + '>' + data + '</a>';
                    }
                },
                {
                    width: '50%',
                    title: i18next.t('Family'),
                    data: 'FamilyName',
                    searchable: true
                },
                {
                    width: '30%',
                    title: i18next.t('Valid Until'),
                    data: 'ValidUntilDate',
                    searchable: false,
                    render: function (data) {
                        return moment(data).format("MM-DD-YY");
                    }
                }
            ],
            order: [[0, "asc"]]
        };
        $.extend(dataTableConfig, window.CRM.plugin.dataTable);
        $("#families-pending").DataTable(dataTableConfig);
    }

    // Wait for locales to load before initializing
    $(document).ready(function () {
        window.CRM.onLocalesReady(initializePeopleVerify);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
