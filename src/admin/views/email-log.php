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
            <span class="badge bg-danger-lt text-danger ms-3"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= sprintf(gettext('%d failed'), (int) $failedEmailCount) ?></span>
        <?php endif; ?>
        <span class="ms-auto text-body-secondary small"><?= gettext('Every email ChurchCRM sent, newest first. Open a person to see their full history.') ?></span>
    </div>
    <div class="card-body p-0">
        <?php
        $emailHistoryRows = $recentEmails['rows'];
        $emailHistoryShowTo = true;
        include SystemURLs::getDocumentRoot() . '/people/views/partials/email-history-table.php';
        ?>
    </div>
</div>
<?php include SystemURLs::getDocumentRoot() . '/people/views/partials/email-history-modal.php'; ?>
<?php if (!empty($queuedEmailCount)) : ?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/email-delete-queue.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<?php endif; ?>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
