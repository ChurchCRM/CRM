<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Calculate data quality status
$hasDataQualityIssues = $genderDataCheckCount > 0 || $roleDataCheckCount > 0 ||
                        $classificationDataCheckCount > 0 || $familyCoordinatesCheckCount > 0;
?>

<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-2 col-xs-4">
        <!-- small box -->
        <div class="small-box bg-olive">
            <div class="inner">
                <h3 id="familyCountDashboard">
                    <?= $dashboardCounts["families"] ?>
                </h3>
                <p>
                    <?= gettext('Families') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-user-friends"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/family" class="small-box-footer">
                <?= gettext('See all Families') ?> <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-2 col-xs-4">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3 id="peopleStatsDashboard">
                    <?= $dashboardCounts["People"] ?>
                </h3>
                <p>
                    <?= gettext('People') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-user"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="small-box-footer">
                <?= gettext('See All People') ?> <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-2 col-xs-4">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3 id="groupsCountDashboard">
                    <?= $dashboardCounts["Groups"] ?>
                </h3>
                <p>
                    <?= gettext('Groups') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/GroupList.php" class="small-box-footer">
                <?= gettext('More info') ?>  <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <?php if ($sundaySchoolEnabled) {
        ?>
        <div class="col-lg-2 col-xs-4">
            <!-- small box -->
            <div class="small-box bg-orange">
                <div class="inner">
                    <h3 id="groupStatsSundaySchool">
                        <?= $dashboardCounts["SundaySchool"] ?>
                    </h3>
                    <p>
                        <?= gettext('Sunday School Classes') ?>
                    </p>
                </div>
                <div class="icon">
                    <i class="fa-solid fa-child"></i>
                </div>
                <a href="<?= SystemURLs::getRootPath() ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
                    <?= gettext('More info') ?> <i class="fa-solid fa-arrow-circle-right"></i>
                </a>
            </div>
        </div><!-- ./col -->
        <?php
    }
    if ($eventsEnabled) {
        ?>
    <div class="col-lg-2 col-xs-4">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>
                    <?= $dashboardCounts["events"] ?>
                </h3>
                <p>
                    <?= gettext('Attendees Checked In') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/ListEvents.php" class="small-box-footer">
                <?= gettext('More info') ?>  <i class="fa-solid fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
        <?php
    } ?>
</div><!-- /.row -->

<?php if ($hasDataQualityIssues): ?>
<!-- Row 2: Data Quality Alert -->
<div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
    <div class="d-flex align-items-center">
        <div class="mr-3">
            <i class="fa-solid fa-clipboard-check fa-2x"></i>
        </div>
        <div class="flex-grow-1">
            <strong><?= gettext('Data Quality:') ?></strong>
            <?php 
            $issues = [];
            if ($genderDataCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/v2/people?Gender=0" class="alert-link">' . 
                            sprintf(gettext('%d missing gender'), $genderDataCheckCount) . '</a>';
            }
            if ($roleDataCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/v2/people?FamilyRole=0" class="alert-link">' . 
                            sprintf(gettext('%d missing role'), $roleDataCheckCount) . '</a>';
            }
            if ($classificationDataCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/v2/people?Classification=0" class="alert-link">' . 
                            sprintf(gettext('%d missing classification'), $classificationDataCheckCount) . '</a>';
            }
            if ($familyCoordinatesCheckCount > 0) {
                $issues[] = '<a href="' . SystemURLs::getRootPath() . '/GeoPage.php" class="alert-link">' . 
                            sprintf(gettext('%d families missing coordinates'), $familyCoordinatesCheckCount) . '</a>';
            }
            echo implode(' · ', $issues);
            ?>
        </div>
    </div>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<div class="row">
    <div class="card col-md-6" id="birthdayCard">
        <div class="card-body">
            <h3><i class="fa-solid fa-cake-candles mr-2"></i><?= gettext('Upcoming Birthdays') ?></h3>
            <p class="text-muted small mb-2"><?= gettext('Next 7 days and past 7 days') ?></p>
            <table class="table table-striped table-hover" width="100%" id="PersonBirthdayDashboardItem"></table>
        </div>
    </div>
    <div class="card col-md-6" id="anniversaryCard">
        <div class="card-body">
            <h3><i class="fa-solid fa-heart mr-2"></i><?= gettext('Upcoming Anniversaries') ?></h3>
            <p class="text-muted small mb-2"><?= gettext('Next 7 days and past 7 days') ?></p>
            <table class="table table-striped table-hover" width="100%" id="FamiliesWithAnniversariesDashboardItem"></table>
        </div>
    </div>
</div>

<?php
if ($depositEnabled) { // If the user has Finance permissions, then let's display the deposit line chart
    ?>
    <div class="card card-info"  id="depositChartRow">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-circle-dollar-to-slot"></i> <?= gettext('Deposit Tracking') ?></h3>
            <div class="card-tools pull-right">
                <div id="deposit-graph" class="chart-legend"></div>
            </div>
        </div><!-- /.box-header -->
        <div class="card-body" style="height: 200px">
            <canvas id="deposit-lineGraph" style="height:125px; width:100%"></canvas>
        </div>
    </div>
    <?php
}  //END IF block for Finance permissions to include HTML for Deposit Chart
?>

<div class="card">
    <div class="card-header with-border">
        <div class="card-title"><h4><?= gettext('People') ?></h4></div>
    </div>
    <div class="card-header p-0 pt-1 border-bottom-0">
        <ul class="nav nav-tabs" id="people-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="latest-fam-tab" data-toggle="tab" href="#latest-fam-pane" role="tab" aria-controls="latest-fam-pane" aria-selected="true">
                    <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Latest Families') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="updated-fam-tab" data-toggle="tab" href="#updated-fam-pane" role="tab" aria-controls="updated-fam-pane" aria-selected="false">
                    <i class="fa-solid fa-pen mr-1"></i><?= gettext('Updated Families') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="latest-ppl-tab" data-toggle="tab" href="#latest-ppl-pane" role="tab" aria-controls="latest-ppl-pane" aria-selected="false">
                    <i class="fa-solid fa-user-plus mr-1"></i><?= gettext('Latest Persons') ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="updated-ppl-tab" data-toggle="tab" href="#updated-ppl-pane" role="tab" aria-controls="updated-ppl-pane" aria-selected="false">
                    <i class="fa-solid fa-pen mr-1"></i><?= gettext('Updated Persons') ?>
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

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MainDashboard.js"></script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
