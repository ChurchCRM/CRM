<?php
/*******************************************************************************
 *
 *  filename    : CartToEvent.php
 *  last change : 2005-09-09
 *  description : Add cart records to an event
 *
 *  http://www.churchcrm.io/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *  Copyright 2005 Todd Pillars
 *  Copyright 2012 Michael Wilt
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

// Security: User must have Manage Groups & Roles permission
if (!$_SESSION['bManageGroups'])
{
    Redirect("Menu.php");
    exit;
}

// Was the form submitted?
if (isset($_POST["Submit"]) && count($_SESSION['aPeopleCart']) > 0 && isset($_POST['EventID'])) {

        // Get the PersonID
        $iEventID = FilterInput($_POST["EventID"],'int');

        // Loop through the session array
        $iCount = 0;
        while ($element = each($_SESSION['aPeopleCart'])) {
            // Enter ID into event
            $sSQL = "INSERT IGNORE INTO event_attend (event_id, person_id)";
            $sSQL .= " VALUES ('".$iEventID."','".$_SESSION['aPeopleCart'][$element['key']]."')";
            RunQuery($sSQL);
            $iCount++;
        }

        $sGlobalMessage = $iCount . " records(s) successfully added to selected Event.";

        Redirect("CartView.php?Action=EmptyCart&Message=aMessage&iCount=".$iCount.'&iEID='.$iEventID);

}



// Set the page title and include HTML header
$sPageTitle = gettext("Add Cart to Event");
require "Include/Header.php";

if (count($_SESSION['aPeopleCart']) > 0)
{

$sSQL = "SELECT * FROM events_event";
$rsEvents = RunQuery($sSQL);

?>
<div class="box">
<p align="center"><?= gettext("Select the event to which you would like to add your cart:") ?></p>
<form name="CartToEvent" action="CartToEvent.php" method="POST">
<table align="center">
        <?php if ($sGlobalMessage) { ?>
        <tr>
          <td colspan="2"><?= $sGlobalMessage ?></td>
        </tr>
        <?php } ?>
        <tr>
                <td class="LabelColumn"><?= gettext("Select Event:") ?></td>
                <td class="TextColumn">
                        <?php
                        // Create the group select drop-down
                        echo "<select name=\"EventID\">";
                        while ($aRow = mysql_fetch_array($rsEvents)) {
                                extract($aRow);
                                echo "<option value=\"".$event_id."\">".$event_title."</option>";
                        }
                        echo "</select>";
                        ?>
                </td>
        </tr>
</table>
<p align="center">
<BR>
<input type="submit" name="Submit" value=<?= '"' . gettext("Add Cart to Event") . '"' ?> class="btn btn-primary">
<BR><BR>--<?= gettext("OR") ?>--<BR><BR>
<a href="EventEditor.php" class="btn btn-info"><?= gettext("Add New Event") ?></a>
<BR><BR>
</p>
</form>
</div>
<?php
}
else
    echo "<p align=\"center\" class=\"callout callout-warning\">" . gettext("Your cart is empty!") . "</p>";

require "Include/Footer.php";
?>
