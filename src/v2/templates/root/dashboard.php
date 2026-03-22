<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Overview Card -->
<div class="card card-info card-outline mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-home"></i> <?= gettext('Overview') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon bg-secondary text-white rounded-circle" style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex-shrink:0;">
                                <i class="fa-solid fa-people-roof"></i>
                            </div>
                        </div>
                        <div class="h6 text-muted mb-2"><?= gettext('Families') ?></div>
                        <div class="h2 m-0" id="familyCountDashboard"><?= $dashboardCounts["families"] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon bg-success text-white rounded-circle" style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex-shrink:0;">
                                <i class="fa-solid fa-user"></i>
                            </div>
                        </div>
                        <div class="h6 text-muted mb-2"><?= gettext('People') ?></div>
                        <div class="h2 m-0" id="peopleStatsDashboard"><?= $dashboardCounts["People"] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon bg-primary text-white rounded-circle" style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex-shrink:0;">
                                <i class="fa-solid fa-users"></i>
                            </div>
                        </div>
                        <div class="h6 text-muted mb-2"><?= gettext('Groups') ?></div>
                        <div class="h2 m-0" id="groupsCountDashboard"><?= $dashboardCounts["Groups"] ?></div>
                    </div>
                </div>
            </div>
            <?php if ($sundaySchoolEnabled) { ?>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon bg-warning text-white rounded-circle" style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex-shrink:0;">
                                <i class="fa-solid fa-child"></i>
                            </div>
                        </div>
                        <div class="h6 text-muted mb-2"><?= gettext('Sunday School') ?></div>
                        <div class="h2 m-0" id="groupStatsSundaySchool"><?= $dashboardCounts["SundaySchool"] ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
            <?php if ($eventsEnabled) { ?>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="card card-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon bg-info text-white rounded-circle" style="display:flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;flex-shrink:0;">
                                <i class="fa-regular fa-calendar-check"></i>
                            </div>
                        </div>
                        <div class="h6 text-muted mb-2"><?= gettext('Check-ins') ?></div>
                        <div class="h2 m-0"><?= $dashboardCounts["events"] ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-3" id="birthdayCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-cake-candles me-2"></i><?= gettext('Birthdays') ?></h3>
            </div>
            <div class="card-body p-0">
                <p class="text-muted small px-3 pt-3 mb-2"><?= gettext('Next 7 days and past 7 days') ?></p>
                <table class="table table-striped table-hover mb-0" width="100%" id="PersonBirthdayDashboardItem"></table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3" id="anniversaryCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-heart me-2"></i><?= gettext('Anniversaries') ?></h3>
            </div>
            <div class="card-body p-0">
                <p class="text-muted small px-3 pt-3 mb-2"><?= gettext('Next 7 days and past 7 days') ?></p>
                <table class="table table-striped table-hover mb-0" width="100%" id="FamiliesWithAnniversariesDashboardItem"></table>
            </div>
        </div>
    </div>
</div>

<?php
if ($depositEnabled) { // If the user has Finance permissions, then let's display the deposit line chart
    ?>
<div class="row">
    <div class="col-12">
        <div class="card card-info mb-3" id="depositChartRow">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-circle-dollar-to-slot me-2"></i> <?= gettext('Deposit Tracking') ?></h3>
            </div>
            <div class="card-body">
                <div id="deposit-lineGraph" style="min-height: 300px;"></div>
            </div>
        </div>
    </div>
</div>
    <?php
}  //END IF block for Finance permissions to include HTML for Deposit Chart
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <div class="card-title"><h4><?= gettext('People') ?></h4></div>
            </div>
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs" id="people-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="latest-fam-tab" data-bs-toggle="tab" href="#latest-fam-pane" role="tab" aria-controls="latest-fam-pane" aria-selected="true">
                            <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Latest Families') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="updated-fam-tab" data-bs-toggle="tab" href="#updated-fam-pane" role="tab" aria-controls="updated-fam-pane" aria-selected="false">
                            <i class="fa-solid fa-pen mr-1"></i><?= gettext('Updated Families') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="latest-ppl-tab" data-bs-toggle="tab" href="#latest-ppl-pane" role="tab" aria-controls="#latest-ppl-pane" aria-selected="false">
                            <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Latest People') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="updated-ppl-tab" data-bs-toggle="tab" href="#updated-ppl-pane" role="tab" aria-controls="updated-ppl-pane" aria-selected="false">
                            <i class="fa-solid fa-pen mr-1"></i><?= gettext('Updated People') ?>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="people-tabs-content">
                    <div class="tab-pane fade show active" id="latest-fam-pane" role="tabpanel" aria-labelledby="latest-fam-tab">
                        <table class="table table-striped table-hover mb-0" width="100%" id="latestFamiliesDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="updated-fam-pane" role="tabpanel" aria-labelledby="updated-fam-tab">
                        <table class="table table-striped table-hover mb-0" width="100%" id="updatedFamiliesDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="latest-ppl-pane" role="tabpanel" aria-labelledby="latest-ppl-tab">
                        <table class="table table-striped table-hover mb-0" width="100%" id="latestPersonDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="updated-ppl-pane" role="tabpanel" aria-labelledby="updated-ppl-tab">
                        <table class="table table-striped table-hover mb-0" width="100%" id="updatedPersonDashboardItem"></table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/root-dashboard.min.js') ?>"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
