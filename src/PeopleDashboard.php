<?php
/**
 * User: George Dawoud
 * Date: 1/17/2016
 * Time: 8:01 AM.
 * 
 * Updated by Troy Smith, 2019-07-13
 */

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\DashboardService;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use ChurchCRM\ListOptionQuery;
use ChurchCRM\SessionUser;
use ChurchCRM\GenderTypeQuery;
use Propel\Runtime\Propel;

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

$classList = ListOptionQuery::create()->filterByID(1)->find();
foreach ($classList as $list) {
  $classifications[$list->getOptionName()] = $list->getOptionID();
}

$sSQL = "SELECT per_Email, fam_Email, lst_OptionName as virt_RoleName FROM person_per
          LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
          INNER JOIN list_lst on lst_ID=1 AND per_cls_ID = lst_OptionID
          WHERE family_fam.fam_DateDeactivated is null
			 AND per_ID NOT IN
          (SELECT per_ID
              FROM person_per
              INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
              INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email')";

$conn = Propel::getConnection(); 
$stmt = $conn->prepare($sSQL);
$stmt->execute();
$rsEmailList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$sEmailLink = '';
$sMailtoDelimiter = SessionUser::getUser()->getUserConfigString("sMailtoDelimiter");
$roleEmails = array();

foreach($rsEmailList as $email) {
  $sEmail = SelectWhichInfo($email['per_Email'], $email['fam_Email'], false);
 
  if ($sEmail) {
    if (!stristr($sEmailLink, $sEmail)) {
        $sEmailLink .= $sEmail .= $sMailtoDelimiter;
        if (!array_key_exists($email['virt_RoleName'], $roleEmails)) {
            $roleEmails[$email['virt_RoleName']] = "";
        }
        $roleEmails[$email['virt_RoleName']] .= $sEmail;
    }
  }
}

?>

<!-- Default box -->
<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext('People Functions') ?></h3>
  </div>
  <div class="box-body">
    <a href="<?= SystemURLs::getRootPath() ?>/SelectList.php?mode=person" class="btn btn-app"><i class="fa fa-user"></i><?= gettext('All People') ?></a>
    <a href="<?= SystemURLs::getRootPath() ?>/v2/people/verify" class="btn btn-app"><i class="fa fa-check-square-o"></i><?= gettext('Verify People') ?></a>
    <?php
    if ($sEmailLink) {
        // Add default email if default email has been set and is not already in string
        if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
            $sEmailLink .= $sMailtoDelimiter.SystemConfig::getValue('sToEmailAddress');
        }
        $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
       if (SessionUser::getUser()->isEmailEnabled()) { // Does user have permission to email groups
      // Display link
       ?>
        <div class="btn-group">
          <a  class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><i class="fa fa-send-o"></i><?= gettext('Email All')?></a>
          <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
           <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
          </ul>
        </div>
       <div class="btn-group">
          <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><i class="fa fa-send"></i><?=gettext('Email All (BCC)') ?></a>
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
    <br/>
    <a href="<?= SystemURLs::getRootPath()?>/v2/family" class="btn btn-app"><i class="fa fa-users"></i><?= gettext('All Families') ?></a>
    <a href="GeoPage.php" class="btn btn-app"><i class="fa fa-globe"></i><?= gettext('Family Geographic') ?></a>
    <a href="MapUsingGoogle.php?GroupID=-1" class="btn btn-app"><i class="fa fa-map"></i><?= gettext('Family Map') ?>
    </a>
    <a href="UpdateAllLatLon.php" class="btn btn-app"><i
        class="fa fa-map-pin"></i><?= gettext('Update All Family Coordinates') ?></a>
  </div>
</div>
<!-- Small boxes (Stat box) -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-6">
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
        <i class="fa fa-users"></i>
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
      <a href="<?= SystemURLs::getRootPath() ?>/SelectList.php?mode=person" class="small-box-footer">
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
        <i class="fa fa-gg"></i>
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
    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext('Reports') ?></h3>
          <div class="box-tools pull-right">
              <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
              </button>
              <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
          </div>
      </div>
      <div class="box-body">
        <p> <a class="MediumText" href="members/self-register.php"><?php echo gettext('Self Register') ?> <?= gettext('Reports') ?></a>
        <br>
        <?php echo gettext('List families that were created via self registration.') ?>
       </p>
        <a class="MediumText" href="GroupReports.php"><?php echo gettext('Reports on groups and roles'); ?></a>
        <br>
        <?php echo gettext('Report on group and roles selected (it may be a multi-page PDF).'); ?>
        </p>
        <?php if (SessionUser::getUser()->isCreateDirectoryEnabled()) {
         ?>
          <p><a class="MediumText"
                href="DirectoryReports.php"><?= gettext('People Directory') ?></a><br><?= gettext('Printable directory of all people, grouped by family where assigned') ?>
          </p>
        <?php
     } ?>
        <a class="MediumText" href="LettersAndLabels.php"><?php echo gettext('Letters and Mailing Labels'); ?></a>
        <br><?php echo gettext('Generate letters and mailing labels.'); ?>
        </p>
        <?php
        if (SessionUser::getUser()->isbUSAddressVerificationEnabled()) {
            echo '<p>';
            echo '<a class="MediumText" href="USISTAddressVerification.php">';
            echo gettext('US Address Verification Report')."</a><br>\n";
            echo gettext('Generate report comparing all US family addresses '.
              'with United States Postal Service Standard Address Format.<br>')."\n";
        }
        ?>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
      <div class="box box-primary">
      <div class="box-header with-border">
        <i class="fa fa-bar-chart-o"></i>

        <h3 class="box-title"><?= gettext('People Classification') ?></h3>

        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
          <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <table class="table table-condensed">
        <tr>
          <th><?= gettext('Classification') ?></th>
          <th>% <?= gettext('of People') ?></th>
          <th style="width: 40px"><?= gettext('Count') ?></th>
        </tr>
        <?php foreach ($personStats as $key => $value) {
            ?>
          <tr>
            <td><a href='SelectList.php?Sort=name&Filter=&mode=person&Classification=<?= $classifications[$key] ?>'><?= gettext($key) ?></a></td> 
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
<div class="row">
  <div class="col-lg-6">
    <div class="box box-primary">
      <div class="box-header with-border">
        <i class="fa fa-pie-chart"></i>

        <h3 class="box-title"><?= gettext('Family Roles') ?></h3>

        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
          <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <div class="box-body no-padding">
        <table class="table table-condensed">
          <tr>
            <th><?= gettext('Role / Gender') ?></th>
            <th>% <?= gettext('of People') ?></th>
            <th style="width: 40px"><?= gettext('Count') ?></th>
          </tr>
            <?php 
              $genderlist = GenderTypeQuery::create()->find();
              $genPop = PersonQuery::create()->leftJoinFamily()->where("family_fam.fam_DateDeactivated is NULL")->count();
              foreach ($demographicStats as $demStat) {
                unset($countGender);
                foreach ($genderlist as $gender) {
                  // GenderName, Count, GenderID, demStatId, demStatName 
                  $countGender[] = [$gender->getName(), PersonQuery::create()->leftJoinFamily()->where("family_fam.fam_DateDeactivated is NULL")->filterByFmrId($demStat->getOptionID())->filterByGender($gender->getId())->count(), $gender->getId(), $demStat->getOptionID(), $demStat->getOptionName()];
                }
  
                foreach($countGender as $row) {
                  if ($row[1] != "0") {
                    echo "<tr>";
                    echo "<td><a href='SelectList.php?mode=person&Gender=" . $row[2] . "&FamilyRole=" . $row[3] . "'>" . $row[4] . " - " . gettext($row[0]) . "</a></td>";
                    echo "<td>";
                    echo "<div class='progress progress-xs progress-striped active'>";
                    echo "<div class='progress-bar progress-bar-success' style='width:" . round(($row[1] / $genPop) * 100) . "%' title=" . round(($row[1]  / $genPop) * 100) . "%></div>";
                    echo "</div>";
                    echo "</td>";
                    echo "<td><span class='badge bg-green'>" . $row[1] . "</span></td>";
                    echo "</tr>";
                  } 
                }
            }

              // find Unknown family role
              unset($countGender);
              foreach ($genderlist as $gender) {
                // GenderName, Count, GenderID, demStatId, demStatName 
                $countGender[] = [$gender->getName(), PersonQuery::create()->leftJoinFamily()->where("family_fam.fam_DateDeactivated is NULL")->filterByFmrId("0")->filterByGender($gender->getId())->count(), $gender->getId(), 0, "Unknown"];
              }
              foreach($countGender as $row) {
                if ($row[1] != "0") {
                  echo "<tr>";
                  echo "<td><a href='SelectList.php?mode=person&Gender=" . $row[2] . "&FamilyRole=" . $row[3] . "'>" . $row[4] . " - " . gettext($row[0]) . "</a></td>";
                  echo "<td>";
                  echo "<div class='progress progress-xs progress-striped active'>";
                  echo "<div class='progress-bar progress-bar-success' style='width:" . round(($row[1] / $genPop) * 100) . "%' title=" . round(($row[1]  / $genPop) * 100) . "%></div>";
                  echo "</div>";
                  echo "</td>";
                  echo "<td><span class='badge bg-green'>" . $row[1] . "</span></td>";
                  echo "</tr>";
                } 
              }
                ?>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">

    <div class="box box-info">
      <div class="box-header">
        <i class="fa fa-address-card-o"></i>

        <h3 class="box-title"><?= gettext('Gender Demographics') ?></h3>

        <div class="box-tools pull-right">
          <div id="gender-donut-legend" class="chart-legend"></div>
        </div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <canvas id="gender-donut" style="height:250px"></canvas>
      </div>
    </div>
    <div class="box box-info">
      <div class="box-header">
        <i class="fa fa-birthday-cake"></i>
        <h3 class="box-title"><?= gettext('Age Histogram') ?></h3>

        <div class="box-tools pull-right">
          <div id="age-stats-bar-legend" class="chart-legend"></div>
        </div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <canvas id="age-stats-bar" style="height:250px"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- this page specific inline scripts -->
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        //-------------
        //- PIE CHART -
        //-------------
        // Get context with jQuery - using jQuery's .get() method.
        var PieData = [
            <?php
            $RoleChild = SystemConfig::getValue('sDirRoleChild');

              // get list of children
              $personList = PersonQuery::create()->leftJoinFamily()->where("family_fam.fam_DateDeactivated is NULL")
                ->withColumn("COUNT(per_ID)", "Numb")
                ->filterByFmrId($RoleChild)
                ->groupByGender()
                ->groupByFmrId()
                ->orderByGender()
                ->orderByFmrId()
                ->find();
              
              foreach($personList as $person) {
                echo "{value: " . $person->getNumb() . ", label: 'Child - " . gettext($person->getGenderName()) . "' },";
              }

              // get list of adults
              $personList = PersonQuery::create()->leftJoinFamily()->where("family_fam.fam_DateDeactivated is NULL")
                ->withColumn("COUNT(per_ID)", "Numb")
                ->filterByFmrId(!$RoleChild)
                ->groupByGender()
                ->groupByFmrId()
                ->orderByGender()
                ->orderByFmrId()
                ->find();

              foreach($personList as $person) {
                echo "{value: " . $person->getNumb() . ", label: 'Adult - " . gettext($person->getGenderName()) . "' },";
              }
            ?>
        ];
        var pieOptions = {

            //String - Point label font colour
            pointLabelFontColor: "#666",

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
            maintainAspectRatio: true,
            //String - A legend template
            legendTemplate: "<% for (var i=0; i<segments.length; i++){%><div style=\"color: white;padding-right: 4px;padding-left: 2px;background-color:<%=segments[i].fillColor%>\"><%if(segments[i].label){%><%=segments[i].label%><%}%></div> <%}%></ul>"
        };

        var pieChartCanvas = $("#gender-donut").get(0).getContext("2d");
        var pieChart = new Chart(pieChartCanvas);


        //Create pie or douhnut chart
        // You can switch between pie and douhnut using the method below.
        pieChart = pieChart.Doughnut(PieData, pieOptions);

        //then you just need to generate the legend
        var legend = pieChart.generateLegend();

        //and append it to your page somewhere
        $('#gender-donut-legend').append(legend);
        var ageLabels = <?php
          echo json_encode(array_keys($ageStats));
        ?>;
        var ageValues = <?php
          echo json_encode(array_values($ageStats));
        ?>;
        var ageStatsCanvas = $("#age-stats-bar").get(0).getContext("2d");
        var AgeChart = new Chart(ageStatsCanvas);
        AgeChart.Bar(
          {
            labels: ageLabels,
            datasets: [ {
                label: "Ages",
                data: ageValues,
                backgroundColor: 'rgba(255, 99, 132, 1)',
            }]
          },
          {
            scales: {
              xAxes: [{
                display: false,
                barPercentage: 1.3,
                ticks: {
                  max: 3,
                }
             }],
              yAxes: [{
                ticks: {
                  beginAtZero:true
                }
              }]
            }
          }
        );
    });
</script>

<?php require 'Include/Footer.php' ?>
