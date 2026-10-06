<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$page = (int) $emailHistory['page'];
$pages = (int) $emailHistory['pages'];
$baseUrl = $sRootPath . '/people/view/' . (int) $iPersonID . '/emails?page=';
?>

<div class="card">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title m-0"><i class="fa-solid fa-envelope-open-text me-1"></i> <?= gettext('Email History') ?>
            <span class="badge bg-secondary-lt text-secondary ms-2"><?= (int) $emailHistory['total'] ?></span>
        </h3>
        <span class="ms-auto text-body-secondary small"><?= gettext('Newest first') ?></span>
    </div>
    <div class="card-body p-0">
        <?php
        $emailHistoryRows = $emailHistory['rows'];
        $emailHistoryShowTo = true;
        include __DIR__ . '/partials/email-history-table.php';
        ?>
    </div>
    <?php if ($pages > 1) : ?>
    <div class="card-footer d-flex align-items-center">
        <p class="m-0 text-body-secondary small">
            <?= sprintf(gettext('Page %d of %d'), $page, $pages) ?>
        </p>
        <ul class="pagination m-0 ms-auto" id="email-history-pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $baseUrl . ($page - 1) ?>" rel="prev"><i class="fa-solid fa-chevron-left"></i><span class="d-none d-sm-inline ms-1"><?= gettext('Prev') ?></span></a>
            </li>
            <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) : ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $baseUrl . $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $baseUrl . ($page + 1) ?>" rel="next"><span class="d-none d-sm-inline me-1"><?= gettext('Next') ?></span><i class="fa-solid fa-chevron-right"></i></a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/partials/email-history-modal.php'; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
