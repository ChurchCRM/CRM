<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';

$steps[] = [
    'done'  => false,
    'label' => gettext('Localization & Formats'),
    'desc'  => gettext('Language, time zone, date & phone formats'),
    'link'  => SystemURLs::getRootPath() . '/admin/system/localization',
];
?>

<div class="container-fluid">
    <div class="row row-cards">
        <div class="col-md-4">
            <div class="card h-100 <?= $demoLoaded ? 'border-warning' : 'border-success' ?>" id="setupChoiceDemo">
                <div class="card-body d-flex flex-column">
                    <?php if ($demoLoaded): ?>
                        <h3 class="card-title"><?= gettext("You're exploring demo data") ?></h3>
                        <p class="text-secondary"><?= gettext('Everything here is sample data. When you are ready to use ChurchCRM for your church, start fresh: it erases the demo data and every setting so you can begin with your own.') ?></p>
                        <div class="btn-list mt-auto">
                            <a href="<?= InputUtils::escapeAttribute($dashboardUrl) ?>" class="btn btn-primary" id="keepExploring"><?= gettext('Keep exploring') ?></a>
                            <?php if ($windowOpen): ?>
                                <button type="button" class="btn btn-outline-danger js-start-fresh"><?= gettext('Start Fresh') ?></button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <h3 class="card-title"><?= gettext('Explore with Demo Data') ?></h3>
                        <p class="text-secondary"><?= gettext('Load sample families, people, groups, events, volunteer ministries and giving records. The safest way to learn ChurchCRM before committing your real data.') ?></p>
                        <div class="mt-auto">
                            <button type="button" id="importDemoDataSetup" class="btn btn-success"
                                    data-success-url="<?= InputUtils::escapeAttribute($dashboardUrl) ?>"
                                    data-has-existing-data="<?= $hasExistingData ? 'true' : 'false' ?>"
                                    data-person-count="<?= (int) $personCount ?>"
                                    data-family-count="<?= (int) $familyCount ?>">
                                <?= gettext('Load demo data') ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100" id="setupChoiceChurch">
                <div class="card-body d-flex flex-column">
                    <h3 class="card-title"><?= gettext('Set Up Your Church') ?></h3>
                    <?php if ($demoLoaded): ?>
                        <p class="text-secondary mb-0"><?= gettext('Start fresh first, then set up ChurchCRM with your own church information.') ?></p>
                    <?php elseif ($needsChurchSetup): ?>
                        <p class="text-secondary"><?= gettext('Enter your church information, add your people, and invite your team.') ?></p>
                        <div class="mt-auto">
                            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/church-info" class="btn btn-primary" id="startChurchSetup"><?= gettext('Start setup') ?></a>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary mb-0"><?= gettext('Your church information is saved. Work through the steps below.') ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100" id="setupChoiceRestore">
                <div class="card-body d-flex flex-column">
                    <h3 class="card-title"><?= gettext('Restore a Backup') ?></h3>
                    <p class="text-secondary"><?= gettext('Moving from another ChurchCRM server? Restore a backup made there. This replaces everything on this install.') ?></p>
                    <div class="mt-auto">
                        <a href="<?= SystemURLs::getRootPath() ?>/admin/system/restore" class="btn btn-outline-secondary"><?= gettext('Restore') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$demoLoaded && !$needsChurchSetup): ?>
    <div class="card mt-3" id="setupSteps">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Next Steps') ?></h3>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($steps as $step): ?>
            <div class="list-group-item d-flex flex-wrap align-items-center gap-2" data-step-done="<?= $step['done'] ? 'true' : 'false' ?>">
                <i class="<?= $step['done'] ? 'fa-solid fa-circle-check text-success' : 'fa-regular fa-circle text-secondary' ?>"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= $step['label'] ?></div>
                    <div class="text-secondary small"><?= $step['desc'] ?></div>
                </div>
                <?php if (!$step['done']): ?>
                    <a href="<?= $step['link'] ?>" <?= str_starts_with($step['link'], 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?> class="btn btn-sm btn-outline-primary"><?= gettext('Set up') ?></a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/importDemoData.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-setup.min.js') ?>"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
