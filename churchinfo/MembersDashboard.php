<?php
/**
 * User: George Dawoud
 * Date: 1/17/2016
 * Time: 8:01 AM
 */
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/PersonFunctions.php';

require_once "Service/DashboardService.php";

// Set the page title
$sPageTitle = "Members Dashboard";

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
?>

<!-- this page specific styles -->
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/chartjs/Chart.min.js"></script>

<!-- Default box -->
<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">Members Functions</h3>
    </div>
    <div class="box-body">
        <a href="SelectList.php?mode=person" class="btn btn-app"><i class="fa fa-user"></i><?= gettext("All People") ?></a>
        <a href="OptionManager.php?mode=classes" class="btn btn-app"><i class="fa fa-gears"></i><?= gettext("Classifications Manager") ?></a>
        <br/>
        <a href="FamilyList.php" class="btn btn-app"><i class="fa fa-users"></i><?= gettext("All Families") ?></a>
        <a href="OptionManager.php?mode=famroles" class="btn btn-app"><i class="fa fa-cubes"></i><?= gettext("Family Roles") ?></a>
        <a href="GeoPage.php" class="btn btn-app"><i class="fa fa-globe"></i><?= gettext("Family Geographic") ?></a>
        <a href="MapUsingGoogle.php?GroupID=-1" class="btn btn-app"><i class="fa fa-map"></i><?= gettext("Family Map") ?></a>
        <a href="UpdateAllLatLon.php" class="btn btn-app"><i class="fa fa-map-pin"></i><?= gettext("Update All Family Coordinates") ?></a>
        <?php if ($_SESSION['bAdmin']) { ?>
            <br/>
            <a href="VolunteerOpportunityEditor.php" class="btn btn-app"><i class="fa fa-bullhorn"></i><?= gettext("Volunteer Opportunities") ?></a>
            <a href="PersonCustomFieldsEditor.php" class="btn btn-app"><i class="fa fa-gear"></i><?= gettext("Custom Person Fields") ?></a>
            <a href="FamilyCustomFieldsEditor.php" class="btn btn-app"><i class="fa fa-gear"></i><?= gettext("Custom Family Fields") ?></a>
        <?php } ?>

    </div>
</div>
<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>
                    <?= $familyCount['familyCount'] ?>
                </h3>
                <p>
                    Families
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person-stalker"></i>
            </div>
            <a href="<?= $sRootPath . "/" ?>FamilyList.php" class="small-box-footer">
                See all Families <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3>
                    <?= $personCount['personCount'] ?>
                </h3>
                <p>
                    People
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person"></i>
            </div>
            <a href="<?= $sRootPath . "/" ?>SelectList.php?mode=person" class="small-box-footer">
                See All People <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>
                    <?= $groupStats['sundaySchoolkids'] ?>
                </h3>
                <p>
                    Sunday School Kids
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-child"></i>
            </div>
            <a href="<?= $sRootPath ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
                More info <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
  <div class="col-lg-3">
    <!-- small box -->
    <div class="small-box bg-red">
      <div class="inner">
        <h3>
          <?= $groupStats['groups'] -$groupStats['sundaySchoolClasses']  ?>
        </h3>
        <p>
          Groups
        </p>
      </div>
      <div class="icon">
        <i class="fa fa-gg"></i>
      </div>
      <a href="<?= $sRootPath ?>/grouplist" class="small-box-footer">
        More info <i class="fa fa-arrow-circle-right"></i>
      </a>
    </div>
  </div><!-- ./col -->

</div><!-- /.row -->
<div class="row">
    <div class="col-lg-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <i class="fa fa-pie-chart"></i>

                <h3 class="box-title">Family Roles</h3>

                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="box-body no-padding">
                <table class="table table-condensed">
                    <tr>
                        <th>Role / Gender</th>
                        <th>% of Members</th>
                        <th style="width: 40px">Count</th>
                    </tr>
                    <? foreach ($demographicStats as $key => $value) { ?>
                    <tr>
                        <td><?= $key ?></td>
                        <td>
                            <div class="progress progress-xs progress-striped active">
                                <div class="progress-bar progress-bar-success" style="width: <?= round($value/$personCount['personCount']*100) ?>%"></div>
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
                <h3 class="box-title">People Classification</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i>
                    </button>
                    <button type="button" class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <table class="table table-condensed">
                <tr>
                    <th>Classification</th>
                    <th>% of Members</th>
                    <th style="width: 40px">Count</th>
                </tr>
                <? foreach ($personStats as $key => $value) { ?>
                    <tr>
                        <td><?= $key ?></td>
                        <td>
                            <div class="progress progress-xs progress-striped active">
                                <div class="progress-bar progress-bar-success" style="width: <?= round($value/$personCount['personCount']*100) ?>%"></div>
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
                <h3 class="box-title">Gender Demographics</h3>
                <div class="box-tools pull-right">
                    <div id="gender-donut-legend" class="chart-legend"></div>
                </div>
            </div><!-- /.box-header -->
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
                    echo "{value: ". $row['numb'] ." , color: \"#003399\", highlight: \"#3366ff\", label: \"Men\" },";
                }
                if ($row['per_Gender'] == 2 ) {
                    echo "{value: ". $row['numb'] ." , color: \"#9900ff\", highlight: \"#ff66cc\", label: \"Women\"},";
                }
            }
            while ($row = mysql_fetch_array($rsKidsGender)) {
            if ($row['per_Gender'] == 1 ) {
                    echo "{value: ". $row['numb'] ." , color: \"#3399ff\", highlight: \"#99ccff\", label: \"Boys\"},";
                }
                if ($row['per_Gender'] == 2 ) {
                    echo "{value: ". $row['numb'] ." , color: \"#009933\", highlight: \"#99cc00\", label: \"Girls\",}";
                }
            }
            ?>
        ];
        var pieOptions = {

            //String - Point label font colour
            pointLabelFontColor : "#666",

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
