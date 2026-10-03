<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="card">
    <div class="list-group list-group-flush" id="reportCatalog">
        <?php foreach ($reports as $report) : ?>
        <a class="list-group-item list-group-item-action report-entry" href="<?= $sRootPath ?>/<?= InputUtils::escapeAttribute($report['url']) ?>">
            <div class="row align-items-center">
                <div class="col-auto"><i class="fa-solid <?= InputUtils::escapeAttribute($report['icon']) ?> fa-fw"></i></div>
                <div class="col">
                    <div class="fw-bold"><?= InputUtils::escapeHTML($report['title']) ?></div>
                    <div class="text-secondary"><?= InputUtils::escapeHTML($report['description']) ?></div>
                </div>
                <div class="col-auto"><span class="badge bg-secondary-lt"><?= InputUtils::escapeHTML($report['module']) ?></span></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
