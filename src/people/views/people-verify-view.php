<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ConfirmReportEmailResult;
use ChurchCRM\Utils\CSRFUtils;

$sPageTitle = gettext('People Verify Dashboard');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$bEmailEnabled = SystemConfig::isEmailEnabled();

/* -----------------------------------------------------------------------
 * Structured email result alert vars (set by the route handler)
 * --------------------------------------------------------------------- */
$emailErrorReason  = (string) ($emailErrorReason  ?? '');
$emailErrorSent    = (int) ($emailErrorSent   ?? 0);
$emailErrorFailed  = (int) ($emailErrorFailed ?? 0);
$emailSuccessCount = (int) ($emailSuccessCount ?? 0);

// Compute a PHP-side alert message using the value object’s central method
$emailAlertClass   = '';
$emailAlertMsg     = '';
$emailAlertIcon    = '';
if ($emailErrorReason !== '') {
    // Map reason → alert class and icon (display concerns stay in the view)
    switch ($emailErrorReason) {
        case ConfirmReportEmailResult::STATUS_NO_RECIPIENTS:
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-mail-off';
            break;
        case ConfirmReportEmailResult::STATUS_SMTP_FAILURE:
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-cloud-off';
            break;
        case ConfirmReportEmailResult::STATUS_EMAIL_DISABLED:
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-mail-off';
            break;
        case ConfirmReportEmailResult::STATUS_PARTIAL_FAILURE:
            $emailAlertClass = 'warning';
            $emailAlertIcon  = 'ti ti-alert-triangle';
            break;
        default:
            $emailAlertClass = 'danger';
            $emailAlertIcon  = 'ti ti-alert-circle';
    }
    // Message text delegated to the value object’s static method (single source of truth)
    $emailAlertMsg = ConfirmReportEmailResult::messageForStatus($emailErrorReason, $emailErrorSent, $emailErrorFailed);
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
            <button type="button" class="btn btn-outline-primary" id="verifyEmail" <?php if (!$bEmailEnabled) echo 'disabled'; ?> title="<?= htmlspecialchars(
                $bEmailEnabled ? gettext('Send email to families') : gettext('Email is not configured. Please configure SMTP settings in System Settings.'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
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
                    <span class="visually-hidden"><?= gettext('Loading…') ?></span>
                </div>
            </div>

            <!-- Fetch-error banner (shown inside the modal when the API call fails) -->
            <div id="previewFetchError" class="modal-body d-none">
                <!-- populated by fetchPreview() .catch() -->
            </div>

            <!-- Summary content (hidden until loaded) -->
            <div id="modalPreview" class="modal-body d-none">
                <p id="recipientCountText" data-cy="modal-recipient-count"></p>
                <p id="noEmailWarningText" class="d-none">
                    <!-- populated by JS when familiesWithoutEmail > 0 -->
                </p>
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
            // Clear fetch-error banner (does not touch #modalPreview children)
            document.getElementById('previewFetchError').classList.add('d-none');
            document.getElementById('previewFetchError').innerHTML = '';
            // Guard: showInModalResult() may have replaced #modalSendBtn
            var sendBtn = document.getElementById('modalSendBtn');
            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.innerHTML =
                    '<i class="ti ti-send me-1" aria-hidden="true"></i>' +
                    i18next.t('Send Emails');
            }
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
                // Inject error into dedicated container — does NOT replace #modalPreview
                // children so that a subsequent successful fetch (e.g. after a retry)
                // can still populate them correctly.
                var errContainer = document.getElementById('previewFetchError');
                errContainer.innerHTML =
                    '<div class="alert alert-danger">' +
                    '<i class="ti ti-alert-circle me-2" aria-hidden="true"></i>' +
                    i18next.t('Could not load email preview. Please close and try again.') +
                    '</div>';
                errContainer.classList.remove('d-none');
                document.getElementById('modalLoading').classList.add('d-none');
            });
        }

        function populatePreview(data) {
            var recipientCount   = data.recipientCount || 0;
            var noEmailCount     = (data.familiesWithoutEmail || []).length;

            // Recipient count sentence
            document.getElementById('recipientCountText').textContent = i18next.t(
                'You are about to send confirmation emails to {{count}} families.',
                { count: recipientCount }
            );

            // No-email warning with link to /v2/email/missing
            var noEmailEl = document.getElementById('noEmailWarningText');
            if (noEmailCount > 0) {
                var linkHref = window.CRM.root + '/v2/email/missing';
                var sentence = i18next.t(
                    '{{count}} families have no email address on file and will be skipped',
                    { count: noEmailCount }
                );
                // Build via DOM APIs so linkHref is never concatenated raw into innerHTML
                var link = document.createElement('a');
                link.href = linkHref;
                link.textContent = i18next.t('view families missing an email');
                noEmailEl.textContent = sentence + ' — ';
                noEmailEl.appendChild(link);
                noEmailEl.appendChild(document.createTextNode('.'));
                noEmailEl.classList.remove('d-none');
            } else {
                noEmailEl.classList.add('d-none');
            }

            // Show summary, hide loading, enable/disable send
            document.getElementById('modalLoading').classList.add('d-none');
            document.getElementById('modalPreview').classList.remove('d-none');
            var sendBtn = document.getElementById('modalSendBtn');
            if (sendBtn) {
                sendBtn.disabled = (recipientCount === 0);
            }
        }

        /* ---- Send button: AJAX POST with CSRF token ---- */
        document.getElementById('modalSendBtn').addEventListener('click', function () {
            var sendBtn = this;
            sendBtn.disabled = true;
            sendBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                i18next.t('Sending…');

            // Guard against missing CSRF form (partial render, CSP block, etc.)
            var csrfInput = document.querySelector('#verifyEmailAllForm input[name="csrf_token"]');
            if (!csrfInput) {
                showInModalResult({ status: 'error', message: i18next.t('Security token missing. Please refresh the page.') });
                return;
            }
            var csrfToken = csrfInput.value;

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
            .then(function (res) {
                if (!res.ok) { throw new Error('HTTP ' + res.status); }
                return res.json();
            })
            .then(function (data) {
                showInModalResult(data);
            })
            .catch(function () {
                showInModalResult({ status: 'error', message: i18next.t('Unexpected error. Please try again.') });
            });
        });

        // Tracks the pending hidden.bs.modal listener so stale closures from
        // previous calls to showInModalResult() can be cancelled before a new
        // one is registered.  Without this, multiple independent listeners
        // accumulate and the oldest one redirects based on stale result data.
        var _pendingHiddenListener = null;

        function showInModalResult(data) {
            var modal     = document.getElementById('verifyEmailModal');
            var banner    = document.getElementById('modalResultBanner');
            var footer    = document.getElementById('modalFooter');
            var isSuccess = (data.status === 'success');
            var isPartial = (data.status === 'partial_failure');
            var alertClass = isSuccess ? 'success' : (isPartial ? 'warning' : 'danger');
            var icon       = isSuccess ? 'ti-circle-check' : (isPartial ? 'ti-alert-triangle' : 'ti-alert-circle');

            // Cancel any listener registered by a previous call
            if (_pendingHiddenListener) {
                modal.removeEventListener('hidden.bs.modal', _pendingHiddenListener);
                _pendingHiddenListener = null;
            }

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
            _pendingHiddenListener = function () {
                _pendingHiddenListener = null;
                if (isSuccess) {
                    window.location.href = window.CRM.root + '/people/verify?AllPDFsEmailed=' + (data.sentCount || 0);
                } else if (data.status !== 'no_recipients') {
                    var q = 'EmailsError=1&reason=' + encodeURIComponent(data.status || 'unknown') +
                        '&sent=' + (data.sentCount || 0) + '&failed=' + (data.failedCount || 0);
                    window.location.href = window.CRM.root + '/people/verify?' + q;
                } else {
                    // no_recipients: reload to clear any stale error alert from a prior attempt
                    window.location.reload();
                }
            };
            modal.addEventListener('hidden.bs.modal', _pendingHiddenListener, { once: true });
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
