<?php

use ChurchCRM\dto\SystemURLs;

//Set the page title
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>


<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-2 col-xs-4">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3 id="familyCountDashboard">
                    <?= $dashboardCounts["families"] ?>
                </h3>
                <p>
                    <?= gettext('Families') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-users"></i>
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
    <?php if ($sundaySchoolEnabled) {
        ?>
        <div class="col-lg-2 col-xs-4">
            <!-- small box -->
            <div class="small-box bg-yellow">
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
                <i class="fa fa-gg"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/GroupList.php" class="small-box-footer">
                <?= gettext('More info') ?>  <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
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
                <i class="fa fa-gg"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/ListEvents.php" class="small-box-footer">
                <?= gettext('More info') ?>  <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
</div><!-- /.row -->

<div class="box">
    <div class="box-body">
        <div class="row">
            <div class="col-md-6">
                <div class="box box-solid">
                    <h3 class="box-header"><h4><?= gettext("Today's Birthdays") ?></h4></h3>
                    <div class="box-body">
                        <table class="table table-striped" width="100%" id="PersonBirthdayDashboardItem"></table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-solid">
                    <h3 class="box-header"><h4><?= gettext("Today's Wedding Anniversaries") ?></h4></h3>
                    <div class="box-body">
                        <table class="table table-striped" width="100%" id="FamiliesWithAnniversariesDashboardItem"></table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
            </div>
        </div>
    </div>
</div>

<?php
if ($depositEnabled) { // If the user has Finance permissions, then let's display the deposit line chart
    ?>
    <div class="row" id="depositChartRow">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="box box-info">
                <div class="box-header">
                    <i class="fa fa-money"></i>
                    <h3 class="box-title"><?= gettext('Deposit Tracking') ?></h3>
                    <div class="box-tools pull-right">
                        <div id="deposit-graph" class="chart-legend"></div>
                    </div>
                </div><!-- /.box-header -->
                <div class="box-body">
                    <canvas id="deposit-lineGraph" style="height:125px; width:100%"></canvas>
                </div>
            </div>
        </div>
    </div>
    <?php
}  //END IF block for Finance permissions to include HTML for Deposit Chart
?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('People') ?></h3>
        <div class="pull-right">
            <div class="btn-group">
                <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php">
                    <button type="button" class="btn btn-success"><?= gettext('Add New Person') ?></button>
                </a>
                <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php"
                <button type="button" class="btn btn-success"><?= gettext('Add New Family') ?></button>
                </a>
            </div>
        </div>
    </div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-12">
                <!-- Custom Tabs -->
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#ppl-tab_1" data-toggle="tab"><?= gettext('Latest Families') ?></a></li>
                        <li><a href="#ppl-tab_2" data-toggle="tab"><?= gettext('Updated Families') ?></a></li>
                        <li><a href="#ppl-tab_3" data-toggle="tab"><?= gettext('Latest Persons') ?></a></li>
                        <li><a href="#ppl-tab_4" data-toggle="tab"><?= gettext('Updated Persons') ?></a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="ppl-tab_1">
                            <table class="table table-striped" width="100%" id="latestFamiliesDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane" id="ppl-tab_2">
                            <table class="table table-striped" width="100%" id="updatedFamiliesDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane" id="ppl-tab_3">
                            <table class="table table-striped" width="100%" id="latestPersonDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                        <div class="tab-pane" id="ppl-tab_4">
                            <table class="table table-striped" width="100%" id="updatedPersonDashboardItem"></table>
                        </div>
                        <!-- /.tab-pane -->
                    </div>
                    <!-- /.tab-content -->
                </div>
                <!-- nav-tabs-custom -->
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/MainDashboard.js"></script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
