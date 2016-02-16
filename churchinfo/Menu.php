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

require_once "service/DashboardService.php";

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
            <a href="<?= $sURLPath."/" ?>FamilyList.php" class="small-box-footer">
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
            <a href="<?= $sURLPath."/" ?>SelectList.php?mode=person" class="small-box-footer">
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
            <a href="<?= $sURLPath."/"; ?>sundayschool\SundaySchoolDashboard.php" class="small-box-footer">

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
            <a href="<?= $sURLPath."/" ?>Reports\SundaySchoolClassList.php" class="small-box-footer">
                More info <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
</div><!-- /.row -->

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
                            <td><a href="FamilyView.php?FamilyID=<?php echo $row['fam_ID'];?>"><?php echo $row['fam_Name'];?></a></td>
                            <td><?php if ($row['fam_Address1'] != "") { echo $row['fam_Address1']. ", ".$row['fam_City']." ".$row['fam_Zip']; }?></td>
                            <td><?php echo FormatDate($row['fam_DateEntered'], false);?></td>
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
                                <td><a href="FamilyView.php?FamilyID=<?php echo $row['fam_ID'];?>"><?php echo $row['fam_Name'];?></a></td>
                                <td><?php echo $row['fam_Address1']. ", ".$row['fam_City']." ".$row['fam_Zip'];?></td>
                                <td><?php echo FormatDate($row['fam_DateLastEdited'], false);?></td>
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
                            <a class="users-list" href="PersonView.php?PersonID=<?= $row['per_ID'];?>">
                            <img src="<?= $personService->getPhoto($row['per_ID']); ?>" alt="User Image" class="user-image" width="85" height="85" /><br/>
                            <?= $row['per_FirstName']." ".substr($row['per_LastName'],0,1);?></a>
                            <span class="users-list-date"><?= FormatDate($row['per_DateEntered'], false);?></span>
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
                                <a class="users-list" href="PersonView.php?PersonID=<?= $row['per_ID'];?>">
                                <img src="<?= $personService->getPhoto($row['per_ID']); ?>" alt="User Image" class="user-image" width="85" height="85" /><br/>
                                <?= $row['per_FirstName']." ".substr($row['per_LastName'],0,1);?></a>
                                <span class="users-list-date"><?= FormatDate($row['per_DateLastEdited'], false);?></span>
                            </li>
                        <?php } ?>
                    </ul>
                    <!-- /.users-list -->
                </div>
            </div>
        </div>
    </div>
</div>


<?php
require 'Include/Footer.php';
?>
