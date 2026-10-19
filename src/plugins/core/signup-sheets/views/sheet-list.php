<?php

/**
 * Signup Sheets — list of all sheets.
 *
 * @var array<int, array<string, mixed>> $sheets
 * @var bool                             $allowPublic
 * @var bool                             $canEdit
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$statusBadges = [
    'draft' => 'bg-secondary',
    'open' => 'bg-success',
    'closed' => 'bg-dark',
];
?>

<div class="row mb-3">
    <div class="col-12 d-flex align-items-center">
        <nav aria-label="breadcrumb" class="flex-grow-1">
            <ol class="breadcrumb mb-0 bg-light">
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><i class="fa-solid fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="<?= SystemURLs::getRootPath() ?>/event/dashboard"><?= gettext('Events') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= gettext('Signup Sheets') ?></li>
            </ol>
        </nav>
        <?php if ($canEdit) : ?>
            <a href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets/new" class="btn btn-primary btn-sm ms-2">
                <i class="fa-solid fa-plus fa-fw"></i> <?= gettext('New Sheet') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($sheets)) : ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fa-solid fa-clipboard-list fa-3x text-body-secondary mb-3"></i>
            <h3><?= gettext('No signup sheets yet') ?></h3>
            <p class="text-body-secondary">
                <?= gettext('A signup sheet lists what an event needs — dishes to bring, roles to serve, shifts to cover — and lets people claim those slots themselves.') ?>
            </p>
            <?php if ($canEdit) : ?>
                <a href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets/new" class="btn btn-primary">
                    <i class="fa-solid fa-plus fa-fw"></i> <?= gettext('Create your first sheet') ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else : ?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Signup Sheets') ?></h3>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= gettext('Sheet') ?></th>
                        <th><?= gettext('When') ?></th>
                        <th><?= gettext('Status') ?></th>
                        <th><?= gettext('Filled') ?></th>
                        <th><?= gettext('Sharing') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sheets as $sheet) : ?>
                        <?php
                        $capacity = (int) $sheet['capacity_total'];
                        $claimed = (int) $sheet['claimed_total'];
                        $percent = $capacity > 0 ? min(100, (int) round(($claimed / $capacity) * 100)) : 0;
                        $status = (string) $sheet['shs_status'];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= SystemURLs::getRootPath() ?>/plugins/signup-sheets/<?= (int) $sheet['shs_ID'] ?>">
                                    <strong><?= InputUtils::escapeHTML($sheet['shs_title']) ?></strong>
                                </a>
                                <?php if (!empty($sheet['event_title'])) : ?>
                                    <div class="small text-body-secondary">
                                        <i class="fa-solid fa-link fa-fw"></i><?= InputUtils::escapeHTML($sheet['event_title']) ?>
                                    </div>
                                <?php elseif (!empty($sheet['shs_location'])) : ?>
                                    <div class="small text-body-secondary">
                                        <i class="fa-solid fa-location-dot fa-fw"></i><?= InputUtils::escapeHTML($sheet['shs_location']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($sheet['shs_starts'])) : ?>
                                    <?= date('M j, Y g:i a', strtotime((string) $sheet['shs_starts'])) ?>
                                <?php else : ?>
                                    <span class="text-body-secondary">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $statusBadges[$status] ?? 'bg-secondary' ?>">
                                    <?= InputUtils::escapeHTML(ucfirst($status)) ?>
                                </span>
                            </td>
                            <td class="signup-progress-column">
                                <?php if ($capacity > 0) : ?>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2 signup-progress-track">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                 style="width: <?= $percent ?>%"
                                                 aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="small text-body-secondary text-nowrap"><?= $claimed ?> / <?= $capacity ?></span>
                                    </div>
                                <?php else : ?>
                                    <span class="small text-body-secondary"><?= $claimed ?> <?= gettext('signed up') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($sheet['shs_is_public']) && $allowPublic) : ?>
                                    <span class="badge bg-info"><i class="fa-solid fa-globe fa-fw"></i><?= gettext('Public link') ?></span>
                                <?php else : ?>
                                    <span class="badge bg-light text-dark"><i class="fa-solid fa-lock fa-fw"></i><?= gettext('CRM only') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
