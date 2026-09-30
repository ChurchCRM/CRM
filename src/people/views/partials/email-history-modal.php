<?php

use ChurchCRM\dto\SystemURLs;

/**
 * Modal that shows one email from the history, rendered as it was sent.
 * Include once per page next to email-history-table.php. Clicks on any
 * `.email-history-open[data-email-log-id]` fetch GET /api/email/log/{id} and fill it.
 * The body is shown in a sandboxed iframe so stored HTML cannot run scripts or
 * reach the page.
 */
?>
<div class="modal fade" id="email-history-modal" tabindex="-1" aria-labelledby="email-history-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="email-history-modal-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-3 small" id="email-history-modal-meta">
                    <dt class="col-sm-2"><?= gettext('To') ?></dt><dd class="col-sm-10" data-field="address"></dd>
                    <dt class="col-sm-2"><?= gettext('Date') ?></dt><dd class="col-sm-10" data-field="dateSent"></dd>
                    <dt class="col-sm-2"><?= gettext('Type') ?></dt><dd class="col-sm-10" data-field="kindLabel"></dd>
                    <dt class="col-sm-2"><?= gettext('Status') ?></dt><dd class="col-sm-10" data-field="status"></dd>
                    <dt class="col-sm-2"><?= gettext('Sent by') ?></dt><dd class="col-sm-10" data-field="sentBy"></dd>
                </dl>
                <div class="alert alert-danger d-none" id="email-history-modal-error"></div>
                <div class="alert alert-secondary d-none" id="email-history-modal-nobody">
                    <i class="fa-solid fa-lock me-1"></i><?= gettext('The content of this email is not stored: it contained a password or a one-time link.') ?>
                </div>
                <iframe id="email-history-modal-body" class="w-100 border rounded d-none" sandbox="" title="<?= gettext('Email content') ?>" style="min-height: 420px; background: #fff;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    var modalEl = document.getElementById('email-history-modal');
    if (!modalEl) return;
    var root = (window.CRM && window.CRM.root) || '';
    var statusText = {
        sent: <?= json_encode(gettext('Sent')) ?>,
        failed: <?= json_encode(gettext('Failed')) ?>,
        skipped: <?= json_encode(gettext('Skipped')) ?>
    };
    var automatic = <?= json_encode(gettext('Automatic')) ?>;
    var loadFailed = <?= json_encode(gettext('Could not load this email.')) ?>;

    function setField(name, value) {
        var el = modalEl.querySelector('[data-field="' + name + '"]');
        if (el) el.textContent = value == null || value === '' ? '—' : String(value);
    }

    function show(id) {
        var title = document.getElementById('email-history-modal-title');
        var errorEl = document.getElementById('email-history-modal-error');
        var noBodyEl = document.getElementById('email-history-modal-nobody');
        var frame = document.getElementById('email-history-modal-body');
        title.textContent = '…';
        errorEl.classList.add('d-none');
        noBodyEl.classList.add('d-none');
        frame.classList.add('d-none');
        frame.removeAttribute('srcdoc');
        ['address', 'dateSent', 'kindLabel', 'status', 'sentBy'].forEach(function (f) { setField(f, ''); });
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        fetch(root + '/api/email/log/' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.ok ? res.json() : Promise.reject(new Error(String(res.status))); })
            .then(function (row) {
                title.textContent = row.subject || '';
                setField('address', row.address);
                setField('dateSent', row.dateSent);
                setField('kindLabel', row.kindLabel);
                setField('status', (statusText[row.status] || row.status) + (row.error ? ' — ' + row.error : ''));
                setField('sentBy', row.sentBy || automatic);
                if (row.body) {
                    frame.classList.remove('d-none');
                    frame.setAttribute('srcdoc', row.body);
                } else {
                    noBodyEl.classList.remove('d-none');
                }
            })
            .catch(function () {
                errorEl.textContent = loadFailed;
                errorEl.classList.remove('d-none');
            });
    }

    document.addEventListener('click', function (e) {
        var link = e.target && e.target.closest ? e.target.closest('.email-history-open[data-email-log-id]') : null;
        if (!link) return;
        e.preventDefault();
        show(link.getAttribute('data-email-log-id'));
    });
})();
</script>
