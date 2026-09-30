<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>
<div class="card">
    <div class="list-group list-group-flush" id="peopleReports">
        <?php foreach ($reports as $slug => $report) : ?>
        <div class="list-group-item">
            <div class="row align-items-center">
                <div class="col">
                    <a id="report-<?= InputUtils::escapeAttribute($slug) ?>"
                       href="<?= $sRootPath ?>/v2/reports/people/<?= InputUtils::escapeAttribute($slug) ?>"
                       class="fw-bold text-body">
                        <?= InputUtils::escapeHTML($report['name']) ?>
                    </a>
                    <div class="text-secondary"><?= InputUtils::escapeHTML($report['description']) ?></div>
                </div>
                <div class="col-auto">
                    <a href="<?= $sRootPath ?>/v2/reports/people/<?= InputUtils::escapeAttribute($slug) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-play me-1"></i><?= gettext('Run') ?>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
