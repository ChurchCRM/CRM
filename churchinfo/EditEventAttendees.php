<?php
//*******************************************************************************


require "Include/Config.php";
require "Include/Functions.php";
require "Include/Header.php";

$sPageTitle = gettext("Church Event Editor");

$sAction = $_POST['Action'];
$EventID = $_POST['EID']; // from ListEvents button=Attendees
$EvtName = $_POST['EName'];
$EvtDesc = $_POST['EDesc'];
$EvtDate = $_POST['EDate'];
//
// process the action inputs
//
if ($sAction=='Delete'){
  $dpeEventID=$_POST['DelPerEventID'];
  $dpePerID=$_POST['DelPerID'];
  $dpeSQL = "DELETE FROM event_attend WHERE event_id=$dpeEventID AND person_id=$dpePerID LIMIT 1";
  RunQuery($dpeSQL);
  $ShowAttendees = 1;
   
}
// Construct the form
?>

<form method="post" action="EditEventAttendees.php" name="AttendeeEditor">
<input type="hidden" name="EID" value="<?php echo $EventID ; ?>">
<table cellpadding="0" cellspacing="0" width="75%" align="center">
  <caption>
    <h3><?php echo gettext("Attendees for Event ID: $EventID"); ?></h3>
  </caption>
  <tr ><td colspan="4">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center"><span class="SmallText"><?php echo gettext("<strong>Name:</strong> <br>$EvtName") ?></td>
      <td align="center"><span class="SmallText"><?php echo gettext("<strong>Description:</strong> <br>$EvtDesc") ?></span></td>
      <td align="center"><span class="SmallText"><?php echo gettext("<strong>Date:</strong><br>$EvtDate") ?></span></td>
    </tr>
    </table>
    </td>
    </tr>
  <tr><td colspan="4"></td></tr>
  <tr>
    <td colspan="4" align="center"><input type="button" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';"></td>
 </tr> 
   <tr><td colspan="4"></td></tr>   
  <tr class="TableHeader">
    <td width="35%"><strong><?php echo gettext("Name"); ?></strong></td>
    <td width="25%"><strong><?php echo gettext("Email"); ?></strong></td>
    <td width="25%"><strong><?php echo gettext("Home Phone"); ?></strong></td>
	  <td width="15%" nowrap><strong><?php echo gettext("Action"); ?></strong></td>
  </tr>
<?php 
  $sSQL = "SELECT * FROM event_attend WHERE event_id=$EventID ORDER BY person_id"; 
	$rsOpps = RunQuery($sSQL);
  $numAttRows = mysql_num_rows($rsOpps);
  if($numAttRows!=0){
  $sRowClass = "RowColorA";
  for($na=0; $na<$numAttRows; $na++){  
    $attRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
    extract($attRow);
    $sSQL = "SELECT * FROM person_per WHERE per_ID = $person_id";
    $perOpps = RunQuery($sSQL);
    $perRow = mysql_fetch_array($perOpps, MYSQL_BOTH);
    extract($perRow);  
    $sRowClass = AlternateRowStyle($sRowClass);
    $aHomePhone = ExpandPhoneNumber($per_HomePhone,$per_Country,$dummy);
    ?>
    <tr class="<?php echo $sRowClass; ?>">
        <td class="TextColumn"><?php echo FormatFullName($per_Title,$per_FirstName,$per_MiddleName,$per_LastName,$per_Suffix,3); ?></td>
        <td class="TextColumn"><?php echo ($per_Email ? '<a href="mailto:'.$per_Email.'" title="Send Email">'.$per_Email.'</a>':'Not Available'); ?></td>
        <td class="TextColumn"><?php echo ($aHomePhone ? $aHomePhone :'Not Available'); ?></td>
    <td  class="TextColumn" colspan="1" align="center">
      <form method="POST" action="EditEventAttendees.php" name="DeletePersonFromEvent">
          <input type="hidden" name="DelPerID" value="<?php echo $per_ID; ?>">
          <input type="hidden" name="DelPerEventID" value="<?php echo $EventID; ?>">
          <input type="hidden" name="EID" value="<?php echo $EventID; ?>">
          <input type="hidden" name="EName" value="<?php echo $EvtName ?>">
          <input type="hidden" name="EDesc" value="<?php echo $EvtDesc ?>">
          <input type="hidden" name="EDate" value="<?php echo $EvtDate ?>">
          <input type="submit" name="Action" value="<?php echo gettext("Delete"); ?>" class="icbutton" onClick="return confirm('Are you sure you want to DELETE this person from Event ID:<?php echo $EventID; ?>')">
      </form>
     </td>  
    </tr>
    <?php
    }
} else {
?>
<tr><td colspan="4" align="center"><?php echo gettext("No Attendees Assigned to Event") ?></td></tr>
<?php
}

?>
</table>
    
<?php require "Include/Footer.php"; ?>
