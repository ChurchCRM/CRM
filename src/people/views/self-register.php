<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="alert <?= $selfRegEnabled ? 'alert-success' : 'alert-warning' ?> d-flex flex-wrap gap-2 align-items-center justify-content-between" role="status">
    <div>
        <i class="fa-solid <?= $selfRegEnabled ? 'fa-circle-check' : 'fa-circle-pause' ?> me-2"></i>
        <strong><?= $selfRegEnabled ? gettext('Self-registration is enabled') : gettext('Self-registration is disabled') ?></strong>
        <span class="ms-1"><?= $selfRegEnabled
            ? gettext('Visitors can sign up on your public registration form.')
            : gettext('The public registration form is turned off. Existing registrations are listed below.') ?></span>
    </div>
    <?php if ($isAdmin): ?>
        <a href="<?= $sRootPath ?>/admin/people" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-sliders me-1"></i><?= gettext('Change in People Settings') ?>
        </a>
    <?php endif; ?>
</div>

<div class="row mb-3">
    <div class="col-12 col-md-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-clock icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="selfRegPending"><?= $pendingCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Pending review') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="selfRegApproved"><?= $approvedCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Approved') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="selfRegTotal"><?= $pendingCount + $approvedCount ?></div>
                        <div class="text-body-secondary"><?= gettext('Total registrations') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Pending Registrations') ?></h3>
            </div>
            <div class="card-body">
                <p class="text-body-secondary">
                    <?= gettext('Registrations awaiting review from your public registration form. Entries with no email or phone are flagged — verify contact info before following up.') ?>
                </p>
                <div id="bulkBar" class="d-none mb-3">
                    <button type="button" id="approveSelected" class="btn btn-success" disabled>
                        <i class="fa-solid fa-check me-1"></i><?= gettext('Approve selected') ?> (<span id="selectedCount">0</span>)
                    </button>
                </div>
                <div style="overflow-x: clip; overflow-y: visible;">
                    <table id="selfRegistrations" class="table table-bordered data-table">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-self-register.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
