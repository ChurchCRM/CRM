<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Stat Cards Row -->
<div class="row row-cards mb-3 g-2">
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/people/family" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-roof icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="familyCountDashboard"><?= $dashboardCounts["families"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Families') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/people/list" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-group icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="peopleStatsDashboard"><?= $dashboardCounts["People"] ?></div>
                        <div class="text-body-secondary"><?= gettext('People') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/groups/dashboard" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="groupsCountDashboard"><?= $dashboardCounts["Groups"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Groups') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <?php if ($sundaySchoolEnabled): ?>
        <a href="<?= $sRootPath ?>/groups/sundayschool" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-child icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="groupStatsSundaySchool"><?= $dashboardCounts["SundaySchool"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Sunday School') ?></div>
                    </div>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="card card-sm opacity-50">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-child icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body-secondary"><?= gettext('No Sunday School') ?></div>
                        <div class="text-body-secondary small"><?= gettext('Disabled in settings') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <?php if ($eventsEnabled): ?>
        <a href="<?= $sRootPath ?>/event/dashboard" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-regular fa-calendar-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $dashboardCounts["events"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Check-ins') ?></div>
                    </div>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="card card-sm opacity-50">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-regular fa-calendar-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body-secondary"><?= gettext('No Check-ins') ?></div>
                        <div class="text-body-secondary small"><?= gettext('Disabled in settings') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($eventsEnabled) { ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card" id="todayEventsCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-calendar-day me-2"></i><?= gettext("Today's Events") ?></h3>
                <div class="ms-auto">
                    <a href="<?= SystemURLs::getRootPath() ?>/event/checkin" class="btn btn-sm btn-outline-primary">
                        <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Check-in') ?>
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="todayEventsDashboardItem"></table>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<div class="row">
    <!-- People card — primary content (2/3 width) -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">
                    <i class="ti ti-users me-2 text-body-secondary"></i><?= gettext('People') ?>
                </div>
                <div class="card-options ms-auto">
                    <ul class="nav nav-tabs card-header-tabs" id="people-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="latest-fam-tab" data-bs-toggle="tab" href="#latest-fam-pane" role="tab" aria-controls="latest-fam-pane" aria-selected="true">
                                <i class="ti ti-home-plus me-1"></i><span class="d-none d-xl-inline"><?= gettext('Latest Families') ?></span><span class="d-xl-none"><?= gettext('New') ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="updated-fam-tab" data-bs-toggle="tab" href="#updated-fam-pane" role="tab" aria-controls="updated-fam-pane" aria-selected="false">
                                <i class="ti ti-home-edit me-1"></i><span class="d-none d-xl-inline"><?= gettext('Updated Families') ?></span><span class="d-xl-none"><?= gettext('Updated') ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="latest-ppl-tab" data-bs-toggle="tab" href="#latest-ppl-pane" role="tab" aria-controls="latest-ppl-pane" aria-selected="false">
                                <i class="ti ti-user-plus me-1"></i><span class="d-none d-xl-inline"><?= gettext('Latest People') ?></span><span class="d-xl-none"><?= gettext('New') ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="updated-ppl-tab" data-bs-toggle="tab" href="#updated-ppl-pane" role="tab" aria-controls="updated-ppl-pane" aria-selected="false">
                                <i class="ti ti-user-edit me-1"></i><span class="d-none d-xl-inline"><?= gettext('Updated People') ?></span><span class="d-xl-none"><?= gettext('Updated') ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="people-tabs-content">
                    <div class="tab-pane fade show active" id="latest-fam-pane" role="tabpanel" aria-labelledby="latest-fam-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="latestFamiliesDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="updated-fam-pane" role="tabpanel" aria-labelledby="updated-fam-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="updatedFamiliesDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="latest-ppl-pane" role="tabpanel" aria-labelledby="latest-ppl-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="latestPersonDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="updated-ppl-pane" role="tabpanel" aria-labelledby="updated-ppl-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="updatedPersonDashboardItem"></table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Birthdays & Anniversaries — sidebar (1/3 width) -->
    <div class="col-lg-4">
        <div class="card mb-3" id="birthdayCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-cake-candles me-2"></i><?= gettext('Birthdays') ?></h3>
            </div>
            <div class="card-body p-0">
                <p class="text-body-secondary small px-3 pt-3 mb-2"><?= gettext('Past & next 7 days') ?></p>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" width="100%" id="PersonBirthdayDashboardItem"></table>
                </div>
            </div>
        </div>
        <div class="card mb-3" id="anniversaryCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-heart me-2"></i><?= gettext('Anniversaries') ?></h3>
            </div>
            <div class="card-body p-0">
                <p class="text-body-secondary small px-3 pt-3 mb-2"><?= gettext('Past & next 7 days') ?></p>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" width="100%" id="FamiliesWithAnniversariesDashboardItem"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($depositEnabled) { ?>
<div class="row">
    <div class="col-12">
        <div class="card mb-3" id="depositChartRow">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-circle-dollar-to-slot me-2"></i> <?= gettext('Deposit Tracking') ?></h3>
            </div>
            <div class="card-body">
                <div id="deposit-lineGraph" style="min-height: 300px;"></div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/root-dashboard.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
