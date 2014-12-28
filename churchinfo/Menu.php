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

$sSQL = "select
        (select count(*) from family_fam ) as familyCount,
        (select count(*) from person_per ) as PersonCount,
        (select count(*) from group_grp where grp_Type = 4 ) as SundaySchoolClasses,
        (select count(*) from person_per,`group_grp` grp, `person2group2role_p2g2r` person_grp   where person_grp.p2g2r_rle_ID = 2 and grp_Type = 4 and grp.grp_ID = person_grp.p2g2r_grp_ID  and person_grp.p2g2r_per_ID = per_ID) as SundaySchoolKidsCount
        from dual ;";
$rsQuickStat = RunQuery($sSQL);


// Set the page title
$sPageTitle = gettext('Welcome to ChurchInfo');

require 'Include/Header.php';
?>
<!-- this page specific styles -->
<script src="http://cdn.oesmith.co.uk/morris-0.4.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.1.2/raphael-min.js"></script>


<div class="row">
    <div class="col-lg-12 col-md-6 col-sm-4">
        <div class="box box-solid">
            <div class="box-body clearfix">
                <i class="fa fa-search"></i><input type="text" class="search searchPerson" placeholder="Search..." onfocus="ClearFieldOnce(this);"/>

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
<?php while ($row = mysql_fetch_array($rsQuickStat)) { ?>
<!-- Small boxes (Stat box) -->
<div class="row">
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <h3>
                    <?php echo $row['familyCount'];?>
                </h3>
                <p>
                    Families
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person-stalker"></i>
            </div>
            <a href="<?php echo $sURLPath."/"; ?>FamilyList.php" class="small-box-footer">
                See all Families <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <h3>
                    <?php echo $row['PersonCount'];?>
                </h3>
                <p>
                    Members
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-person-add"></i>
            </div>
            <a href="<?php echo $sURLPath."/"; ?>SelectList.php?mode=person" class="small-box-footer">
                See All Member <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <h3>
                    <?php echo $row['SundaySchoolClasses'];?>
                </h3>
                <p>
                    Sunday School Classes
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-university"></i>
            </div>
            <a href="<?php echo $sURLPath."/"; ?>SundaySchool.php" class="small-box-footer">
                More info <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <h3>
                    <?php echo $row['SundaySchoolKidsCount'];?>
                </h3>
                <p>
                    Sunday School Kids
                </p>
            </div>
            <div class="icon">
                <i class="ion ion-happy"></i>
            </div>
            <a href="<?php echo $sURLPath."/"; ?>Reports\SundaySchoolClassList.php" class="small-box-footer">
                More info <i class="fa fa-arrow-circle-right"></i>
            </a>
        </div>
    </div><!-- ./col -->
</div><!-- /.row -->
<?php } ?>
<div class="row">
    <div class="col-lg-6 col-md-5 col-sm-4">
        <div class="box box-solid">
            <div class="box-header">
                <i class="ion ion-person-add"></i>
                <h3 class="box-title">New Families</h3>
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
    <div class="col-lg-3 col-md-3 col-sm-3">
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-plus"></i>
                <h3 class="box-title">New People</h3>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
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
        <div class="box box-solid">
            <div class="box-header">
                <i class="fa fa-check"></i>
                <h3 class="box-title">Modified People</h3>
            </div><!-- /.box-header -->
            <div class="box-body clearfix">
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
    <div class="col-lg-3 col-md-3 col-sm-3">
        <div class="box box-solid">
            <div class="box-header">
                <i class="ion ion-android-contacts"></i>
                <h3 class="box-title">Gender Demographics</h3>
            </div><!-- /.box-header -->
            <div class="main-box-body clearfix">
                <div id="gender-donut"></div>
            </div>
        </div>
    </div>
</div>

<!-- this page specific inline scripts -->
<script src="http://cdn.oesmith.co.uk/morris-0.4.1.min.js"></script>
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
