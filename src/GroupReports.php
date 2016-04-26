<?php
/*******************************************************************************
 *
 *  filename    : GroupReports.php
 *  last change : 2003-09-03
 *  description : Detailed reports on group members
 *
 *  http://www.churchcrm.io/
 *  Copyright 2003 Federico Nebiolo, Chris Gebhardt
 *  Copyright 2004-2012 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";

// Get all the groups
$sSQL = "SELECT * FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Set the page title and include HTML header
$sPageTitle = gettext("Group reports");
require "Include/Header.php";
?>

<script src="js/RPCDummyAjax.js"></script>

<?php if (!isset($_POST["GroupID"])) { ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><?= gettext("Select the group you would like to report:") ?></h3>
            </div>
            <div class="box-body">
                <form method="POST" action="GroupReports.php">
                    <div class="row">
                        <div class="col-xs-6">
                            <label for="GroupID"><?= gettext("Select Group:") ?></label>
                            <select id="GroupID" name="GroupID" onChange="UpdateRoles();">
                                <?php
                                // Create the group select drop-down
                                echo "<option value=\"0\">" . gettext('None') . "</option>";
                                while ($aRow = mysql_fetch_array($rsGroups)) {
                                    extract($aRow);
                                    echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <label for=""><?= gettext("Select Role:") ?></label>
                            <select name="GroupRole" id="GroupRole">
                                <option><?= gettext("No Group Selected") ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <label for="OnlyCart"><?= gettext("Only cart persons?:") ?></label>
                            <input type="checkbox" Name="OnlyCart" value="1">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12">
                            <label for="ReportModel"><?= gettext("Report Model:") ?></label>
                            <input type="radio" Name="ReportModel" value="1" checked><?= gettext("Report for group and role selected") ?>
                            <input type="radio" Name="ReportModel" value="2"><?= gettext("Report for any role in group selected") ?>
                            <?php
                            //<input type="radio" Name="ReportModel" value="3"><?= gettext("Report any group and role")  
                            ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <input type="submit" class="btn" name="Submit" value="<?= gettext("Next") ?>">
                            <input type="button" class="btn" name="Cancel" value="<?= gettext("Cancel") ?>" onclick="javascript:document.location = 'ReportList.php';">

                        </div>
                    </div>
                </form>
            </div>
        </div>
    
        </div>
    </div>
    <?php
}
else {

    $iGroupID = FilterInput($_POST['GroupID'], 'int');
    ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title"><?= gettext("Select which information you want to include") ?></h3>
                </div>
                <div class="box-body">

                    <form method="POST" action="Reports/GroupReport.php">
                        <input type="hidden" Name="GroupID" <?= "value=\"" . $iGroupID . "\"" ?>>
                        <input type="hidden" Name="GroupRole" <?php
                        if (array_key_exists('GroupRole', $_POST)) {
                            echo "value=\"" . $_POST['GroupRole'] . "\"";
                        }
                        ?>>
                        <input type="hidden" Name="OnlyCart" <?php
                        if (array_key_exists('OnlyCart', $_POST)) {
                            echo "value=\"" . $_POST['OnlyCart'] . "\"";
                        }
                        ?>>
                        <input type="hidden" Name="ReportModel" <?= "value=\"" . $_POST['ReportModel'] . "\"" ?>>

                        <?php
                        $sSQL = "SELECT prop_Field, prop_Name FROM groupprop_master WHERE grp_ID = " . $iGroupID . " ORDER BY prop_ID";
                        $rsPropFields = RunQuery($sSQL);
                        ?>

                        <table align="center">
                            <tr>
                                <td class="LabelColumn"><?= gettext("Standard Info:") ?></td>
                                <td class="TextColumn">
                                    <input type="checkbox" Name="AddressEnable" value="1"> <?= gettext("Address") ?> <br>
                                    <input type="checkbox" Name="HomePhoneEnable" value="1"> <?= gettext("Home Phone") ?> <br>
                                    <input type="checkbox" Name="WorkPhoneEnable" value="1"> <?= gettext("Work Phone") ?> <br>
                                    <input type="checkbox" Name="CellPhoneEnable" value="1"> <?= gettext("Cell Phone") ?> <br>
                                    <input type="checkbox" Name="EmailEnable" value="1"> <?= gettext("Email") ?> <br>
                                    <input type="checkbox" Name="OtherEmailEnable" value="1"> <?= gettext("Other Email") ?> <br>
                                    <input type="checkbox" Name="GroupRoleEnable" value="1"> <?= gettext("GroupRole") ?> <br>
                                </td>
                            </tr>
                            <tr>
                                <td class="LabelColumn"><?= gettext("Group-Specific Property Fields:") ?></td>
                                <td class="TextColumn">
                                    <?php
                                    if (mysql_num_rows($rsPropFields) > 0) {
                                        while ($aRow = mysql_fetch_array($rsPropFields)) {
                                            extract($aRow);
                                            echo "<input type=\"checkbox\" Name=\"" . $prop_Field . "enable\" value=\"1\">" . $prop_Name . "<br>";
                                        }
                                    }
                                    else
                                        echo gettext("None");
                                    ?>
                                </td>
                            </tr>
                        </table>

                        <p align="center">
                            <BR>
                            <input type="submit" class="btn" name="Submit" value="<?= gettext("Create Report") ?>">
                            <input type="button" class="btn" name="Cancel" value="<?= gettext("Cancel") ?>" onclick="javascript:document.location = 'Menu.php';">
                        </p>
                    </form>

                </div>
            </div>
        </div>
    </div>

            <?php } ?>

<?php require "Include/Footer.php" ?>
