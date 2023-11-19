<?php

/**
 * User: George Dawoud
 * Date: 1/17/2016
 * Time: 8:01 AM.
 */

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\DashboardService;

// Set the page title
$sPageTitle = gettext('People Dashboard');

require 'Include/Header.php';

$dashboardService = new DashboardService();
$personCount = $dashboardService->getPersonCount();
$personStats = $dashboardService->getPersonStats();
$familyCount = $dashboardService->getFamilyCount();
$groupStats = $dashboardService->getGroupStats();
$ageStats = $dashboardService->getAgeStats();
$demographicStats = ListOptionQuery::create()->filterByID('2')->find();

$sSQL = 'select count(*) as numb, per_Gender from person_per, family_fam
        where fam_ID =per_fam_ID and fam_DateDeactivated is  null
        and per_Gender in (1,2) and
        per_fmr_ID not in (' . SystemConfig::getValue('sDirRoleChild') . ')
        group by per_Gender ;';
$rsAdultsGender = RunQuery($sSQL);

$sSQL = 'select count(*) as numb, per_Gender from person_per , family_fam
          where fam_ID =per_fam_ID and fam_DateDeactivated is  null
          and per_Gender in (1,2)
          and per_fmr_ID in (' . SystemConfig::getValue('sDirRoleChild') . ')
          group by per_Gender ;';
$rsKidsGender = RunQuery($sSQL);

$sSQL = 'select lst_OptionID,lst_OptionName from list_lst where lst_ID = 1;';
$rsClassification = RunQuery($sSQL);
$classifications = new stdClass();
while (list($lst_OptionID, $lst_OptionName) = mysqli_fetch_row($rsClassification)) {
    $classifications->$lst_OptionName = $lst_OptionID;
}

$sSQL = "SELECT per_Email, fam_Email, lst_OptionName as virt_RoleName FROM person_per
          LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
          INNER JOIN list_lst on lst_ID=1 AND per_cls_ID = lst_OptionID
          WHERE fam_DateDeactivated is  null
			 AND per_ID NOT IN
          (SELECT per_ID
              FROM person_per
              INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
              INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email')";

$rsEmailList = RunQuery($sSQL);
$sEmailLink = '';
$sMailtoDelimiter = AuthenticationManager::getCurrentUser()->getUserConfigString("sMailtoDelimiter");
$roleEmails = [];
while (list($per_Email, $fam_Email, $virt_RoleName) = mysqli_fetch_row($rsEmailList)) {
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
    if ($sEmail) {
        if (!stristr($sEmailLink, $sEmail)) {
            $sEmailLink .= $sEmail .= $sMailtoDelimiter;
            if (!array_key_exists($virt_RoleName, $roleEmails)) {
                $roleEmails[$virt_RoleName] = "";
            }
            $roleEmails[$virt_RoleName] .= $sEmail;
        }
    }
}

$selfRegColor = "bg-red";
$selfRegText = "Disabled";
if (SystemConfig::getBooleanValue("bEnableSelfRegistration")) {
    $selfRegColor = "bg-green";
    $selfRegText = "Enabled";
}
?>

<!-- Default box -->
<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?= gettext('People Functions') ?></h3>
  </div>
  <div class="card-body">
      <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="btn btn-app"><i class="fa fa-user"></i><?= gettext('All People') ?></a>
      <a href="<?= SystemURLs::getRootPath() ?>/v2/people/verify" class="btn btn-app"><i class="fas fa-clipboard-check"></i><?= gettext('Verify People') ?></a>
      <div class="btn btn-app"><span class="badge <?=$selfRegColor?>"><?= $selfRegText?></span><i class="fa fa-user-plus"></i><?= gettext('Self Register') ?></div>
      <a href="<?= SystemURLs::getRootPath()?>/v2/family" class="btn btn-app"><i class="fa fa-users"></i><?= gettext('All Families') ?></a>
      <br/>
      <a href="MapUsingGoogle.php?GroupID=-1" class="btn btn-app"><i class="fa fa-map"></i><?= gettext('Family Map') ?></a>
      <a href="GeoPage.php" class="btn btn-app"><i class="fa fa-globe"></i><?= gettext('Family Geographic') ?></a>
      <a href="UpdateAllLatLon.php" class="btn btn-app"><i class="fa fa-map-pin"></i><?= gettext('Update All Family Coordinates') ?></a>
      <br/>

      <?php
        if ($sEmailLink) {
            // Add default email if default email has been set and is not already in string
            if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
                $sEmailLink .= $sMailtoDelimiter . SystemConfig::getValue('sToEmailAddress');
            }
            $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
            if (AuthenticationManager::getCurrentUser()->isEmailEnabled()) { // Does user have permission to email groups
        // Display link
                ?>
        <div class="btn-group">
          <a  class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><i class="fas fa-mail-bulk"></i></i><?= gettext('Email All')?></a>
          <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
                <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
          </ul>
        </div>
       <div class="btn-group">
          <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><i class="fas fa-mail-bulk"></i><?=gettext('Email All (BCC)') ?></a>
           <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
                <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
          </ul>
        </div>
                <?php
            }
        }
        ?>
  </div>
</div>
<!-- Small boxes (Stat box) -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-6">
    <!-- small box -->
    <div class="small-box bg-gray">
      <div class="inner">
        <h3>
          <?= $familyCount['familyCount'] ?>
        </h3>

        <p>
          <?= gettext('Families') ?>
        </p>
      </div>
      <div class="icon">
        <i class="fa fa-user-friends"></i>
      </div>
      <a href="<?= SystemURLs::getRootPath()?>/v2/family" class="small-box-footer">
        <?= gettext('See all Families') ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
  <!-- ./col -->
  <div class="col-lg-3 col-md-6 col-sm-6">
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
        <i class="fa fa-user"></i>
      </div>
      <a href="<?= SystemURLs::getRootPath() ?>/v2/people" class="small-box-footer">
        <?= gettext('See All People') ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
  <?php if (SystemConfig::getValue('bEnabledSundaySchool')) {
        ?>
  <!-- ./col -->
  <div class="col-lg-3 col-md-6 col-sm-6">
    <!-- small box -->
    <div class="small-box bg-yellow">
      <div class="inner">
        <h3>
          <?= $groupStats['sundaySchoolkids'] ?>
        </h3>

        <p>
          <?= gettext('Sunday School Kids') ?>
        </p>
      </div>
      <div class="icon">
        <i class="fa fa-child"></i>
      </div>
      <a href="<?= SystemURLs::getRootPath() ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
        <?= gettext('More info') ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
        <?php
  } ?>
  <!-- ./col -->
  <div class="col-lg-3 col-md-6 col-sm-6">
    <!-- small box -->
    <div class="small-box bg-red">
      <div class="inner">
        <h3>
          <?= $groupStats['groups'] - $groupStats['sundaySchoolClasses'] ?>
        </h3>

        <p>
          <?= gettext('Groups') ?>
        </p>
      </div>
      <div class="icon">
        <i class="fa fa-users"></i>
      </div>
      <a href="<?= SystemURLs::getRootPath() ?>/grouplist" class="small-box-footer">
        <?= gettext('More info') ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
  <!-- ./col -->

</div><!-- /.row -->
<div class="row">
  <div class="col-lg-6">
    <div class="card card-info">
      <div class="card-header with-border">
        <h3 class="card-title"><i class="fa fa-file-lines"></i>  <?= gettext('Reports') ?></h3>
          <div class="card-tools pull-right">
              <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-minus"></i></button>
              <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa fa-times"></i></button>
          </div>
      </div>
      <div class="card-body">
        <p> <a class="MediumText" href="members/self-register.php"><?php echo gettext('Self Register') ?> <?= gettext('Reports') ?></a>
        <br>
        <?php echo gettext('List families that were created via self registration.') ?>
       </p>
        <a class="MediumText" href="GroupReports.php"><?php echo gettext('Reports on groups and roles'); ?></a>
        <br>
        <?php echo gettext('Report on group and roles selected (it may be a multi-page PDF).'); ?>
        </p>
        <?php if (AuthenticationManager::getCurrentUser()->isCreateDirectoryEnabled()) {
            ?>
          <p><a class="MediumText"
                href="DirectoryReports.php"><?= gettext('People Directory') ?></a><br><?= gettext('Printable directory of all people, grouped by family where assigned') ?>
          </p>
            <?php
        } ?>
        <a class="MediumText" href="LettersAndLabels.php"><?php echo gettext('Letters and Mailing Labels'); ?></a>
        <br><?php echo gettext('Generate letters and mailing labels.'); ?>
        </p>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
      <div class="card card-primary">
      <div class="card-header with-border">
        <h3 class="card-title"><i class="fa fa-chart-bar"></i> <?= gettext('People Classification') ?></h3>
        <div class="card-tools pull-right">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <div class="card-body no-padding">
      <table class="table table-condensed">
        <tr>
          <th><?= gettext('Classification') ?></th>
          <th>% <?= gettext('of People') ?></th>
          <th style="width: 40px"><?= gettext('Count') ?></th>
        </tr>
        <?php foreach ($personStats as $key => $value) {
            ?>
          <tr>
            <td><a href='v2/people?Classification=<?= $classifications->$key ?>'><?= gettext($key) ?></a></td>
            <td>
              <div class="progress progress-xs progress-striped active">
                <div class="progress-bar progress-bar-success"
                     style="width: <?= round($value / $personCount['personCount'] * 100) ?>%"></div>
              </div>
            </td>
            <td><span class="badge bg-green"><?= $value ?></span></td>
          </tr>
            <?php
        } ?>
      </table>
      <!-- /.box-body-->
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-6">
    <div class="card card-primary">
      <div class="card-header with-border">
       <h3 class="card-title"> <i class="fa fa-people-group"></i> <?= gettext('Family Roles') ?></h3>
       <div class="card-tools pull-right">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <div class="card-body no-padding">
        <table class="table table-condensed">
          <tr>
            <th><?= gettext('Role / Gender') ?></th>
            <th>% <?= gettext('of People') ?></th>
            <th style="width: 40px"><?= gettext('Count') ?></th>
          </tr>
            <?php foreach ($demographicStats as $demStat) {
                $countMale = PersonQuery::create()->filterByFmrId($demStat->getOptionID())->filterByGender(1)->count();
                $countFemale = PersonQuery::create()->filterByFmrId($demStat->getOptionID())->filterByGender(2)->count();
                $countUnknown = PersonQuery::create()->filterByFmrId($demStat->getOptionID())->filterByGender(0)->count();
                $demStatId = $demStat->getOptionID();
                $demStatName = $demStat->getOptionName();
                $genPop = PersonQuery::create()->count();
                if ($countMale != 0) {
                    ?>
<tr>
<td><a href="v2/people?Gender=1&FamilyRole=<?= $demStatId ?>"><?= $demStatName ?> - <?= gettext('Male') ?></a></td>
<td>
<div class="progress progress-xs progress-striped active">
<div class="progress-bar progress-bar-success" style="width: <?= round(($countMale / $genPop) * 100)?>%" title="<?= round(($countMale / $genPop) * 100)?>%"></div>
</div>
</td>
<td><span class="badge bg-green"><?= $countMale ?></span></td>
</tr>
                    <?php
                }
                if ($countFemale != 0) {
                    ?>
<tr>
<td><a href="v2/people?Gender=2&FamilyRole=<?= $demStatId ?>"><?= $demStatName ?> - <?= gettext('Female') ?></a></td>
<td>
<div class="progress progress-xs progress-striped active">
<div class="progress-bar progress-bar-success" style="width: <?= round(($countFemale / $genPop) * 100)?>%" title="<?= round(($countFemale / $genPop) * 100)?>%"></div>
</div>
</td>
<td><span class="badge bg-green"><?= $countFemale ?></span></td>
</tr>
                    <?php
                }
                if ($countUnknown != 0) {
                    ?>
<tr>
<td><a href="v2/people?Gender=0&FamilyRole=<?= $demStatId ?>"><?= $demStatName ?> - <?= gettext('Unassigned') ?></a></td>
<td>
<div class="progress progress-xs progress-striped active">
<div class="progress-bar progress-bar-success" style="width: <?= round(($countUnknown / $genPop) * 100)?>%" title="<?= round(($countUnknown / $genPop) * 100)?>%"></div>
</div>
</td>
<td><span class="badge bg-green"><?= $countUnknown ?></span></td>
</tr>
                    <?php
                }
            }
            $countUnknownMale = PersonQuery::create()->filterByFmrId(0)->filterByGender(1)->count();
            $countUnknownFemale = PersonQuery::create()->filterByFmrId(0)->filterByGender(2)->count();
            $countUnknownRoleUnknownGender = PersonQuery::create()->filterByFmrId(0)->filterByGender(0)->count();

            $genPop = PersonQuery::create()->count();
            if ($countUnknownMale != 0) {
                ?>
<tr>
<td><a href="v2/people?Gender=1&FamilyRole=0"><?= gettext('Unassigned') ?> - <?= gettext('Male') ?></a></td>
<td>
<div class="progress progress-xs progress-striped active">
<div class="progress-bar progress-bar-success" style="width: <?= round(($countUnknownMale / $genPop) * 100)?>%" title="<?= round(($countUnknownMale / $genPop) * 100)?>%"></div>
</div>
</td>
<td><span class="badge bg-green"><?= $countUnknownMale ?></span></td>
</tr>
                <?php
            }
            if ($countUnknownFemale != 0) {
                ?>
<tr>
<td><a href="v2/people?Gender=2&FamilyRole=0"><?= gettext('Unassigned') ?> - <?= gettext('Female') ?></a></td>
<td>
<div class="progress progress-xs progress-striped active">
<div class="progress-bar progress-bar-success" style="width: <?= round(($countUnknownFemale / $genPop) * 100)?>%" title="<?= round(($countUnknownFemale / $genPop) * 100)?>%"></div>
</div>
</td>
<td><span class="badge bg-green"><?= $countUnknownFemale ?></span></td>
</tr>
                <?php
            }
            if ($countUnknownRoleUnknownGender != 0) {
                ?>
<tr>
<td><a href="v2/people?Gender=0&FamilyRole=0"><?= gettext('Unassigned') ?> - <?= gettext('Unassigned') ?></a></td>
<td>
<div class="progress progress-xs progress-striped active">
<div class="progress-bar progress-bar-success" style="width: <?= round(($countUnknownRoleUnknownGender / $genPop) * 100)?>%" title="<?= round(($countUnknownRoleUnknownGender / $genPop) * 100)?>%"></div>
</div>
</td>
<td><span class="badge bg-green"><?= $countUnknownRoleUnknownGender ?></span></td>
</tr>
                <?php
            }
            ?>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">

    <div class="card card-info">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-id-card-clip"></i> <?= gettext('Gender Demographics') ?></h3>
      </div>
      <!-- /.box-header -->
      <div class="card-body" style="height: 300px">
        <canvas id="gender-donut" style="height:250px"></canvas>
      </div>
    </div>
    <div class="card card-info">
      <div class="card-header">
        <h3 class="card-title"><i class="fa fa-birthday-cake"></i> <?= gettext('Age Histogram') ?></h3>
      </div>
      <!-- /.box-header -->
      <div class="card-body" style="height: 400px">
        <canvas id="age-stats-bar" style="height:250px"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- this page specific inline scripts -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
    //Gender Donut
    var pieData = {
        labels: [
            '<?= gettext('Men') ?>',
            '<?= gettext('Women') ?>',
            '<?= gettext('Boys') ?>',
            '<?= gettext('Girls') ?>'
        ],
        datasets: [{
            data: [
            <?php while ($row = mysqli_fetch_array($rsAdultsGender)) {
                if ($row['per_Gender'] == 1) {
                    echo $row['numb'] . ',';
                }
                if ($row['per_Gender'] == 2) {
                    echo $row['numb'] . ',';
                }
            }
            while ($row = mysqli_fetch_array($rsKidsGender)) {
                if ($row['per_Gender'] == 1) {
                    echo $row['numb'] . ',';
                }
                if ($row['per_Gender'] == 2) {
                    echo $row['numb'];
                }
            }?>
            ],
            backgroundColor: ["#003399","#9900ff","#3399ff","#009933"],
            hoverBackgroundColor: ["#3366ff","#ff66cc","#99ccff","#99cc00"]
        }]
    };

    var pieOptions = {
        //Display a title
        title: {
            display: false
        },
        //Boolean - Whether we should show a stroke on each segment
        segmentShowStroke: true,
        //String - The colour of each segment stroke
        segmentStrokeColor: "#fff",
        //Number - The width of each segment stroke
        segmentStrokeWidth: 2,
        //Number - The percentage of the chart that we cut out of the middle
        percentageInnerCutout: 50, // This is 0 for Pie charts
        //Boolean - Whether we animate the rotation of the Doughnut
        animateRotate: false,
        //Boolean - whether to make the chart responsive to window resizing
        responsive: true,
        // Boolean - whether to maintain the starting aspect ratio or not when responsive, if set to false, will take up entire container
        maintainAspectRatio: false,
    };

    var ctx = document.getElementById("gender-donut").getContext('2d');
    var pieChart = new Chart(ctx, {
        type: 'doughnut',
        data: pieData,
        options: pieOptions
    });

    //Age Histogram
    var ageLabels = <?= json_encode(array_keys($ageStats)); ?>;
    var ageValues = <?= json_encode(array_values($ageStats)); ?>;

    var ctx = document.getElementById("age-stats-bar").getContext('2d');
    var AgeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ageLabels,
            datasets: [{
                label: "Ages",
                data: ageValues,
                backgroundColor: "#3366ff"
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            legend: {
                display: true
            },
            title: {
               display: false
            },
            scales: {
                xAxes: [{
                   display: true,
                }],
                yAxes: [{
                   ticks: {
                   beginAtZero: true,
                   stepSize: 1
                   }
                }]
            }
        }
    });
});
</script>

<?php require 'Include/Footer.php' ?>
