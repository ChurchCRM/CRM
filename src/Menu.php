<?php
/*******************************************************************************
*
*  filename    : Menu.php
*  description : menu that appears after login, shows login attempts
*
*  http://www.churchcrm.io/
*  Copyright 2001-2002 Phillip Hullquist, Deane Barker, Michael Wilt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\DepositQuery;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\ChurchMetaData;

$financialService = new FinancialService();
$dashboardService = new DashboardService();
$personCount = $dashboardService->getPersonCount();
$familyCount = $dashboardService->getFamilyCount();
$groupStats = $dashboardService->getGroupStats();
//Last edited active families
$updatedFamilies = $dashboardService->getUpdatedFamilies(10);
//Newly added active families
$latestFamilies = $dashboardService->getLatestFamilies(10);
//last Edited members from Active families
$updatedMembers = $dashboardService->getUpdatedMembers(12);
//Newly added members from Active families
$latestMembers = $dashboardService->getLatestMembers(12);

$depositData = false;  //Determine whether or not we should display the deposit line graph
if ($_SESSION['bFinance']) {
    $deposits = DepositQuery::create()->filterByDate(['min' =>date('Y-m-d', strtotime('-90 days'))])->find();
    if (count($deposits) > 0) {
        $depositData = $deposits->toJSON();
    }
}

// Set the page title
$sPageTitle = gettext('Welcome to').' '. ChurchMetaData::getChurchName();

require 'Include/Header.php';
?>
<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>
                    <?= $familyCount['familyCount'] ?>
                </h3>
                <p>
                    <?= gettext('Families') ?>
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person-stalker"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/FamilyList.php" class="small-box-footer">
                <?= gettext('See all Families') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3>
                    <?= $personCount['personCount'] ?>
                </h3>
                <p>
                    <?= gettext('People') ?>
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/SelectList.php?mode=person" class="small-box-footer">
                <?= gettext('See All People') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>
                    <?= $groupStats['sundaySchoolClasses'] ?>
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
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3>
                  <?= $groupStats['groups'] - $groupStats['sundaySchoolClasses']  ?>
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
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>
                  <?=
                     ChurchCRM\Base\EventAttendQuery::create()
                    ->filterByCheckinDate(null, \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
                    ->filterByCheckoutDate(null, \Propel\Runtime\ActiveQuery\Criteria::EQUAL)
                    ->find()
                    ->count();
                  ?>
                </h3>
                <p>
                    <?= gettext('Attendees Checked In') ?>
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
</div><!-- /.row -->

<?php
if ($depositData) { // If the user has Finance permissions, then let's display the deposit line chart
?>
<div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12">
        <div class="box box-info">
            <div class="box-header">
                <i class="ion ion-cash"></i>
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

<div class="row">
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header">
                <i class="ion ion-person-add"></i>
                <h3 class="box-title"><?= gettext('Latest Families') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th data-field="name"><?= gettext('Family Name') ?></th>
                            <th data-field="address"><?= gettext('Address') ?></th>
                            <th data-field="city"><?= gettext('Created') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($latestFamilies as $family) {
    ?>
                            <tr>
                                <td>
                                    <a href="FamilyView.php?FamilyID=<?= $family->getId() ?>"><?= $family->getName() ?></a>
                                </td>
                                <td><?= $family->getAddress() ?></td>
                                <td><?=  date_format($family->getDateEntered(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                            </tr>
                            <?php
}
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-check"></i>
                <h3 class="box-title"><?= gettext('Updated Families') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th data-field="name"><?= gettext('Family Name') ?></th>
                            <th data-field="address"><?= gettext('Address') ?></th>
                            <th data-field="city"><?= gettext('Updated') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($updatedFamilies as $family) {
                            ?>
                            <tr>
                                <td>
                                    <a href="FamilyView.php?FamilyID=<?= $family->getId() ?>"><?= $family->getName() ?></a>
                                </td>
                                <td><?= $family->getAddress() ?></td>
                                <td><?=  date_format($family->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong')) ?></td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><?= gettext('Latest Members') ?></h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <!-- /.box-header -->
                <div class="box-body no-padding">
                    <ul class="users-list clearfix">
                        <?php foreach ($latestMembers as $person) {
                            ?>
                            <li>
                                <a class="users-list" href="PersonView.php?PersonID=<?= $person->getId() ?>">
                                    <img data-name="<?= $person->getFullName() ?>"
                                         data-src="<?= SystemURLs::getRootPath(); ?>/api/persons/<?= $person->getId() ?>/thumbnail"
                                         alt="<?= $person->getFullName() ?>" class="user-image initials-image"
                                         width="85" height="85"/><br/>
                                    <?= $person->getFullName() ?></a>
                                <span class="users-list-date"><?= date_format($person->getDateEntered(), SystemConfig::getValue('sDateFormatLong')); ?>&nbsp;</span>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                    <!-- /.users-list -->
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><?= gettext('Updated Members') ?></h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                        </button>
                        <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <!-- /.box-header -->
                <div class="box-body no-padding">
                    <ul class="users-list clearfix">
                        <?php foreach ($updatedMembers as $person) {
                            ?>
                            <li>
                                <a class="users-list" href="PersonView.php?PersonID=<?= $person->getId() ?>">
                                    <img data-name="<?= $person->getFullName() ?>"
                                         data-src="<?= SystemURLs::getRootPath(); ?>/api/persons/<?= $person->getId() ?>/thumbnail"
                                         alt="<?= $person->getFullName() ?>" class="user-image initials-image"
                                         width="85" height="85"/><br/>
                                    <?= $person->getFullName() ?></a>
                                <span
                                    class="users-list-date"><?= date_format($person->getDateLastEdited(), SystemConfig::getValue('sDateFormatLong')); ?>&nbsp;</span>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                    <!-- /.users-list -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- this page specific inline scripts -->
<script>
<?php
if ($depositData) { // If the user has Finance permissions, then let's display the deposit line chart
?>
    //---------------
    //- LINE CHART  -
    //---------------
    var lineDataRaw = <?= $depositData ?>;

    var lineData = {
        labels: [],
        datasets: [
            {
                data: []
            }
        ]
    };


  $( document ).ready(function() {
    $.each(lineDataRaw.Deposits, function(i, val) {
        lineData.labels.push(moment(val.Date).format("MM-DD-YY"));
        lineData.datasets[0].data.push(val.totalAmount);
    });
    options = {
      responsive:true,
      maintainAspectRatio:false
    };
    var lineChartCanvas = $("#deposit-lineGraph").get(0).getContext("2d");
    var lineChart = new Chart(lineChartCanvas).Line(lineData,options);

  });
<?php
                        }  //END IF block for Finance permissions to include JS for Deposit Chart
?>
</script>


<?php
require 'Include/Footer.php';
?>
