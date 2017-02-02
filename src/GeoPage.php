<?php
/*******************************************************************************
*
*  filename    : GeoPage.php
*  website     : http://www.churchcrm.io
*  copyright   : Copyright 2004-2005 Michael Wilt
*
*  Additional Contributors:
*  2006 Ed Davis
*
*
*  Copyright Contributors
*
*  ChurchCRM is free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  This file best viewed in a text editor with tabs stops set to 4 characters
*
******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

require 'Include/GeoCoder.php';

use ChurchCRM\dto\SystemConfig;

function CompareDistance($elem1, $elem2)
{
    if ($elem1['Distance'] > $elem2['Distance']) {
        return 1;
    } elseif ($elem1['Distance'] == $elem2['Distance']) {
        return 0;
    } else {
        return -1;
    }
}

function SortByDistance($array)
{
    $newArr = $array;
    usort($newArr, 'CompareDistance');

    return $newArr;
}

// Create an associated array of family information sorted by distance from
// a particular family.
function FamilyInfoByDistance($iFamily)
{
    // Handle the degenerate case of no family selected by just making the array without
    // distance and bearing data, and don't bother to sort it.
    if ($iFamily) {
        // Get info for the selected family
        $sSQL = 'SELECT fam_ID as selected_fam_ID, fam_Name as selected_fam_Name, fam_Address1 as selected_fam_Address1, fam_Address2 as selected_fam_Address2, fam_City as selected_fam_City, fam_State as selected_fam_State, fam_Zip as selected_fam_Zip, fam_Latitude as selected_fam_Latitude, fam_Longitude as selected_fam_Longitude from family_fam WHERE fam_ID='.$iFamily;
        $rsFamilies = RunQuery($sSQL);
        extract(mysqli_fetch_array($rsFamilies));
    }

    // Compute distance and bearing from the selected family to all other families
    $sSQL = 'SELECT fam_ID, fam_Name, fam_Address1,fam_Address2, fam_City, fam_State, fam_Zip, fam_Latitude, fam_Longitude from family_fam WHERE fam_DateDeactivated is  null';

    $rsFamilies = RunQuery($sSQL);
    while ($aFam = mysqli_fetch_array($rsFamilies)) {
        extract($aFam);

        if ($iFamily) {
            $results[$fam_ID]['Distance'] = floatval(LatLonDistance($selected_fam_Latitude, $selected_fam_Longitude, $fam_Latitude, $fam_Longitude));
            $results[$fam_ID]['Bearing'] = LatLonBearing($selected_fam_Latitude, $selected_fam_Longitude, $fam_Latitude, $fam_Longitude);
        }
        $results[$fam_ID]['fam_Name'] = $fam_Name;
        $results[$fam_ID]['fam_Address1'] = $fam_Address1;
        $results[$fam_ID]['fam_Address2'] = $fam_Address2;
        $results[$fam_ID]['fam_City'] = $fam_City;
        $results[$fam_ID]['fam_State'] = $fam_State;
        $results[$fam_ID]['fam_Zip'] = $fam_Zip;
        $results[$fam_ID]['fam_Latitude'] = $fam_Latitude;
        $results[$fam_ID]['fam_Longitude'] = $fam_Longitude;
        $results[$fam_ID]['fam_ID'] = $fam_ID;
    }

    if ($iFamily) {
        $resultsByDistance = SortByDistance($results);
    } else {
        $resultsByDistance = $results;
    }

    return $resultsByDistance;
}

/* End of functions ... code starts here */

//Set the page title
$sPageTitle = gettext('Family Geographic Utilities');

// Create array with Classification Information (lst_ID = 1)
$sClassSQL = 'SELECT * FROM list_lst WHERE lst_ID=1 ORDER BY lst_OptionSequence';
$rsClassification = RunQuery($sClassSQL);
unset($aClassificationName);
$aClassificationName[0] = 'Unassigned';
while ($aRow = mysqli_fetch_array($rsClassification)) {
    extract($aRow);
    $aClassificationName[intval($lst_OptionID)] = $lst_OptionName;
}

// Create array with Family Role Information (lst_ID = 2)
$sFamRoleSQL = 'SELECT * FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence';
$rsFamilyRole = RunQuery($sFamRoleSQL);
unset($aFamilyRoleName);
$aFamilyRoleName[0] = 'Unassigned';
while ($aRow = mysqli_fetch_array($rsFamilyRole)) {
    extract($aRow);
    $aFamilyRoleName[intval($lst_OptionID)] = $lst_OptionName;
}

// Get the Family if specified in the query string
$iFamily = -1;
$iNumNeighbors = 15;
if (array_key_exists('Family', $_GET)) {
    $iFamily = FilterInput($_GET['Family'], 'int');
}
if (array_key_exists('NumNeighbors', $_GET)) {
    $iNumNeighbors = FilterInput($_GET['NumNeighbors'], 'int');
}

$nMaxDistance = 10; // miles, default value

$bClassificationPost = false;
$sClassificationList = '';
$sCoordFileFormat = '';
$sCoordFileFamilies = '';
$sCoordFileName = '';

//Is this the second pass?
if (isset($_POST['FindNeighbors']) ||
        isset($_POST['DataFile']) ||
        isset($_POST['PersonIDList'])) {
    //Get all the variables from the request object and assign them locally
    $iFamily = FilterInput($_POST['Family']);
    $iNumNeighbors = FilterInput($_POST['NumNeighbors']);
    $nMaxDistance = FilterInput($_POST['MaxDistance']);
    $sCoordFileName = FilterInput($_POST['CoordFileName']);
    if (array_key_exists('CoordFileFormat', $_POST)) {
        $sCoordFileFormat = FilterInput($_POST['CoordFileFormat']);
    }
    if (array_key_exists('CoordFileFamilies', $_POST)) {
        $sCoordFileFamilies = FilterInput($_POST['CoordFileFamilies']);
    }

    foreach ($aClassificationName as $key => $value) {
        $sClassNum = 'Classification'.$key;
        if (isset($_POST["$sClassNum"])) {
            $bClassificationPost = true;
            if (strlen($sClassificationList)) {
                $sClassificationList .= ',';
            }
            $sClassificationList .= $key;
        }
    }
}

// Check if cart needs to be updated
if (isset($_POST['PersonIDList'])) {
    $aIDsToProcess = explode(',', $_POST['PersonIDList']);

    //Do we add these people to cart?
    if (isset($_POST['AddAllToCart'])) {
        AddArrayToPeopleCart($aIDsToProcess);
    }

    //Do we intersect these people with cart (keep values that are in both arrays)
    if (isset($_POST['IntersectCart'])) {
        IntersectArrayWithPeopleCart($aIDsToProcess);
    }

    if (isset($_POST['RemoveFromCart'])) {
        RemoveArrayFromPeopleCart($aIDsToProcess);
    }

    //sort the cart
    sort($_SESSION['aPeopleCart']);
}

if (isset($_POST['DataFile'])) {
    $resultsByDistance = FamilyInfoByDistance($iFamily);

    if ($sCoordFileFormat == 'GPSVisualizer') {
        $filename = $sCoordFileName.'.csv';
    } elseif ($sCoordFileFormat == 'StreetAtlasUSA') {
        $filename = $sCoordFileName.'.txt';
    }

    header("Content-Disposition: attachment; filename=$filename");

    if ($sCoordFileFormat == 'GPSVisualizer') {
        echo "Name,Latitude,Longitude\n";
    }

    $counter = 0;

    foreach ($resultsByDistance as $oneResult) {
        if ($sCoordFileFamilies == 'NeighborFamilies') {
            if ($counter++ == $iNumNeighbors) {
                break;
            }
            if ($oneResult['Distance'] > $nMaxDistance) {
                break;
            }
        }

        // Skip over the ones with no data
        if ($oneResult['fam_Latitude'] == 0) {
            continue;
        }

        if ($sCoordFileFormat == 'GPSVisualizer') {
            echo $oneResult['fam_Name'].','.$oneResult['fam_Latitude'].','.$oneResult['fam_Longitude']."\n";
        } elseif ($sCoordFileFormat == 'StreetAtlasUSA') {
            echo "BEGIN SYMBOL\n";
            echo $oneResult['fam_Latitude'].','.$oneResult['fam_Longitude'].','.$oneResult['fam_Name'].','."Green Star\n";
            echo "END\n";
        }
    }

    exit;
}

require 'Include/Header.php';

//Get Families for the list
$sSQL = 'SELECT * FROM family_fam WHERE fam_DateDeactivated is null ORDER BY fam_Name';
$rsFamilies = RunQuery($sSQL); ?>
<form class="form-horizontal" method="POST" action="GeoPage.php" name="GeoPage">
    <div class="box container">
	    <div class="box-body">
            <div class="form-group">
                <label for="Family" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3" ><?= gettext('Select Family:') ?></label>
                <div class="col-xs-12 col-sm-9">
                    <select class="form-control" name="Family" size="8">
                        <?php
                        while ($aRow = mysqli_fetch_array($rsFamilies)) {
                            extract($aRow);
                            echo "\n<option value=\"".$fam_ID.'"';
                            if ($iFamily == $fam_ID) {
                                echo ' selected';
                            }
                            echo '>'.$fam_Name.'&nbsp;'.FormatAddressLine($fam_Address1, $fam_City, $fam_State);
                        } ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="NumNeighbors" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3"><?= gettext('Maximum number of neighbors:') ?></label>
                <div class="col-xs-12 col-sm-9">
                    <input type="text" class="form-control" name="NumNeighbors" value="<?= $iNumNeighbors ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="MaxDistance" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3"><?= gettext('Maximum distance').' ('.gettext(strtolower(SystemConfig::getValue('sDistanceUnit'))) ."): " ?></label>
                <div class="col-xs-12 col-sm-9">
                    <input type="text" class="form-control" name="MaxDistance" value="<?= $nMaxDistance ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="Classification0" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3"><?= gettext('Show neighbors with these classifications:') ?></label>
                <div class="row col-xs-offset-1 col-sm-offset-3">
                    <?php
                    foreach ($aClassificationName as $key => $value) {
                        $sClassNum = 'Classification'.$key;
                        $checked = (!$bClassificationPost || isset($_POST["$sClassNum"])); ?>
                        <div class="col-sm-5">
                            <label class="checkbox-inline">
                                <input type="checkbox" value="Guardian" value="1" name="Classification<?= $key ?>" id="<?= $value ?>" <?= ($checked ? 'checked' : '') ?> > <?= $value ?>
                            </label>
                        </div>
                        <?php
                    } ?>
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-8">
                    <input type="submit" class="btn btn-primary" name="FindNeighbors" value="<?= gettext('Show Neighbors') ?>">
                </div>
            </div>
        </div>
    </div>

    <?php
    if (isset($_POST['FindNeighbors']) && !$iFamily) { ?>
    <div class="alert alert-warning">
        <?= gettext("Please select a Family.") ?>
    </div>
    <?php
    } ?>

    <!--Datafile section -->
    <div class="box">
        <div class="box-header box-info">
            <h3><?= gettext('Make Data File') ?></h3>
        </div>
        <div class="box-body">
            <div class="form-group">
                <label for="CoordFileFormat" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3"><?= gettext('Data file format:') ?></label>
                <div class="col-xs-12 col-sm-9">
                    <label class="radio-inline">
                        <input type="radio" name="CoordFileFormat" value="GPSVisualizer" <?= ($sCoordFileFormat == 'GPSVisualizer' ? ' checked' : '') ?> >
                        <?= gettext('GPS Visualizer') ?>
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="CoordFileFormat" value="StreetAtlasUSA" <?= ($sCoordFileFormat == 'StreetAtlasUSA' ? ' checked' : '') ?> >
                        <?= gettext('Street Atlas USA') ?>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label for="CoordFileFamilies" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3"><?= gettext('Include families in coordinate file:') ?></label>
                <div class="col-xs-12 col-sm-9">
                    <label class="radio-inline">
                        <input type="radio" name="CoordFileFamilies" value="AllFamilies" <?= ($sCoordFileFamilies == 'AllFamilies' ? ' checked' : '') ?> >
                        <?= gettext('All Families') ?>
                    </label>
                    <label class="radio-inline">
                        <input type="radio" name="CoordFileFamilies" value="NeighborFamilies" <?= ($sCoordFileFamilies == 'NeighborFamilies' ? ' checked' : '') ?> >
                        <?= gettext('Neighbor Families') ?>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label for="CoordFileName" class="control-label col-xs-12 col-sm-3 col-md-3 col-lg-3"><?= gettext('Coordinate data base file name:') ?></label>
                <div class="col-xs-12 col-sm-9">
                    <input type="text" class="form-control" name="CoordFileName" value="<?= $sCoordFileName ?>">
                </div>
            </div>
            <div class="form-group">
                <div class="col-xs-offset-2 col-xs-8">
                    <input type="submit" class="btn btn-primary" name="DataFile" value="<?= gettext('Make Data File') ?>">
                </div>
            </div>
        </div>
    </div>


<?php
$aPersonIDs = [];

if ($iFamily != 0 &&
        (isset($_POST['FindNeighbors']) ||
        isset($_POST['PersonIDList']))) {
    $resultsByDistance = FamilyInfoByDistance($iFamily);

    $counter = 0; ?>
<div class="row">
    <!-- Column Headings -->
    <table id="neighbours" class="table table-striped table-bordered data-table dataTable no-footer dtr-inline" cellspacing="0" role="grid">
    <!--table class="table table-striped"-->
        <thead>
            <tr class="success">
                    <td><strong><?= gettext('Distance') ?> </strong></td>
                    <td><strong><?= gettext('Direction') ?></strong></td>
                    <td><strong><?= gettext('Name') ?>     </strong></td>
                    <td><strong><?= gettext('Address') ?>  </strong></td>
                    <td><strong><?= gettext('City') ?>     </strong></td>
                    <td><strong><?= gettext('State') ?>    </strong></td>
                    <td><strong><?= gettext('Zip') ?>      </strong></td>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ($resultsByDistance as $oneResult) {
            if ($counter >= $iNumNeighbors || $oneResult['Distance'] > $nMaxDistance ) {
            break;
            } // Determine how many people in this family will be listed
            $sSQL = 'SELECT * from person_per where per_fam_ID='.$oneResult['fam_ID'];
            if ($bClassificationPost) {
                $sSQL .= ' AND per_cls_ID IN ('.$sClassificationList.')';
            }
            $rsPeople = RunQuery($sSQL);
            $numListed = mysqli_num_rows($rsPeople);

            if (!$numListed) { // skip familes with zero members
                continue;
            }
            $counter++; ?>
            <tr class="info">
                <td><?= $oneResult['Distance'] ?> </td>
                <td><?= $oneResult['Bearing'] ?> </td>
                <td><B><?= $oneResult['fam_Name'] ?> </B></td>
                <td><?= $oneResult['fam_Address1'] . ($oneResult['fam_Address2'] ? ', ':'') . $oneResult['fam_Address2']  ?> </td>
                <td><?= $oneResult['fam_City'] ?> </td>
                <td><?= $oneResult['fam_State'] ?> </td>
                <td><?= $oneResult['fam_Zip'] ?> </td>
            </tr>
            <?php
            while ($aRow = mysqli_fetch_array($rsPeople)) {
                extract($aRow);

                if (!in_array($per_ID, $aPersonIDs)) {
                    $aPersonIDs[] = $per_ID;
                } ?>
            <tr>
                <td><BR></td>
                <td><BR></td>
                <td align="right"><?= $per_FirstName.' '.$per_LastName ?> </td>
                <td align="left"><?= $aClassificationName[$per_cls_ID] ?></td>
                <td><BR></td>
                <td><BR></td>
                <td><BR></td>
            </tr>
                <?php
            }
        } ?>
        </tbody>
    </table>
    </div>

    <?php
    $sPersonIDList = implode(',', $aPersonIDs); ?>

    <input type="hidden" name="PersonIDList" value="<?= $sPersonIDList ?>">

    <div class="row">
        <div class="col-xs-7 col-md-4">
            <input name="AddAllToCart"   type="submit" class="btn btn-primary" value="<?= gettext('Add to Cart') ?>">
        </div>
        <div class="col-xs-7 col-md-4">
            <input name="IntersectCart"  type="submit" class="btn btn-primary" value="<?= gettext('Intersect with Cart') ?>">
        </div>
        <div class="col-xs-7 col-md-4">
            <input name="RemoveFromCart" type="submit" class="btn btn-primary" value="<?= gettext('Remove from Cart') ?>">
        </div>
    </div>
    <?php
} ?>
</form>

<?php
require 'Include/Footer.php';
?>
