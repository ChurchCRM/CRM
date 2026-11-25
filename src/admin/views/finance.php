<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <?php foreach ($listLinks as [$label, $path, $icon]) : ?>
            <div class="col-6 col-lg-4 mb-3">
                <a href="<?= InputUtils::escapeAttribute($sRootPath . $path) ?>" class="card card-sm text-decoration-none h-100">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <span class="bg-secondary text-white avatar rounded-circle">
                                    <i class="fa-solid <?= InputUtils::escapeAttribute($icon) ?> icon"></i>
                                </span>
                            </div>
                            <div class="col fw-medium text-body"><?= InputUtils::escapeHTML($label) ?></div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
