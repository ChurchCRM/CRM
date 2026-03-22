<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-sm-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-roof"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium" id="familyCountDashboard"><?= $dashboardCounts["families"] ?></div>
                        <div class="text-muted"><?= gettext('Families') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-user"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium" id="peopleStatsDashboard"><?= $dashboardCounts["People"] ?></div>
                        <div class="text-muted"><?= gettext('People') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium" id="groupsCountDashboard"><?= $dashboardCounts["Groups"] ?></div>
                        <div class="text-muted"><?= gettext('Groups') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ($sundaySchoolEnabled) { ?>
    <div class="col-sm-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-child"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium" id="groupStatsSundaySchool"><?= $dashboardCounts["SundaySchool"] ?></div>
                        <div class="text-muted"><?= gettext('Sunday School') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
    <?php if ($eventsEnabled) { ?>
    <div class="col-sm-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-regular fa-calendar-check"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $dashboardCounts["events"] ?></div>
                        <div class="text-muted"><?= gettext('Check-ins') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
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
                            <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Latest Families') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="updated-fam-tab" data-bs-toggle="tab" href="#updated-fam-pane" role="tab" aria-controls="updated-fam-pane" aria-selected="false">
                            <i class="fa-solid fa-pen me-1"></i><?= gettext('Updated Families') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="latest-ppl-tab" data-bs-toggle="tab" href="#latest-ppl-pane" role="tab" aria-controls="#latest-ppl-pane" aria-selected="false">
                            <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Latest People') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="updated-ppl-tab" data-bs-toggle="tab" href="#updated-ppl-pane" role="tab" aria-controls="updated-ppl-pane" aria-selected="false">
                            <i class="fa-solid fa-pen me-1"></i><?= gettext('Updated People') ?>
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
