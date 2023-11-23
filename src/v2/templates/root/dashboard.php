<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
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
                <i class="fa fa-user-friends"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/family" class="small-box-footer">
                <?= gettext('See all Families') ?> <i class="fa fa-arrow-circle-right"></i>
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
                <i class="fa fa-user"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="small-box-footer">
                <?= gettext('See All People') ?> <i class="fa fa-arrow-circle-right"></i>
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
                <i class="fa fa-users"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/GroupList.php" class="small-box-footer">
                <?= gettext('More info') ?>  <i class="fa fa-arrow-circle-right"></i>
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
                    <i class="fa fa-child"></i>
                </div>
                <a href="<?= SystemURLs::getRootPath() ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
                    <?= gettext('More info') ?> <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div><!-- ./col -->
        <?php
    } ?>
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
                <i class="far fa-calendar-check"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/ListEvents.php" class="small-box-footer">
                <?= gettext('More info') ?>  <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
</div><!-- /.row -->

<div class="row">
    <div class="card col-md-6">
        <div class="card-body">
            <h3><?= gettext("Today's Birthdays") ?></h3>
            <table class="table table-striped" width="100%" id="PersonBirthdayDashboardItem"></table>
        </div>
    </div>
    <div class="card col-md-6">
        <div class="card-body">
            <h3><?= gettext("Today's Wedding Anniversaries") ?></h3>
            <table class="table table-striped" width="100%" id="FamiliesWithAnniversariesDashboardItem"></table>
        </div>
    </div>
</div>

<?php
if ($depositEnabled) { // If the user has Finance permissions, then let's display the deposit line chart
    ?>
    <div class="card" id="depositChartRow">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa fa-circle-dollar-to-slot"></i> <?= gettext('Deposit Tracking') ?></h3>
                    <div class="card-tools pull-right">
                        <div id="deposit-graph" class="chart-legend"></div>
                    </div>
                </div><!-- /.box-header -->
                <div class="card-body" style="height: 200px">
                    <canvas id="deposit-lineGraph" style="height:125px; width:100%"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php
}  //END IF block for Finance permissions to include HTML for Deposit Chart
?>

<div class="card">
    <div class="card-header with-border">
        <dev class="card-title"><h4><?= gettext('People') ?></h4></dev>
        <div class="card-tools">
            <div class="btn-group">
                <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php">
                    <button type="button" class="btn btn-success"><?= gettext('Add New Person') ?></button>
                </a>
                <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php">
                <button type="button" class="btn btn-success"><?= gettext('Add New Family') ?></button>
                </a>
            </div>
        </div>
    </div>
        <div class="card-body">
            <div class="row">
                <div class="col-5 col-sm-3">
                    <div class="nav flex-column nav-tabs h-100" id="vert-tabs-tab" role="tablist" aria-orientation="vertical">
                        <a class="nav-link active" id="vert-tabs-latest-fam-tab" data-toggle="pill" href="#vert-tabs-lasterst-fam" role="tab" aria-controls="vert-tabs-lasterst-fam" aria-selected="true"><?= gettext('Latest Families') ?></a>
                        <a class="nav-link" id="vert-tabs-updated-fam-tab" data-toggle="pill" href="#vert-tabs-updated-fam" role="tab" aria-controls="vert-tabs-updated-fam" aria-selected="false"><?= gettext('Updated Families') ?></a>
                        <a class="nav-link" id="vert-tabs-latest-ppl-tab" data-toggle="pill" href="#vert-tabs-latest-ppl" role="tab" aria-controls="vert-tabs-latest-ppl" aria-selected="false"><?= gettext('Latest Persons') ?></a>
                        <a class="nav-link" id="vert-tabs-updated-ppl-tab" data-toggle="pill" href="#vert-tabs-updated-ppl" role="tab" aria-controls="vert-tabs-updated-ppl" aria-selected="false"><?= gettext('Updated Persons') ?></a>
                    </div>
                </div>
                <div class="col-7 col-sm-9">
                    <div class="tab-content" id="vert-tabs-tabContent">
                        <div class="tab-pane text-left fade show active" id="vert-tabs-lasterst-fam" role="tabpanel" aria-labelledby="vert-tabs-latest-fam-tab">
                            <table class="table table-striped" width="100%" id="latestFamiliesDashboardItem"></table>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-updated-fam" role="tabpanel" aria-labelledby="vert-tabs-updated-fam-tab">
                            <table class="table table-striped" width="100%" id="updatedFamiliesDashboardItem"></table>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-latest-ppl" role="tabpanel" aria-labelledby="vert-tabs-latest-ppl-tab">
                            <table class="table table-striped" width="100%" id="latestPersonDashboardItem"></table>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-updated-ppl" role="tabpanel" aria-labelledby="vert-tabs-updated-ppl-tab">
                            <table class="table table-striped" width="100%" id="updatedPersonDashboardItem"></table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.card -->
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MainDashboard.js"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
