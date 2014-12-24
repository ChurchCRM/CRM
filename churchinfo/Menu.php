<?php
/*******************************************************************************
*
*  filename    : Menu.php
*  description : menu that appears after login, shows login attempts
*
*  http://www.churchdb.org/
*  Copyright 2001-2002 Phillip Hullquist, Deane Barker, Michael Wilt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*
*  ChurchInfo is free software; you can redistribute it and/or modify
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

$sSQL = "select * from family_fam order by fam_DateLastEdited desc  LIMIT 10;";
$rsLastFamilies = RunQuery($sSQL);

$sSQL = "select * from family_fam where fam_DateLastEdited is null order by fam_DateEntered desc LIMIT 10;";
$rsNewFamilies = RunQuery($sSQL);

$sSQL = "select * from person_per order by per_DateLastEdited desc  LIMIT 10;";
$rsLastPeople = RunQuery($sSQL);

$sSQL = "select * from person_per where per_DateLastEdited is null order by per_DateEntered desc LIMIT 10;";
$rsNewPeople = RunQuery($sSQL);

$sSQL = "select count(*) as numb, per_Gender from person_per where per_Gender in (1,2) and per_fmr_ID in (1,2) group by per_Gender ;";
$rsAdultsGender = RunQuery($sSQL);

$sSQL = "select count(*) as numb, per_Gender from person_per where per_Gender in (1,2) and per_fmr_ID not in (1,2) group by per_Gender ;";
$rsKidsGender = RunQuery($sSQL);


// Set the page title
$sPageTitle = gettext('Welcome to ChurchInfo');

require 'Include/Header.php';
?>
<!-- this page specific styles -->
<script src="http://cdn.oesmith.co.uk/morris-0.4.1.min.js"></script>
<script src="js/raphael-min.js"></script>


<div class="row">
    <div class="col-lg-12 col-md-6 col-sm-4">
        <div class="main-box clearfix">
            <header class="main-box-header clearfix">
            <div class="filter-block">
                <a href="PersonEditor.php" class="btn btn-primary">
                    <i class="fa fa-plus-circle fa-md"></i> Add Person
                </a>

                <a href="FamilyEditor.php" class="btn btn-primary">
                    <i class="fa fa-plus-circle fa-md"></i> Add Family
                </a>
            </div>
            </header>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-lg-6 col-md-5 col-sm-4">
        <div class="main-box clearfix">
            <header class="main-box-header clearfix">
                <h2 class="pull-left">New Families</h2>
            </header>

            <div class="main-box-body clearfix">
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
        <div class="main-box clearfix">
            <header class="main-box-header clearfix">
                <h2 class="pull-left">Updated Families</h2>
            </header>

            <div class="main-box-body clearfix">
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
    <div class="col-lg-3 col-md-3 col-sm-3">
        <div class="main-box clearfix">
            <header class="main-box-header clearfix">
                <h2 class="pull-left">New People</h2>
            </header>

            <div class="main-box-body clearfix">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th data-field="name">Name</th>
                            <th data-field="name">Created</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysql_fetch_array($rsNewPeople)) { ?>
                            <tr>
                                <td><a href="PersonView.php?PersonID=<?php echo $row['per_ID'];?>"><?php echo $row['per_FirstName']." ".$row['per_LastName'];?></a></td>
                                <td><?php echo FormatDate($row['per_DateEntered'], false);?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-3 col-sm-3">
        <div class="main-box clearfix">
            <header class="main-box-header clearfix">
                <h2 class="pull-left">Modified People</h2>
            </header>

            <div class="main-box-body clearfix">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                        <tr>
                            <th data-field="name">Name</th>
                            <th data-field="name">Updated</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($row = mysql_fetch_array($rsLastPeople)) { ?>
                            <tr>
                                <td><a href="PersonView.php?PersonID=<?php echo $row['per_ID'];?>"><?php echo $row['per_FirstName']." ".$row['per_LastName'];?></a></td>
                                <td><?php echo FormatDate($row['per_DateLastEdited'], false);?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 col-md-6 col-sm-3">
        <div class="main-box">
            <header class="main-box-header clearfix">
                <h2>Gender Demographics</h2>
            </header>

            <div class="main-box-body clearfix">
                <div id="gender-donut"></div>
            </div>
        </div>
    </div>
</div>

<!-- this page specific inline scripts -->
<script>
    Morris.Donut({
        element: 'gender-donut',
        data: [
            <?php while ($row = mysql_fetch_array($rsAdultsGender)) {
                if ($row['per_Gender'] == 1 ) {
                    echo "{label: \"Men\", value: ". $row['numb'] ."},";
                }
                if ($row['per_Gender'] == 2 ) {
                    echo "{label: \"Women\", value: ". $row['numb'] ."},";
                }
            }
            while ($row = mysql_fetch_array($rsKidsGender)) {
            if ($row['per_Gender'] == 1 ) {
                    echo "{label: \"Boys\", value: ". $row['numb'] ."},";
                }
                if ($row['per_Gender'] == 2 ) {
                    echo "{label: \"Girls\", value: ". $row['numb'] ."}";
                }
            }
            ?>
        ],
        colors: ['Navy', 'Pink', 'Blue', 'DarkMagenta'],
        resize: true
    });
</script>

<?php
require 'Include/Footer.php';
?>
