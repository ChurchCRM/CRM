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
*  2017 Philippe Logel
*

******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
use ChurchCRM\DepositQuery;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\MenuEventsCount;

$dashboardService = new DashboardService();

//last Edited members from Active families
$updatedMembers = $dashboardService->getUpdatedMembers(12);
//Newly added members from Active families
$latestMembers = $dashboardService->getLatestMembers(12);

$depositData = false;  //Determine whether or not we should display the deposit line graph
if ($_SESSION['user']->isFinanceEnabled()) {
    $deposits = DepositQuery::create()->filterByDate(['min' =>date('Y-m-d', strtotime('-90 days'))])->find();
    if (count($deposits) > 0) {
        $depositData = $deposits->toJSON();
    }
}


// Set the page title
$sPageTitle = gettext('Welcome to').' '. ChurchMetaData::getChurchName();

require 'Include/Header.php';

$showBanner = SystemConfig::getValue("bEventsOnDashboardPresence");

$peopleWithBirthDays = MenuEventsCount::getBirthDates();
$Anniversaries = MenuEventsCount::getAnniversaries();
$peopleWithBirthDaysCount = MenuEventsCount::getNumberBirthDates();
$AnniversariesCount = MenuEventsCount::getNumberAnniversaries();


if ($showBanner && ($peopleWithBirthDaysCount > 0 || $AnniversariesCount > 0)) {
    ?>
    <div class="alert alert-info alert-dismissible bg-purple disabled color-palette" id="Menu_Banner">
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true" style="color:#fff;">&times;</button>

    <?php
    if ($peopleWithBirthDaysCount > 0) {
        ?>
        <h4 class="alert-heading"><?= gettext("Birthdates of the day") ?></h4>
        <p>
        <div class="row">

      <?php
        $new_row = false;
        $count_people = 0;

        {
            foreach ($peopleWithBirthDays as $peopleWithBirthDay) {
                if ($new_row == false) {
                    ?>

                    <div class="row">
                <?php
                    $new_row = true;
                } ?>
                <div class="col-sm-3">
                <label class="checkbox-inline">
                    <a href="<?= $peopleWithBirthDay->getViewURI()?>" class="btn btn-link" style="text-decoration: none"><?= $peopleWithBirthDay->getFullNameWithAge() ?></a>
                </label>
                </div>
              <?php
                $count_people+=1;
                $count_people%=4;
                if ($count_people == 0) {
                    ?>
                    </div>
                    <?php $new_row = false;
                }
            }

            if ($new_row == true) {
                ?>
                </div>
            <?php
            }
          } ?>

        </div>
        </p>
    <?php
    } ?>

    <?php if ($AnniversariesCount > 0) {
        if ($peopleWithBirthDaysCount > 0) {
            ?>
            <hr>
    <?php
        } ?>

        <h4 class="alert-heading"><?= gettext("Anniversaries of the day")?></h4>
        <p>
        <div class="row">

    <?php
        $new_row = false;
        $count_people = 0;

        foreach ($Anniversaries as $Anniversary) {
            if ($new_row == false) {
                ?>
                <div class="row">

                <?php $new_row = true;
            } ?>
            <div class="col-sm-3">
            <label class="checkbox-inline">
              <a href="<?= $Anniversary->getViewURI() ?>" class="btn btn-link" style="text-decoration: none"><?= $Anniversary->getFamilyString() ?></a>
            </label>
            </div>

            <?php
            $count_people+=1;
            $count_people%=4;
            if ($count_people == 0) {
                ?>
                </div>
            <?php
                $new_row = false;
            }
        }

        if ($new_row == true) {
            ?>
            </div>
        <?php
        } ?>

        </div>
        </p>
    <?php
    } ?>
  </div>

<?php
}?>

<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3 id="familyCountDashboard">
                    0
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
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3 id="peopleStatsDashboard">
                    0
                </h3>
                <p>
                    <?= gettext('People') ?>
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-user"></i>
            </div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="small-box-footer">
                <?= gettext('See all People') ?> <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <?php if (SystemConfig::getValue('bEnabledSundaySchool')) {
        ?> 
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3 id="groupStatsSundaySchool">
                   0
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
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3 id="groupsCountDashboard">
                  0
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
            <a href="<?= SystemURLs::getRootPath() ?>/ListEvents.php" class="small-box-footer">
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

<div class="row">
    <div class="col-lg-6">
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-user-plus"></i>
                <h3 class="box-title"><?= gettext('Latest Families') ?></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i>
                    </button>
                </div>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive" style="overflow:hidden">
                    <table class="dataTable table table-striped table-condensed" id="latestFamiliesDashboardItem">
                        <thead>
                        <tr>
                            <th data-field="name"><?= gettext('Family Name') ?></th>
                            <th data-field="address"><?= gettext('Address') ?></th>
                            <th data-field="city"><?= gettext('Created') ?></th>
                        </tr>
                        </thead>
                        <tbody>
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
                <div class="table-responsive" style="overflow:hidden">
                    <table class=" dataTable table table-striped table-condensed" id="updatedFamiliesDashboardItem">
                        <thead>
                        <tr>
                            <th data-field="name"><?= gettext('Family Name') ?></th>
                            <th data-field="address"><?= gettext('Address') ?></th>
                            <th data-field="city"><?= gettext('Updated') ?></th>
                        </tr>
                        </thead>
                        <tbody>
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
                    <h3 class="box-title"><?= gettext('Latest Persons') ?></h3>
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
                                    <img src="<?= SystemURLs::getRootPath(); ?>/api/person/<?= $person->getId() ?>/thumbnail"
                                         alt="<?= $person->getFullName() ?>" class="user-image initials-image"
                                         width="<?= SystemConfig::getValue('iProfilePictureListSize') ?>px" height="<?= SystemConfig::getValue('iProfilePictureListSize') ?>px"/><br/>
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
                    <h3 class="box-title"><?= gettext('Updated Persons') ?></h3>
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
                                    <img src="<?= SystemURLs::getRootPath(); ?>/api/person/<?= $person->getId() ?>/thumbnail"
                                         alt="<?= $person->getFullName() ?>" class="user-image initials-image"
                                         width="<?= SystemConfig::getValue('iProfilePictureListSize') ?>px" height="<?= SystemConfig::getValue('iProfilePictureListSize') ?>px"/><br/>
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
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  var timeOut = <?= SystemConfig::getValue("iEventsOnDashboardPresenceTimeOut")*1000 ?>;

  $(document).ready (function(){
    $("#myWish").click(function showAlert() {
        $("#Menu_Banner").alert();
        window.setTimeout(function () {
            $("#Menu_Banner").alert('close'); }, timeOut);
       });
    });

    $("#Menu_Banner").fadeTo(timeOut, 500).slideUp(500, function(){
    $("#Menu_Banner").slideUp(500);
});
</script>


<?php
require 'Include/Footer.php';
?>
