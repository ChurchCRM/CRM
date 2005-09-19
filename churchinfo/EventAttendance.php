<?php
/*******************************************************************************
 *
 *  filename    : Attendance.php
 *  last change : 2005-09-18
 *  website     : http://www.churchdb.org
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

if ($_POST['Action']== "Retrieve" && !empty($_POST['Event']))
{
    if ($_POST['Choice'] == "Attendees")
    {
        $sSQL = "SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_Email, t1.per_HomePhone, t1.per_Country, t1.per_MembershipDate, t4.fam_HomePhone, t4.fam_Country
                FROM person_per AS t1, events_event AS t2, event_attend AS t3, family_fam AS t4 
                WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ".$_POST['Event']." AND t1.per_fam_ID = t4.fam_ID AND per_cls_ID = 1
		ORDER BY t1.per_LastName, t1.per_ID";
        $sPageTitle = gettext("Event Attendees");
    }
    elseif ($_POST['Choice'] == "Nonattendees")
    {
        $aSQL = "SELECT DISTINCT(person_id) FROM event_attend WHERE event_id = ".$_POST['Event'];
        $raOpps = RunQuery($aSQL);
        while ($aRow = mysql_fetch_row($raOpps))
        {
            $aArr[] = $aRow[0];
        }
        if (count($aArr) > 0)
	{
		$aArrJoin = join(",",$aArr);
	        $sSQL = "SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_Email, t1.per_HomePhone, t1.per_Country, t1.per_MembershipDate, t2.fam_HomePhone, t2.fam_Country
        	        FROM person_per AS t1, family_fam AS t2
                	WHERE t1.per_fam_ID = t2.fam_ID AND t1.per_ID NOT IN (".$aArrJoin.") AND per_cls_ID = 1
			ORDER BY t1.per_LastName, t1.per_ID";
	}
	else
	{
		$sSQL = "SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_Email, t1.per_HomePhone, t1.per_Country, t1.per_MembershipDate, t2.fam_HomePhone, t2.fam_Country
                        FROM person_per AS t1, family_fam AS t2
                        WHERE t1.per_fam_ID = t2.fam_ID AND per_cls_ID = 1
			ORDER BY t1.per_LastName, t1.per_ID";
	}
        $sPageTitle = gettext("Event Nonattendees");
    }
    elseif ($_POST['Choice'] == "Guests")
    {
        $sSQL = "SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_HomePhone, t1.per_Country
                FROM person_per AS t1, events_event AS t2, event_attend AS t3
                WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ".$_POST['Event']." AND per_cls_ID = 3
		ORDER BY t1.per_LastName, t1.per_ID";
        $sPageTitle = gettext("Event Guests");
    }
}
elseif ($_GET['Action']== "List" && !empty($_GET['Event']))
{
    $sSQL = "SELECT * FROM events_event WHERE event_type = ".$_GET['Event']." ORDER BY event_start";

    $sPageTitle = gettext("All ".$_GET['Type']." Events");
}
require "Include/Header.php";
?>
<table cellpadding="4" align="center" cellspacing="0" width="100%">
  <tr>
    <td align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Report Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='ReportList.php';"></td>
  </tr>
</table>
<?
// Get data for the form as it now exists..
$rsOpps = RunQuery($sSQL);
$numRows = mysql_num_rows($rsOpps);

// Create arrays of the attendees.
for ($row = 1; $row <= $numRows; $row++)
{
    $aRow = mysql_fetch_assoc($rsOpps);
    extract($aRow);

    if ($_GET['Action'] == "List")
    {
        $aEventID[$row] = $event_id;
        $aEventTitle[$row] = htmlentities(stripslashes($event_title));
        $aEventStartDateTime[$row] = $event_start;
    }
    else
    {
        $aPersonID[$row] = $per_ID;
        $aTitle[$row] = $per_Title;
        $aFistName[$row] = $per_FirstName;
        $aMiddleName[$row] = $per_MiddleName;
        $aLastName[$row] = $per_LastName;
        $aSuffix[$row] = $per_Suffix;
        $aEmail[$row] = $per_Email;
	$aHomePhone[$row] = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$per_Country,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), True);
    }
}

// Construct the form
?>
<table cellpadding="4" align="center" cellspacing="0" width="60%">

<?php
if ($_GET['Action'] == "List" && $numRows > 0)
{
?>
       <caption>
         <h3><?php echo gettext("There ".($numRows == 1 ? "is ".$numRows." event":"are ".$numRows." events"))." in this category"; ?></h3>
       </caption>
         <tr class="TableHeader">
           <td width="33%"><strong><?php echo gettext("Event Title"); ?></strong></td>
           <td width="33%"><strong><?php echo gettext("Event Date"); ?></strong></td>
           <td colspan="3" width="34%" align="center"><strong><?php echo gettext("Generate Report"); ?></strong></td>
        </tr>
         <?php
         //Set the initial row color
         $sRowClass = "RowColorA";

         for ($row=1; $row <= $numRows; $row++)
         {

         //Alternate the row color
         $sRowClass = AlternateRowStyle($sRowClass);

         //Display the row
         ?>
         <tr class="<?php echo $sRowClass; ?>">
           <td class="TextColumn"><?php echo $aEventTitle[$row]; ?></td>
           <td class="TextColumn"><?php echo FormatDate($aEventStartDateTime[$row],1); ?></td>
           <td class="TextColumn" align="center">
             <form name="Attend" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
               <input type="hidden" name="Event" value="<?php echo $aEventID[$row]; ?>">
               <input type="hidden" name="Type" value="<?php echo $_GET['Type']; ?>">
               <input type="hidden" name="Action" value="Retrieve">
               <input type="hidden" name="Choice" value="Attendees">
<?php
$cSQL = "SELECT COUNT(per_ID) AS cCount
         FROM person_per as t1, events_event as t2, event_attend as t3
         WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ".$aEventID[$row]." AND per_cls_ID = 1";
$cOpps = RunQuery($cSQL);
$cNumAttend = mysql_result($cOpps, 0);
$tSQL = "SELECT COUNT(per_ID) AS tCount
         FROM person_per
         WHERE per_cls_ID = 1";
$tOpps = RunQuery($tSQL);
$tNumTotal = mysql_result($tOpps, 0);
?>
               <input type="submit" name="Type" value="<?php echo gettext("Attending Members").' ['.$cNumAttend.']'; ?>" class="icButton">
             </form>
           </td>
           <td class="TextColumn">
             <form name="NonAttend" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
               <input type="hidden" name="Event" value="<?php echo $aEventID[$row]; ?>">
               <input type="hidden" name="Type" value="<?php echo $_GET['Type']; ?>">
               <input type="hidden" name="Action" value="Retrieve">
               <input type="hidden" name="Choice" value="Nonattendees">
<?php
?>
               <input type="submit" name="Type" value="<?php echo gettext("Non-Attending Members").' ['.($tNumTotal - $cNumAttend).']'; ?>" class="icButton">
             </form>
           </td>
           <td class="TextColumn">
             <form name="GuestAttend" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
               <input type="hidden" name="Event" value="<?php echo $aEventID[$row]; ?>">
               <input type="hidden" name="Type" value="<?php echo $_GET['Type']; ?>">
               <input type="hidden" name="Action" value="Retrieve">
               <input type="hidden" name="Choice" value="Guests">
<?php
$gSQL = "SELECT COUNT(per_ID) AS gCount
         FROM person_per as t1, events_event as t2, event_attend as t3
         WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ".$aEventID[$row]." AND per_cls_ID = 3";
$gOpps = RunQuery($gSQL);
$gNumGuestAttend = mysql_result($gOpps, 0);
?>
               <input type="hidden" name="EventIDs" value="<?php echo $EventIDs; ?>">
               <input <? echo ($gNumGuestAttend == 0 ? "type=\"button\"":"type=\"submit\""); ?> name="Type" value="<?php echo gettext("Guests").' ['.$gNumGuestAttend.']'; ?>" class="icButton">
             </form>
           </td>
         </tr>
<?php
         }
?>
         <tr><td colspan="5">&nbsp;</td></tr>
<?
}
elseif ($_POST['Action']== "Retrieve" && $numRows > 0)
{
?>
       <caption>
         <h3><?php echo gettext("There ".($numRows == 1 ? "was ".$numRows." ".$_POST['Choice']:"were ".$numRows." ".$_POST['Choice']))." for this Event"; ?></h3>
       </caption>
         <tr class="TableHeader">
           <td width="45%"><strong><?php echo gettext("Name"); ?></strong></td>
           <td width="25%"><strong><?php echo gettext("Email"); ?></strong></td>
           <td width="25%"><strong><?php echo gettext("Home Phone"); ?></strong></td>
	   <td width="10%" nowrap><strong><?php echo gettext("Cart"); ?></strong></td>
        </tr>
<?php
         //Set the initial row color
         $sRowClass = "RowColorA";

         for ($row=1; $row <= $numRows; $row++)
         {

         //Alternate the row color
         $sRowClass = AlternateRowStyle($sRowClass);

         //Display the row
         ?>
         <tr class="<?php echo $sRowClass; ?>">
           <td class="TextColumn"><?php echo FormatFullName($aTitle[$row],$aFistName[$row],$aMiddleName[$row],$aLastName[$row],$aSuffix[$row],3); ?></td>
           <td class="TextColumn"><?php echo ($aEmail[$row] ? '<a href="mailto:'.$aEmail[$row].'" title="Send Email">'.$aEmail[$row].'</a>':'Not Available'); ?></td>
           <td class="TextColumn"><?php echo ($aHomePhone[$row] ? ExpandPhoneNumber($aHomePhone[$row],$aPhoneCountry[$row],$dummy):'Not Available'); ?></td>
<?php
// AddToCart call to go here
?>
           <td class="TextColumn">&nbsp;</td>
         </tr>
<?
         }
}
else
{
?>
       <caption>
         <h3><?php echo ($_GET ? gettext("There are no events in this category"):gettext("There are no Records")); ?><br><br></h3>
       </caption>
       <tr><td>&nbsp;</td></tr>
<?php
}
?>
      </table>
<?php
require "Include/Footer.php";
?>
