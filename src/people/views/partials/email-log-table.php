<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Utils\InputUtils;

/**
 * Email log card: a server-side DataTable over GET /api/email/log (webpack/people/email-log-table.js),
 * which also limits a sender without admin rights to the mail they queued.
 *
 * Expects $emailLog:
 *   facets          EmailLogService::getLogFacets() for the viewer
 *   pageUrl         root-relative path of the page, for the status badge links
 *   showSender      bool, offer the Sent by filter (administrators)
 *   canDeleteQueue  bool, offer Delete Queue
 */
$emailLogFacets = $emailLog['facets'];
$emailLogFailed = (int) $emailLogFacets['failed'];
$emailLogQueued = (int) $emailLogFacets['queued'];
$emailLogStatusUrl = SystemURLs::getRootPath() . $emailLog['pageUrl'] . '?status=';
?>
<div class="card mb-3" id="email-log-card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h3 class="card-title m-0"><i class="fa-solid fa-envelope-open-text me-2"></i><?= gettext('Email History') ?></h3>
        <?php if ($emailLogQueued > 0) : ?>
            <a href="<?= InputUtils::escapeAttribute($emailLogStatusUrl . EmailLogService::STATUS_QUEUED) ?>" class="badge bg-azure-lt text-azure ms-2" id="email-queued-count"
               data-email-log-status-link="<?= EmailLogService::STATUS_QUEUED ?>">
                <i class="fa-solid fa-hourglass-half me-1"></i><?= sprintf(gettext('%d queued'), $emailLogQueued) ?>
            </a>
            <?php if ($emailLog['canDeleteQueue']) : ?>
                <button type="button" class="btn btn-sm btn-outline-danger" id="email-delete-queue" data-queued-count="<?= $emailLogQueued ?>">
                    <i class="fa-solid fa-trash-can me-1"></i><?= gettext('Delete Queue') ?>
                </button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($emailLogFailed > 0) : ?>
            <a href="<?= InputUtils::escapeAttribute($emailLogStatusUrl . EmailLogService::STATUS_FAILED) ?>" class="badge bg-danger-lt text-danger ms-2" data-email-failed-badge
               data-email-log-status-link="<?= EmailLogService::STATUS_FAILED ?>"
               title="<?= gettext('Failed sends across all email history, including automated messages') ?>"
               aria-label="<?= sprintf(gettext('View %d failed sends across all email history'), $emailLogFailed) ?>">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><?= sprintf(gettext('%d failed (all time)'), $emailLogFailed) ?>
            </a>
        <?php endif; ?>
        <?php if (!$emailLog['showSender']) : ?>
            <span class="ms-auto text-body-secondary small"><?= gettext('Only the email you sent is shown.') ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body border-bottom py-3">
        <div class="row g-2">
            <div class="col-6 col-md-4">
                <select id="email-log-status" class="form-select" aria-label="<?= gettext('Status') ?>">
                    <option value=""><?= gettext('All Statuses') ?></option>
                    <?php foreach ([EmailLogService::STATUS_QUEUED, EmailLogService::STATUS_SENDING, EmailLogService::STATUS_SENT, EmailLogService::STATUS_FAILED, EmailLogService::STATUS_SKIPPED] as $status) : ?>
                        <option value="<?= $status ?>"><?= InputUtils::escapeHTML(EmailLogService::statusLabel($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select id="email-log-kind" class="form-select" aria-label="<?= gettext('Type') ?>">
                    <option value=""><?= gettext('All Types') ?></option>
                    <?php foreach ($emailLogFacets['kinds'] as $kind => $label) : ?>
                        <option value="<?= InputUtils::escapeAttribute($kind) ?>"><?= InputUtils::escapeHTML($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($emailLog['showSender']) : ?>
                <div class="col-12 col-md-4">
                    <select id="email-log-sent-by" class="form-select" aria-label="<?= gettext('Sent by') ?>">
                        <option value=""><?= gettext('Everyone') ?></option>
                        <option value="<?= EmailLogService::SENT_BY_AUTOMATIC ?>"><?= gettext('Automatic') ?></option>
                        <?php foreach ($emailLogFacets['senders'] as $userId => $name) : ?>
                            <option value="<?= (int) $userId ?>"><?= InputUtils::escapeHTML($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover w-100 mb-0" id="email-log-table" data-email-history-table>
            <thead>
                <tr>
                    <th class="w-1 text-nowrap"><?= gettext('Date') ?></th>
                    <th class="w-1 text-nowrap"><?= gettext('Type') ?></th>
                    <th><?= gettext('Subject') ?></th>
                    <th><?= gettext('To') ?></th>
                    <th class="w-1 text-nowrap"><?= gettext('Status') ?></th>
                    <th class="w-1 text-nowrap"><?= gettext('Sent by') ?></th>
                </tr>
            </thead>
        </table>
    </div>
</div>
<?php include __DIR__ . '/email-history-modal.php'; ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-log-table.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php if ($emailLog['canDeleteQueue'] && $emailLogQueued > 0) : ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-delete-queue.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>
