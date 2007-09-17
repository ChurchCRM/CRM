<?php
/*******************************************************************************
 *
 *  filename    : Checkin.php
 *  last change : 2007-xx-x
 *  description : Quickly add attendees to an event
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
 *  Copyright 2005 Todd Pillars
 *
 *  InfoCentral is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

// Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/Header.php";

$sPageTitle = gettext("Event Checkin");

$sAction = $_POST['Action'];
$EventID = $_POST['EventID']; // from ListEvents button=Attendees
$EvtName = $_POST['EName'];
$EvtDesc = $_POST['EDesc'];
$EvtDate = $_POST['EDate'];

//
// process the action inputs
//

//Start off by first picking the event to check people in for
//include "show_post_info.php";
//include "show_session_info.php";

$sSQL = "SELECT * FROM events_event";
$rsEvents = RunQuery($sSQL);

//Page loading for the first time
if (!isset($_POST['EventID']) && !isset($_POST['Verify']) && !isset($_POST['Add']) && !isset($_POST['Checkout']) || isset($_POST['Exit']) ) {
?>

	<p align="center"><?php echo gettext("Select the event to which you would like to check people in for:"); ?></p>
	<form name="Checkin" action="Checkin.php" method="POST">
	<table align="center" >
			<?php if ($sGlobalMessage) { ?>
			<tr>
			  <td colspan="2"><?php echo $sGlobalMessage; ?></td>
			</tr>
			<?php } ?>
			<tr>
					<td class="LabelColumn"><?php echo gettext("Select Event:"); ?></td>
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
	<input type="submit" name="Submit" value=<?php echo '"' . gettext("Select Event") . '"'; ?> class="icButton">
	<BR><BR>--<?php echo gettext("OR"); ?>--<BR><BR>
	<a href="AddEvent.php"><?php echo gettext("Add New Event"); ?></a>
	<BR><BR>
	</p>
	</form>
<?php
}
//End Picking Event to checkin
?>

<!-- Add Atendees Here -->
<?php
//If event is known, then show 2 text boxes, person being checked in and the person checking them in.  Show a verify button and a button to add new visitor in dbase.
if (isset($_POST["Submit"]) && isset($_POST['EventID']) || isset($_POST['Cancel']) ){

	$iEventID = FilterInput($_POST["EventID"],'int');
	$sSQL = "SELECT * FROM events_event WHERE Event_id ='".$iEventID."';";
	$rsEvents = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsEvents);
	extract($aRow);
?>
	<form method="post" action="Checkin.php" name="Checkin">
	<input type="hidden" name="EventID" value="<?php echo $iEventID ; ?>">
		<table cellpadding="0" cellspacing="0" width="100%" align="center" border="1">
		<tr>
		<td>
			<caption>
				<h3><?php echo gettext("Add Attendees for Event: $event_title"); ?></h3>
			</caption>
		</td>
		</tr>
		<tr>
		<!-- Right Side -->
			<td width="33%" valign="top" align="right">
			<span class="SmallText"><input type="textbox" class="textbox" name="child">
			</td>
		<!-- Middle -->
		  <td width="33%" valign="top" align="center">
				<input type="submit" class="icButton" <?php echo 'value="' . gettext("Verify") . '"'; ?> Name="Verify" onclick="javascript:document.location='Checkin.php';">
				<input type="submit" class="icButton" <?php echo 'value="' . gettext("Back to Menu") . '"'; ?> name="Exit" onClick="javascript:document.location='Checkin.php';">
				<input type="button" class="icButton" <?php echo 'value="' . gettext("Add Visitor") . '"'; ?> name="Add" onClick="javascript:document.location='PersonEditor.php';"></td>
		<!-- Left Side -->
			<td width="33%" valign="top" align="left">
				<span class="SmallText"><input type="textbox" class="textbox" name="adult">
			</td>
		</tr>
		<tr>
			<td width="33%" align="right">
			Child's Number
			</td>
			<td width="33%" valign="top" align="center">
			</td>
			<td width="33%" valign="top" align="left">
			Adult Number(Optional)
			</td>
		</tr>
		</table>
	</form>
<?php
}
//End Entry

//Verify Section - get the picture and name of both people.  Display Add or Cancel (back to add people)"
if (isset($_POST["EventID"]) && isset($_POST['Verify']) && isset($_POST['child']) ){
	$iEventID = FilterInput($_POST["EventID"],'int');
	$iChildID = FilterInput($_POST["child"],'int');
	$iAdultID = FilterInput($_POST["adult"],'int');

	$sSQL = "SELECT * FROM events_event WHERE Event_id ='".$iEventID."';";
	$rsEvents = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsEvents);
	extract($aRow);
?>
	<form method="post" action="Checkin.php" name="Checkin">
	<input type="hidden" name="EventID" value="<?php echo $iEventID ; ?>">
	<input type="hidden" name="child" value="<?php echo $iChildID ; ?>">
	<input type="hidden" name="adult" value="<?php echo $iAdultID ; ?>">

	<table width="100%" border="0" cellpadding="4" cellspacing="0">
		<tr>
		<td width="25%" valign="top" align="center">
			<div class="LightShadedBox">
			<?php
				loadperson($iChildID);
			?>
			</div>
		</td>
	<!-- Center - 75% -->
		<td width="50% valign="Top" align="center">
			<table>
			  <tr>
				<td colspan="3" align="center">
					<p>
						<h3><?php echo gettext("Event: $event_title"); ?></h3>
					</p>
				</td>
			  </tr>
			  <tr>
				<td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("Cancel") . '"'; ?> name="Cancel" onClick="javascript:document.location='Checkin.php';"></td>
				<td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("CheckIn") . '"'; ?> name="CheckIn" onClick="javascript:document.location='Checkin.php';"></td>
				<!-- <td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("CheckOut") . '"'; ?> name="CheckOut" onClick="javascript:document.location='Checkin.php';"></td> -->
			  </tr>
			</table>
		<!-- right - 25% -->
			<td width="25%" valign="top" align="center">
			<div class="LightShadedBox">
			<?php
				if ( $iAdultID <> null ) {
					loadperson($iAdultID); 
				}

			?>
			</div>
		</td>
	</table>
	</form>

<?php
}

// End Verify section.

// Checkin Section
if (isset($_POST["EventID"]) && isset($_POST['child']) && (isset($_POST['CheckIn']) || isset($_POST['VerifyCheckOut']) ) ){
//Fields -> event_id, person_id, checkin_date, checkin_id, checkout_date, checkout_id
        $iEventID = FilterInput($_POST["EventID"],'int');
		$iChildID = FilterInput($_POST["child"],'int');
	if (isset($_POST['CheckIn']) ){
	   if ($_POST['adult'] <> '' ){
		   $iCheckinID = FilterInput($_POST["adult"],'int');
		   $fields = "(event_id, person_id, checkin_date, checkin_id)";
		   $values =  "'".$iEventID."', '".$iChildID."', NOW(), '".$iCheckinID."'";
	   }else{
			$fields = "(event_id, person_id, checkin_date)";
		   $values =  "'".$iEventID."', '".$iChildID."', NOW() ";
	   }
        $sSQL = "INSERT INTO event_attend $fields VALUES ( $values ) ;";
        RunQuery($sSQL);
	}
	if (isset($_POST['VerifyCheckOut']) ){
		if ($_POST['adult'] <> '' ){
		   $iCheckoutID = FilterInput($_POST["adult"],'int');
		   $fields = "checkout_date, checkout_id";
		   $values =  "checkout_date=NOW(), checkout_id='".$iCheckoutID."' ";
	   }else{
			$fields = "checkout_date";
		   $values =  "checkout_date=NOW() ";
	   }
        $sSQL = "UPDATE event_attend SET $values WHERE (person_id = '".$iChildID."' AND event_id='".$iEventID."') ;";
//        die($sSQL);
		RunQuery($sSQL);
	}
?>
	<form method="post" action="Checkin.php" name="Checkin">
	<input type="hidden" name="EventID" value="<?php echo $iEventID ; ?>">
	<input type="submit" name="Submit" value=<?php echo '"' . gettext("Continue checkin") . '"'; ?> class="icButton">
	</form> 
  <?php      
?>

<?php
}

//-- End checkin

//  Checkout section
if (isset($_POST["EventID"]) && isset($_POST['Action']) && isset($_POST['child']) || isset($_POST['VerifyCheck']) ){

	$iEventID = FilterInput($_POST["EventID"],'int');
	$iChildID = FilterInput($_POST["child"],'int');

	$sSQL = "SELECT * FROM events_event WHERE Event_id ='".$iEventID."';";
	$rsEvents = RunQuery($sSQL);
	$aRow = mysql_fetch_array($rsEvents);
	extract($aRow);

	if(isset($_POST['Action']) ){

	?>
	<form method="post" action="Checkin.php" name="Checkin">
		<input type="hidden" name="EventID" value="<?php echo $iEventID ; ?>">
		<input type="hidden" name="child" value="<?php echo $iChildID ; ?>">

		<table width="100%" border="0" cellpadding="4" cellspacing="0">
			<tr>
			<td width="25%" valign="top" align="center">
				<div class="LightShadedBox">
				<?php
					loadperson($iChildID);
				?>
				</div>
			</td>
		<!-- Center - 75% -->
			<td width="50% valign="Top" align="center">
				<table>
				  <tr>
					<td colspan="3" align="center">
						<p>
							<h3><?php echo gettext("Event: $event_title"); ?></h3>
						</p>
					</td>
				  </tr>
				  <tr>
					<td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("Cancel") . '"'; ?> name="Cancel" onClick="javascript:document.location='Checkin.php';"></td>
					<td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("Verify CheckOut") . '"'; ?> name="VerifyCheck" onClick="javascript:document.location='Checkin.php';"></td>
				  </tr>
				</table>
			<!-- right - 25% -->
			<td width="25%" valign="Bottom" align="center">
				<table>
					<tr>
						<div class="LightShadedBox">
							<td width="33%" valign="top" align="left">
								<span class="SmallText"><input type="textbox" class="textbox" name="adult">
							</td>
						</div>
					</tr>
					<tr>
						<td width="33%" valign="top" align="left">
							Person Checking Out Child (Number)
						</td>
					</tr>
				</table>
			</td>
		</table>
		</form>
<?php
	}
	if(isset($_POST['VerifyCheck']) ){
		$iAdultID = FilterInput($_POST["adult"],'int');
		?>
		<form method="post" action="Checkin.php" name="Checkin">
		<input type="hidden" name="EventID" value="<?php echo $iEventID ; ?>">
		<input type="hidden" name="child" value="<?php echo $iChildID ; ?>">
		<input type="hidden" name="adult" value="<?php echo $iAdultID ; ?>">

		<table width="100%" border="0" cellpadding="4" cellspacing="0">
			<tr>
			<td width="25%" valign="top" align="center">
				<div class="LightShadedBox">
				<?php
					loadperson($iChildID);
				?>
				</div>
			</td>
		<!-- Center - 75% -->
			<td width="50% valign="Top" align="center">
				<table>
				  <tr>
					<td colspan="3" align="center">
						<p>
							<h3><?php echo gettext("Event: $event_title"); ?></h3>
						</p>
					</td>
				  </tr>
				  <tr>
					<td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("Cancel") . '"'; ?> name="Cancel" onClick="javascript:document.location='Checkin.php';"></td>
					<!-- <td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("CheckIn") . '"'; ?> name="CheckIn" onClick="javascript:document.location='Checkin.php';"></td> -->
					<td  align="center"><input type="submit" class="icButton" <?php echo 'value="' . gettext("Finalize CheckOut") . '"'; ?> name="VerifyCheckOut" onClick="javascript:document.location='Checkin.php';"></td>
				  </tr>
				</table>
			<!-- right - 25% -->
			<td width="25%" valign="top" align="center">
				<div class="LightShadedBox">
				<?php
					loadperson($iAdultID);
				?>
				</div>
			</td>
		</table>
		</form>
	<?php
	}
}
//End checkout
?>

<!-- ********************************************************************************************************** -->
<table width="100%">
   <tr><td colspan="4"></td></tr>   
  <tr class="TableHeader">
    <td width="20%"><strong><?php echo gettext("Name"); ?></strong></td>
    <td width="15%"><strong><?php echo gettext("Checked In Time"); ?></strong></td>
    <td width="20%"><strong><?php echo gettext("Checked In By"); ?></strong></td>
    <td width="15%"><strong><?php echo gettext("Checked Out Time"); ?></strong></td>
    <td width="20%"><strong><?php echo gettext("Checked Out By"); ?></strong></td>
	  <td width="10%" nowrap><strong><?php echo gettext("Action"); ?></strong></td>
  </tr>

<?php 
if (isset ($_POST["EventID"]) ) {
  $EventID = FilterInput($_POST["EventID"],'int');
  $sSQL = "SELECT * FROM event_attend WHERE event_id = '$EventID' ";				// ORDER BY person_id"; 
	$rsOpps = RunQuery($sSQL);
  $numAttRows = mysql_num_rows($rsOpps);
  if($numAttRows!=0){
	  $sRowClass = "RowColorA";
	  for($na=0; $na<$numAttRows; $na++){  
		$attRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
		extract($attRow);

	//Get Person who is checked in
		$sSQL = "SELECT * FROM person_per WHERE per_ID = $person_id ";
		$perOpps = RunQuery($sSQL);
		$perRow = mysql_fetch_array($perOpps, MYSQL_BOTH);
		extract($perRow);  
		$sPerson = FormatFullName($per_Title,$per_FirstName,$per_MiddleName,$per_LastName,$per_Suffix,3);
		$per_Title='';$per_FirstName='';$per_MiddleName='';$per_LastName='';$per_Suffix='';

	//Get Person who checked person in	
		if ($checkin_id <> null){
		$sSQL = "SELECT * FROM person_per WHERE per_ID = $checkin_id";
		$perCheckin = RunQuery($sSQL);
		$perCheckinRow = mysql_fetch_array($perCheckin, MYSQL_BOTH);
		extract($perCheckinRow);  
		$sCheckinby = FormatFullName($per_Title,$per_FirstName,$per_MiddleName,$per_LastName,$per_Suffix,3);
		}
		$per_Title='';$per_FirstName='';$per_MiddleName='';$per_LastName='';$per_Suffix='';

	//Get Person who checked person out	
		if ($checkout_id > 1) {
		$sSQL = "SELECT * FROM person_per WHERE per_ID = $checkout_id";
		$perCheckout = RunQuery($sSQL);
		$perCheckoutRow = mysql_fetch_array($perCheckout, MYSQL_BOTH);
		extract($perCheckoutRow);  
		$sCheckoutby = FormatFullName($per_Title,$per_FirstName,$per_MiddleName,$per_LastName,$per_Suffix,3);
		}else{
			$sCheckoutby = '';
		}
		$per_Title='';$per_FirstName='';$per_MiddleName='';$per_LastName='';$per_Suffix='';
		$sRowClass = AlternateRowStyle($sRowClass);
		?>
		<tr class="<?php echo $sRowClass; ?>">
			<td class="TextColumn"><?php echo $sPerson; ?></td>
			<td class="TextColumn"><?php echo $checkin_date; ?></td>
			<td class="TextColumn"><?php echo $sCheckinby; ?></td>
			<td class="TextColumn"><?php echo $checkout_date; ?></td>
			<td class="TextColumn"><?php echo $sCheckoutby; ?></td>
			<td  class="TextColumn" colspan="1" align="center">
	
		    <form method="POST" action="Checkin.php" name="DeletePersonFromEvent">
			  <input type="hidden" name="child" value="<?php echo $person_id; ?>">
			  <input type="hidden" name="EventID" value="<?php echo $EventID; ?>">
			  <input type="submit" name="Action" value="<?php echo gettext("Checkout"); ?>" class="icbutton" >
			</form>
		 </td>  
		</tr>
	<?php
	  }
  }
} else {
?>
<tr><td colspan="4" align="center"><?php echo gettext("No Attendees Assigned to Event") ?></td></tr>
<?php
}

?>
</table>
    
<?php require "Include/Footer.php"; 

function loadperson($iPersonID){
	$sSQL = "SELECT a.*, family_fam.*, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole, b.per_FirstName AS EnteredFirstName,
					b.Per_LastName AS EnteredLastName, c.per_FirstName AS EditedFirstName, c.per_LastName AS EditedLastName
				FROM person_per a
				LEFT JOIN family_fam ON a.per_fam_ID = family_fam.fam_ID
				LEFT JOIN list_lst cls ON a.per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
				LEFT JOIN list_lst fmr ON a.per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
				LEFT JOIN person_per b ON a.per_EnteredBy = b.per_ID
				LEFT JOIN person_per c ON a.per_EditedBy = c.per_ID
				WHERE a.per_ID = " . $iPersonID;
	$rsPerson = RunQuery($sSQL);
	extract(mysql_fetch_array($rsPerson));

	// Get the lists of custom person fields
	$sSQL = "SELECT person_custom_master.* FROM person_custom_master
				WHERE custom_Side = 'left' ORDER BY custom_Order";
	$rsLeftCustomFields = RunQuery($sSQL);

	$sSQL = "SELECT person_custom_master.* FROM person_custom_master
				WHERE custom_Side = 'right' ORDER BY custom_Order";
	$rsRightCustomFields = RunQuery($sSQL);

	// Get the custom field data for this person.
	$sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $iPersonID;
	$rsCustomData = RunQuery($sSQL);
	$aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);

	// Get the notes for this person
	$sSQL = "SELECT nte_Private, nte_ID, nte_Text, nte_DateEntered, nte_EnteredBy, nte_DateLastEdited, nte_EditedBy, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName ";
	$sSQL .= "FROM note_nte ";
	$sSQL .= "LEFT JOIN person_per a ON nte_EnteredBy = a.per_ID ";
	$sSQL .= "LEFT JOIN person_per b ON nte_EditedBy = b.per_ID ";
	$sSQL .= "WHERE nte_per_ID = " . $iPersonID;

	// Admins should see all notes, private or not.  Otherwise, only get notes marked non-private or private to the current user.
	if (!$_SESSION['bAdmin'])
		$sSQL .= " AND (nte_Private = 0 OR nte_Private = " . $_SESSION['iUserID'] . ")";

	$rsNotes = RunQuery($sSQL);


		echo "<font size=\"4\"><b>";
		echo FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0);
		echo "</font></b><br>";

		if ($fam_ID != "") {
			echo "<font size=\"2\">(";
			if ($sFamRole != "") { echo $sFamRole; } else { echo gettext("Member"); }
			echo gettext(" of the") . " <a href=\"FamilyView.php?FamilyID=" . $fam_ID . "\">" . $fam_Name . "</a>" . gettext(" family)") . "</font><br><br>";
		}
		else
			echo gettext("(No assigned family)") . "<br><br>";

		echo "<div class=\"TinyShadedBox\">";
			echo "<font size=\"3\">";
			if ($sAddress1 != "") { echo $sAddress1 . "<br>"; }
			if ($sAddress2 != "") { echo $sAddress2 . "<br>"; }
			if ($sCity != "") { echo $sCity . ", "; }
			if ($sState != "") { echo $sState; }
			if ($sZip != "") { echo " " . $sZip; }
			if ($sCountry != "") {echo "<br>" . $sCountry; }
			echo "</font>";
		echo "</div>";

		// Strip tags in case they were added for family inherited data
		$sAddress1 = strip_tags($sAddress1);
		$sCity = strip_tags($sCity);
		$sState = strip_tags($sState);
		$sCountry = strip_tags($sCountry);

		// Upload photo
		if ( isset($_POST["UploadPhoto"]) && ($_SESSION['bAddRecords'] || $bOkToEdit) ) {
			if ($_FILES['Photo']['name'] == "") {
				$PhotoError = gettext("No photo selected for uploading.");
			} elseif ($_FILES['Photo']['type'] != "image/pjpeg" && $_FILES['Photo']['type'] != "image/jpeg") {
				$PhotoError = gettext("Only jpeg photos can be uploaded.");
			} else {
				// Create the thumbnail used by PersonView

			chmod ($_FILES['Photo']['tmp_name'], 0777);

				$srcImage=imagecreatefromjpeg($_FILES['Photo']['tmp_name']);
				$src_w=imageSX($srcImage);
				$src_h=imageSY($srcImage);

				// Calculate thumbnail's height and width (a "maxpect" algorithm)
				$dst_max_w = 200;
				$dst_max_h = 350;
				if ($src_w > $dst_max_w) {
					$thumb_w=$dst_max_w;
					$thumb_h=$src_h*($dst_max_w/$src_w);
					if ($thumb_h > $dst_max_h) {
						$thumb_h = $dst_max_h;
						$thumb_w = $src_w*($dst_max_h/$src_h);
					}
				}
				elseif ($src_h > $dst_max_h) {
					$thumb_h=$dst_max_h;
					$thumb_w=$src_w*($dst_max_h/$src_h);
					if ($thumb_w > $dst_max_w) {
						$thumb_w = $dst_max_w;
						$thumb_h = $src_h*($dst_max_w/$src_w);
					}
				}
				else {
					if ($src_w > $src_h) {
						$thumb_w = $dst_max_w;
						$thumb_h = $src_h*($dst_max_w/$src_w);
					} elseif ($src_w < $src_h) {
						$thumb_h = $dst_max_h;
						$thumb_w = $src_w*($dst_max_h/$src_h);
					} else {
						if ($dst_max_w >= $dst_max_h) {
							$thumb_w=$dst_max_h;
							$thumb_h=$dst_max_h;
						} else {
							$thumb_w=$dst_max_w;
							$thumb_h=$dst_max_w;
						}
					}
				}
				$dstImage=ImageCreateTrueColor($thumb_w,$thumb_h);
				imagecopyresampled($dstImage,$srcImage,0,0,0,0,$thumb_w,$thumb_h,$src_w,$src_h);
				imagejpeg($dstImage, "Images/Person/thumbnails/" . $iPersonID . ".jpg");
				imagedestroy($dstImage);
				imagedestroy($srcImage);
				move_uploaded_file($_FILES['Photo']['tmp_name'], "Images/Person/" . $iPersonID . ".jpg");
			}
		} elseif (isset($_POST["DeletePhoto"]) && $_SESSION['bDeleteRecords']) {
			unlink("Images/Person/" . $iPersonID . ".jpg");
			unlink("Images/Person/thumbnails/" . $iPersonID . ".jpg");
		}

		// Display photo or upload from file
		$photoFile = "Images/Person/thumbnails/" . $iPersonID . ".jpg";
		if (file_exists($photoFile))
		{
			echo '<a target="_blank" href="Images/Person/' . $iPersonID . '.jpg">';
			echo '<img border="1" src="'.$photoFile.'"></a>';
/*			if ($bOkToEdit) {
				echo '
					<form method="post"
					action="PersonView.php?PersonID=' . $iPersonID . '">
					<br>
					<input type="submit" class="icTinyButton" 
					value="' . gettext("Delete Photo") . '" name="DeletePhoto">
					</form>';
				}
*/
		} else {
			// Some old / M$ browsers can't handle PNG's correctly.
			if ($bDefectiveBrowser)
				echo '<img border="0" src="Images/NoPhoto.gif"><br><br><br>';
			else
				echo '<img border="0" src="Images/NoPhoto.png"><br><br><br>';
/*
			if ($bOkToEdit) {
				if (isset($PhotoError))
					echo '<span style="color: red;">' . $PhotoError . '</span><br>';

				echo '
					<form method="post" 
					action="PersonView.php?PersonID=' . $iPersonID . '" 
					enctype="multipart/form-data">
					<input class="icTinyButton" type="file" name="Photo">
					<input type="submit" class="icTinyButton" 
					value="' . gettext("Upload Photo") . '" name="UploadPhoto">
					</form>';
			}
*/
		}
}
?>
