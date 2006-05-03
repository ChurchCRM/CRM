<?php
/*******************************************************************************
 *
 *  filename    : ListEvents.php
 *  last change : 2005-09-10
 *  website     : http://www.terralabs.com
 *  copyright   : Copyright 2005 Todd Pillars
 *
 *  function    : List all Church Events
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

if (!$_SESSION['bAdmin'])
{
    header ("Location: Menu.php");
}

$sPageTitle = gettext("Edit Event Names");

require "Include/Header.php";

?>
<script language="javascript">

function confirmDeleteOpp( Opp ) {
var answer = confirm (<?php echo '"' . gettext("Are you sure you want to delete this event?") . '"'; ?>)
if ( answer )
        window.location="EventEditor.php?Opp=" + Opp + "&Action=delete"
}
</script>
<table width="100%" align="center" cellpadding="4" cellspacing="0">
  <tr>
    <td align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';"></td>
  </tr>
</table>
<?php
if ($_POST['Action'] == "Add Event Name")
{
    $newEventName = FilterInput($_POST['newEventName']);

    // Insert into the event_name table
    $sSQL = "INSERT INTO `event_types`
             (`type_id` , `type_name`)
             VALUES
             ('', '".$newEventName."');";
    RunQuery($sSQL);
}
elseif ($_POST['Action'] == "Save Changes")
{
    foreach ($_POST["EventName"] as $en_key => $en_val)
    {
        $sSQL = "UPDATE event_types SET type_name = '".$en_val."' WHERE type_id = '".$en_key."' LIMIT 1";
        RunQuery($sSQL);
    }
}

// Get data for the form as it now exists.

$sSQL = "SELECT * FROM event_types";
$rsOpps = RunQuery($sSQL);
$numRows = mysql_num_rows($rsOpps);

        // Create arrays of the event names
        for ($row = 1; $row <= $numRows; $row++)
        {
                $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
                extract($aRow);

                $aTypeID[$row] = $type_id;
                $aTypeName[$row] = $type_name;
        }

// Construct the form
?>
<table width="100%" align="center" cellpadding="4" cellspacing="0">
<?php
if ($numRows > 0)
{
?>
  <caption>
    <h3><?php echo gettext("There currently ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." custom event names")); ?></h3>
  </caption>
  <tr>
    <td>
      <form name="UpdateEventNames" action="EventNames.php" method="POST">
      <table width="40%" align="center">
<?php
         for ($row=1; $row <= $numRows; $row++)
         {
?>
        <tr>
          <td class="LabelColumn"><?php echo $aTypeID[$row]; ?></td>
          <td class="TextColumn"><input type="text" name="EventName[<?php echo $aTypeID[$row]; ?>]" value="<?php echo $aTypeName[$row]; ?>"></td>
        </tr>
<?php
        }
?>
        <tr>
          <td colspan="2" align="center" valign="bottom">
            <input type="submit" Name="Action" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> class="icButton">
          </td>
        </tr>
<?php
}
?>
      </table>
      </form>
    </td>
  </tr>
  <tr><td>&nbsp;</td></tr>
  <tr>
    <td>
      <form name="AddEventNames" action="EventNames.php" method="POST">
      <table width="40%" align="center">
        <tr>
          <td align="right"><span class="SmallText"><?php echo gettext("New Event Name"); ?></span></td>
          <td align="center"><input type="text" name="newEventName" value="<?php echo $newEventName; ?>" size="30" maxlength="40"></td>
          <td align="left" valign="middle">
            <input type="submit" Name="Action" <?php echo 'value="' . gettext("Add Event Name") . '"'; ?> class="icButton">
          </td>
        </tr>
      </table>
      </form>
    </td>
  </tr>
</table>
<?php
require "Include/Footer.php";
?>
