<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<?php if (!empty($recentEmails)) : ?>
<div class="card mt-3">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h3 class="card-title m-0"><i class="fa-solid fa-envelope-open-text me-2"></i><?= gettext('Recent Sends') ?>
            <span class="badge bg-secondary-lt text-secondary ms-2"><?= (int) $recentEmails['total'] ?></span>
        </h3>
        <?php if (!empty($queuedEmailCount)) : ?>
            <span class="badge bg-azure-lt text-azure ms-3" id="email-queued-count"><i class="fa-solid fa-hourglass-half me-1"></i><?= sprintf(gettext('%d queued'), (int) $queuedEmailCount) ?></span>
            <button type="button" class="btn btn-sm btn-outline-danger" id="email-delete-queue" data-queued-count="<?= (int) $queuedEmailCount ?>">
                <i class="fa-solid fa-trash-can me-1"></i><?= gettext('Delete Queue') ?>
            </button>
        <?php endif; ?>
        <?php if (!empty($failedEmailCount)) : ?>
            <a href="<?= $sRootPath ?>/admin/email/log?status=failed" class="badge bg-danger-lt text-danger ms-3" data-email-failed-badge
               title="<?= gettext('Failed sends across all email history, including automated messages') ?>"
               aria-label="<?= sprintf(gettext('View %d failed sends across all email history'), (int) $failedEmailCount) ?>">
                <i class="fa-solid fa-triangle-exclamation me-1"></i><?= sprintf(gettext('%d failed (all time)'), (int) $failedEmailCount) ?>
            </a>
        <?php endif; ?>
        <?php if (!empty($failedOnly)) : ?>
            <a href="<?= $sRootPath ?>/admin/email/log" class="ms-auto small"><?= gettext('Show all recent sends') ?></a>
        <?php else : ?>
            <span class="ms-auto text-body-secondary small"><?= gettext('The table shows only the latest 20 sends. Open a person to see their full history.') ?></span>
        <?php endif; ?>
    </div>
    <?php if (!empty($failedOnly)) : ?>
        <p class="text-body-secondary small px-3 pt-3 mb-0" data-email-failed-filter>
            <?= gettext('Showing failed sends across all email history, including automated messages. Select a subject for details.') ?>
        </p>
    <?php endif; ?>
    <div class="card-body p-0">
        <?php
        $emailHistoryRows = $recentEmails['rows'];
        $emailHistoryShowTo = true;
        $emailHistoryShowError = !empty($failedOnly);
        $emailHistoryLinkPerson = !empty($failedOnly);
        include SystemURLs::getDocumentRoot() . '/people/views/partials/email-history-table.php';
        ?>
    </div>
    <?php if (!empty($failedOnly) && $recentEmails['pages'] > 1) : ?>
        <?php $currentPage = (int) $recentEmails['page']; ?>
        <nav class="card-footer d-flex align-items-center justify-content-between" aria-label="<?= gettext('Failed email history pages') ?>">
            <span class="text-body-secondary small"><?= sprintf(gettext('Page %d of %d'), $currentPage, (int) $recentEmails['pages']) ?></span>
            <div class="d-flex gap-2">
                <?php if ($currentPage > 1) : ?>
                    <a class="btn btn-sm" href="<?= $sRootPath ?>/admin/email/log?status=failed&amp;page=<?= $currentPage - 1 ?>"><?= gettext('Previous') ?></a>
                <?php endif; ?>
                <?php if ($currentPage < (int) $recentEmails['pages']) : ?>
                    <a class="btn btn-sm" href="<?= $sRootPath ?>/admin/email/log?status=failed&amp;page=<?= $currentPage + 1 ?>"><?= gettext('Next') ?></a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</div>
<?php include SystemURLs::getDocumentRoot() . '/people/views/partials/email-history-modal.php'; ?>
<?php if (!empty($queuedEmailCount)) : ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-delete-queue.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
