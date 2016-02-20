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
require 'Include/PersonFunctions.php';
require 'service/FinancialService.php';

$financialService = new FinancialService();

require_once "Service/DashboardService.php";

$sSQL = "select * from family_fam order by fam_DateLastEdited desc  LIMIT 10;";
$rsLastFamilies = RunQuery($sSQL);

$sSQL = "select * from family_fam where fam_DateLastEdited is null order by fam_DateEntered desc LIMIT 10;";
$rsNewFamilies = RunQuery($sSQL);

$sSQL = "select * from person_per order by per_DateLastEdited desc  LIMIT 10;";
$rsLastPeople = RunQuery($sSQL);

$sSQL = "select * from person_per where per_DateLastEdited is null order by per_DateEntered desc LIMIT 10;";
$rsNewPeople = RunQuery($sSQL);

$dashboardService = new DashboardService();
$personCount = $dashboardService->getPersonCount();
$familyCount = $dashboardService->getFamilyCount();
$sundaySchoolStats = $dashboardService->getSundaySchoolStats();


// Set the page title
$sPageTitle = "Welcome to <b>Church</b>CRM";

require 'Include/Header.php';
?>
<script src="<?= $sRootPath ?>/skin/adminlte/plugins/chartjs/Chart.min.js"></script>

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
                    Families
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person-stalker"></i>
            </div>
            <a href="<?= $sRootPath ?>/FamilyList.php" class="small-box-footer">
                See all Families <i class="fa fa-arrow-circle-right"></i>
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
                    People
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person"></i>
            </div>
            <a href="<?= $sRootPath ?>/SelectList.php?mode=person" class="small-box-footer">
                See All People <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>
                    <?= $sundaySchoolStats['classes'] ?>
                </h3>
                <p>
                    Sunday School Classes
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-university"></i>
            </div>
            <a href="<?= $sRootPath ?>/sundayschool/SundaySchoolDashboard.php" class="small-box-footer">
                More info <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3>
                    TBD
                </h3>
                <p>
                    Groups
                </p>
            </div>
            <div class="icon">
                <i class="fa fa-child"></i>
            </div>
            <a href="<?= $sRootPath ?>/Reports\SundaySchoolClassList.php" class="small-box-footer">
                More info <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
</div><!-- /.row -->

<?php 
if ($_SESSION['bFinance']) // If the user has Finance permissions, then let's display the deposit line chart
{ 
?>
<div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12">
        <div class="box box-info">
            <div class="box-header">
                <i class="ion ion-cash"></i>
                <h3 class="box-title">Deposit Tracking</h3>
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
    <div class="col-lg-6 col-md-5 col-sm-4">
        <div class="box box-solid">
            <div class="box-header">
                <i class="ion ion-person-add"></i>
                <h3 class="box-title">Latest Families</h3>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th data-field="name">Family Name</th>
                            <th data-field="address">Address</th>
                            <th data-field="city">Created</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysql_fetch_array($rsNewFamilies)) { ?>
                        <tr>
                            <td><a href="FamilyView.php?FamilyID=<?= $row['fam_ID'] ?>"><?= $row['fam_Name'] ?></a></td>
                            <td><?php if ($row['fam_Address1'] != "") { echo $row['fam_Address1']. ", ".$row['fam_City']." ".$row['fam_Zip']; } ?></td>
                            <td><?= FormatDate($row['fam_DateEntered'], false) ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-md-5 col-sm-4">
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-check"></i>
                <h3 class="box-title">Updated Families</h3>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th data-field="name">Family Name</th>
                            <th data-field="address">Address</th>
                            <th data-field="city">Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysql_fetch_array($rsLastFamilies)) { ?>
                            <tr>
                                <td><a href="FamilyView.php?FamilyID=<?= $row['fam_ID'] ?>"><?= $row['fam_Name'] ?></a></td>
                                <td><?= $row['fam_Address1']. ", ".$row['fam_City']." ".$row['fam_Zip'] ?></td>
                                <td><?= FormatDate($row['fam_DateLastEdited'], false) ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-6 col-sm-3">
        <div class="box box-solid">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Latest Members</h3>
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
                        <?php while ($row = mysql_fetch_array($rsNewPeople)) { ?>
                        <li>
                            <a class="users-list" href="PersonView.php?PersonID=<?= $row['per_ID'] ?>">
                            <img src="<?= $personService->getPhoto($row['per_ID']); ?>" alt="User Image" class="user-image" width="85" height="85" /><br/>
                            <?= $row['per_FirstName']." ".substr($row['per_LastName'],0,1) ?></a>
                            <span class="users-list-date"><?= FormatDate($row['per_DateEntered'], false) ?></span>
                        </li>
                        <?php } ?>
                    </ul>
                    <!-- /.users-list -->
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-md-6 col-sm-3">
        <div class="box box-solid">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Updated Members</h3>
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
                        <?php while ($row = mysql_fetch_array($rsLastPeople)) { ?>
                            <li>
                                <a class="users-list" href="PersonView.php?PersonID=<?= $row['per_ID'] ?>">
                                <img src="<?= $personService->getPhoto($row['per_ID']) ?>" alt="User Image" class="user-image" width="85" height="85" /><br/>
                                <?= $row['per_FirstName']." ".substr($row['per_LastName'],0,1) ?></a>
                                <span class="users-list-date"><?= FormatDate($row['per_DateLastEdited'], false) ?></span>
                            </li>
                        <?php } ?>
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
if ($_SESSION['bFinance']) // If the user has Finance permissions, then let's display the deposit line chart
{ 
?>
    //---------------
    //- LINE CHART  -
    //---------------
    var lineDataRaw = <?= $financialService->getDepositJSON($financialService->getDeposits()) ?>;

    var lineData = {
        labels: [],
        datasets: [
            {
                data: []
            }     
        ]
    };
    
    $.each(lineDataRaw.deposits, function(i, val) {
        lineData.labels.push(val.dep_Date);
        lineData.datasets[0].data.push(val.dep_Total);
    });

    var lineChartCanvas = $("#deposit-lineGraph").get(0).getContext("2d");

    var lineChart = new Chart(lineChartCanvas).Line(lineData);
<?php 
}  //END IF block for Finance permissions to include JS for Deposit Chart
 ?>

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


<?php
require 'Include/Footer.php';
?>

