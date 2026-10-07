<?php

use ChurchCRM\dto\SystemURLs;

/**
 * Modal that shows one email from the history, rendered as it was sent.
 * Include once per page next to email-history-table.php; its behaviour is the
 * `email-history-modal` bundle (webpack/people/email-history-modal.ts).
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
                    <i class="fa-solid fa-lock me-1"></i><?= gettext('The content of this email is not stored. ChurchCRM keeps the text of messages written in the email composer and of volunteer emails only.') ?>
                </div>
                <iframe id="email-history-modal-body" class="w-100 border rounded d-none" sandbox="" title="<?= gettext('Email content') ?>" style="min-height: 420px; background: #fff;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
            </div>
        </div>
    </div>
</div>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-history-modal.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
