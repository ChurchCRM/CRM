<?php
/**
 * User: George Dawoud
 * Date: 1/17/2016
 * Time: 8:01 AM
 */
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Service\DashboardService;

// Set the page title
$sPageTitle = gettext("Members Dashboard");

require 'Include/Header.php';

$dashboardService = new DashboardService();
$personCount = $dashboardService->getPersonCount();
$personStats = $dashboardService->getPersonStats();
$familyCount = $dashboardService->getFamilyCount();
$groupStats = $dashboardService->getGroupStats();
$demographicStats = $dashboardService->getDemographic();

$sSQL = "select count(*) as numb, per_Gender from person_per where per_Gender in (1,2) and per_fmr_ID in (1,2) group by per_Gender ;";
$rsAdultsGender = RunQuery($sSQL);

$sSQL = "select count(*) as numb, per_Gender from person_per where per_Gender in (1,2) and per_fmr_ID not in (1,2) group by per_Gender ;";
$rsKidsGender = RunQuery($sSQL);

$sSQL = "select lst_OptionID,lst_OptionName from list_lst where lst_ID = 1;";
$rsClassification = RunQuery($sSQL);
$classifications = new stdClass();
while (list ($lst_OptionID,$lst_OptionName) = mysql_fetch_row($rsClassification))
{
  $classifications->$lst_OptionName = $lst_OptionID;

}

$sSQL = "SELECT per_Email, fam_Email, lst_OptionName as virt_RoleName FROM person_per
          LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
          INNER JOIN list_lst on lst_ID=1 AND per_cls_ID = lst_OptionID
          WHERE per_ID NOT IN
          (SELECT per_ID
              FROM person_per
              INNER JOIN record2property_r2p ON r2p_record_ID = per_ID
              INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email')";

$rsEmailList = RunQuery($sSQL);
$sEmailLink = '';
while (list ($per_Email, $fam_Email, $virt_RoleName) = mysql_fetch_row($rsEmailList))
{
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, False);
    if ($sEmail)
    {
        /* if ($sEmailLink) // Don't put delimiter before first email
            $sEmailLink .= $sMailtoDelimiter; */
        // Add email only if email address is not already in string
        if (!stristr($sEmailLink, $sEmail))
        {
          $sEmailLink .= $sEmail .= $sMailtoDelimiter;
          $roleEmails->$virt_RoleName .= $sEmail.= $sMailtoDelimiter;
        }
    }
}

?>

<!-- Default box -->
<div class="box">
  <div class="box-header with-border">
    <h3 class="box-title"><?= gettext("Members Functions") ?></h3>
  </div>
  <div class="box-body">
    <a href="SelectList.php?mode=person" class="btn btn-app"><i class="fa fa-user"></i><?= gettext("All People") ?></a>
    <a href="OptionManager.php?mode=classes" class="btn btn-app"><i
        class="fa fa-gears"></i><?= gettext("Classifications Manager") ?></a>
    <?php
    if ($sEmailLink)
    {
      // Add default email if default email has been set and is not already in string
      if ($sToEmailAddress != '' && $sToEmailAddress != 'myReceiveEmailAddress'
                                 && !stristr($sEmailLink, $sToEmailAddress))
          $sEmailLink .= $sMailtoDelimiter . $sToEmailAddress;
      $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
       if ($bEmailMailto) { // Does user have permission to email groups
      // Display link
       ?>
        <div class="btn-group">
          <a  class="btn btn-app" href="mailto:<?= mb_substr($sEmailLink,0,-3) ?>"><i class="fa fa-send-o"></i><?= gettext('Email All')?></a>
          <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
           <?php generateGroupRoleEmailDropdown($roleEmails,"mailto:") ?>
          </ul>
        </div>
       <div class="btn-group">
          <a class="btn btn-app" href="mailto:?bcc=<?= mb_substr($sEmailLink,0,-3) ?>"><i class="fa fa-send"></i><?=gettext('Email All (BCC)') ?></a>
           <button type="button" class="btn btn-app dropdown-toggle" data-toggle="dropdown" >
            <span class="caret"></span>
            <span class="sr-only">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu" role="menu">
           <?php generateGroupRoleEmailDropdown($roleEmails,"mailto:?bcc=") ?>
          </ul>
        </div>
       <?php
       }
      }
     ?>
    <br/>
    <a href="FamilyList.php" class="btn btn-app"><i class="fa fa-users"></i><?= gettext("All Families") ?></a>
    <a href="OptionManager.php?mode=famroles" class="btn btn-app"><i
        class="fa fa-cubes"></i><?= gettext("Family Roles") ?></a>
    <a href="GeoPage.php" class="btn btn-app"><i class="fa fa-globe"></i><?= gettext("Family Geographic") ?></a>
    <a href="MapUsingGoogle.php?GroupID=-1" class="btn btn-app"><i class="fa fa-map"></i><?= gettext("Family Map") ?>
    </a>
    <a href="UpdateAllLatLon.php" class="btn btn-app"><i
        class="fa fa-map-pin"></i><?= gettext("Update All Family Coordinates") ?></a>
    <?php if ($_SESSION['bAdmin']) { ?>
      <br/>
      <a href="VolunteerOpportunityEditor.php" class="btn btn-app"><i
          class="fa fa-bullhorn"></i><?= gettext("Volunteer Opportunities") ?></a>
      <a href="PersonCustomFieldsEditor.php" class="btn btn-app"><i
          class="fa fa-gear"></i><?= gettext("Custom Person Fields") ?></a>
      <a href="FamilyCustomFieldsEditor.php" class="btn btn-app"><i
          class="fa fa-gear"></i><?= gettext("Custom Family Fields") ?></a>
    <?php } ?>

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
          <?= gettext("Families") ?>
        </p>
      </div>
      <div class="icon">
        <i class="ion ion-person-stalker"></i>
      </div>
      <a href="<?= $sRootPath . "/" ?>FamilyList.php" class="small-box-footer">
        <?= gettext("See all Families") ?> <i class="fa fa-arrow-circle-right"></i>
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
          <?= gettext("People") ?>
        </p>
      </div>
      <div class="icon">
        <i class="ion ion-person"></i>
      </div>
      <a href="<?= $sRootPath . "/" ?>SelectList.php?mode=person" class="small-box-footer">
        <?= gettext("See All People") ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
  <!-- ./col -->
  <div class="col-lg-3 col-md-6 col-sm-6">
    <!-- small box -->
    <div class="small-box bg-yellow">
      <div class="inner">
        <h3>
          <?= $groupStats['sundaySchoolkids'] ?>
        </h3>

        <p>
          <?= gettext("Sunday School Kids") ?>
        </p>
      </div>
      <div class="icon">
        <i class="fa fa-child"></i>
      </div>
      <a href="<?= $sRootPath ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
        <?= gettext("More info") ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
  <!-- ./col -->
  <div class="col-lg-3 col-md-6 col-sm-6">
    <!-- small box -->
    <div class="small-box bg-red">
      <div class="inner">
        <h3>
          <?= $groupStats['groups'] - $groupStats['sundaySchoolClasses'] ?>
        </h3>

        <p>
          <?= gettext("Groups") ?>
        </p>
      </div>
      <div class="icon">
        <i class="fa fa-gg"></i>
      </div>
      <a href="<?= $sRootPath ?>/grouplist" class="small-box-footer">
        <?= gettext("More info") ?> <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div>
  <!-- ./col -->

</div><!-- /.row -->
<div class="row">
  <div class="col-lg-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title"><?= gettext("Reports") ?></h3>
      </div>
      <div class="box-body">
        <a class="MediumText" href="GroupReports.php"><?php echo gettext('Reports on groups and roles'); ?></a>
        <br>
        <?php echo gettext('Report on group and roles selected (it may be a multi-page PDF).'); ?>
        </p>
        <?php if ($bCreateDirectory) {
          ?>
          <p><a class="MediumText"
                href="DirectoryReports.php"><?= gettext('Members Directory') ?></a><br><?= gettext('Printable directory of all members, grouped by family where assigned') ?>
          </p>
        <?php } ?>
        <a class="MediumText" href="LettersAndLabels.php"><?php echo gettext('Letters and Mailing Labels'); ?></a>
        <br><?php echo gettext('Generate letters and mailing labels.'); ?>
        </p>
        <?php
        if ($bUSAddressVerification) {
          echo '<p>';
          echo '<a class="MediumText" href="USISTAddressVerification.php">';
          echo gettext('US Address Verification Report') . "</a><br>\n";
          echo gettext('Generate report comparing all US family addresses ' .
              'with United States Postal Service Standard Address Format.<br>') . "\n";
        }
        ?>
      </div>
    </div>
  </div>
</div>
<div class="row">
  <div class="col-lg-6">
    <div class="box box-primary">
      <div class="box-header with-border">
        <i class="fa fa-pie-chart"></i>

        <h3 class="box-title"><?= gettext("Family Roles") ?></h3>

        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
          <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <div class="box-body no-padding">
        <table class="table table-condensed">
          <tr>
            <th><?= gettext("Role / Gender") ?></th>
            <th>% <?= gettext("of Members") ?></th>
            <th style="width: 40px"><?= gettext("Count") ?></th>
          </tr>
          <? foreach ($demographicStats as $key => $value) { ?>
            <tr>
              <td><?= gettext($key) ?></td>
              <td>
                <div class="progress progress-xs progress-striped active">
                  <div class="progress-bar progress-bar-success"
                       style="width: <?= round($value / $personCount['personCount'] * 100) ?>%"></div>
                </div>
              </td>
              <td><span class="badge bg-green"><?= $value ?></span></td>
            </tr>
          <? } ?>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="box box-primary">
      <div class="box-header with-border">
        <i class="fa fa-bar-chart-o"></i>

        <h3 class="box-title"><?= gettext("People Classification") ?></h3>

        <div class="box-tools pull-right">
          <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
          </button>
          <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
        </div>
      </div>
      <table class="table table-condensed">
        <tr>
          <th><?= gettext("Classification") ?></th>
          <th>% <?= gettext("of Members") ?></th>
          <th style="width: 40px"><?= gettext("Count") ?></th>
        </tr>
        <? foreach ($personStats as $key => $value) { ?>
          <tr>
            <td><a href='SelectList.php?Sort=name&Filter=&mode=person&Classification=<?= $classifications->$key ?>'><?= gettext($key) ?></a></td>
            <td>
              <div class="progress progress-xs progress-striped active">
                <div class="progress-bar progress-bar-success"
                     style="width: <?= round($value / $personCount['personCount'] * 100) ?>%"></div>
              </div>
            </td>
            <td><span class="badge bg-green"><?= $value ?></span></td>
          </tr>
        <? } ?>
      </table>
      <!-- /.box-body-->
    </div>
    <div class="box box-info">
      <div class="box-header">
        <i class="ion ion-android-contacts"></i>

        <h3 class="box-title"><?= gettext("Gender Demographics") ?></h3>

        <div class="box-tools pull-right">
          <div id="gender-donut-legend" class="chart-legend"></div>
        </div>
      </div>
      <!-- /.box-header -->
      <div class="box-body">
        <canvas id="gender-donut" style="height:250px"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- this page specific inline scripts -->
<script>

  //-------------
  //- PIE CHART -
  //-------------
  // Get context with jQuery - using jQuery's .get() method.
  var PieData = [
    <?php while ($row = mysql_fetch_array($rsAdultsGender)) {
        if ($row['per_Gender'] == 1 ) {
            echo "{value: ". $row['numb'] ." , color: \"#003399\", highlight: \"#3366ff\", label: \"".gettext("Men")."\" },";
        }
        if ($row['per_Gender'] == 2 ) {
            echo "{value: ". $row['numb'] ." , color: \"#9900ff\", highlight: \"#ff66cc\", label: \"".gettext("Women")."\"},";
        }
    }
    while ($row = mysql_fetch_array($rsKidsGender)) {
    if ($row['per_Gender'] == 1 ) {
            echo "{value: ". $row['numb'] ." , color: \"#3399ff\", highlight: \"#99ccff\", label: \"".gettext("Boys")."\"},";
        }
        if ($row['per_Gender'] == 2 ) {
            echo "{value: ". $row['numb'] ." , color: \"#009933\", highlight: \"#99cc00\", label: \"".gettext("Girls")."\",}";
        }
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
    legendTemplate: "<% for (var i=0; i<segments.length; i++){%><span style=\"color: white;padding-right: 4px;padding-left: 2px;background-color:<%=segments[i].fillColor%>\"><%if(segments[i].label){%><%=segments[i].label%><%}%></span> <%}%></ul>"
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
</script>

<?php require 'Include/Footer.php' ?>
