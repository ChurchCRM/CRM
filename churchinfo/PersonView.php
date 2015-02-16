<?php
/*******************************************************************************
 *
 *  filename    : PersonView.php
 *  last change : 2003-04-14
 *  description : Displays all the information about a single person
 *
 *  http://www.infocentral.org/
 *  Copyright 2001-2003 Phillip Hullquist, Deane Barker, Chris Gebhardt
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
require 'Include/PersonFunctions.php';
require 'Include/MailchimpFunctions.php';

$mailchimp = new ChurchInfoMailchimp();

// Get the person ID from the querystring
$iPersonID = FilterInput($_GET["PersonID"],'int');

$iRemoveVO = 0;
if (array_key_exists ("RemoveVO", $_GET))
	$iRemoveVO = FilterInput($_GET["RemoveVO"],'int');

if ( isset($_POST["GroupAssign"]) && $_SESSION['bManageGroups'] )
{
	$iGroupID = FilterInput($_POST["GroupAssignID"],'int');
	AddToGroup($iPersonID,$iGroupID,0);
}

if ( isset($_POST["VolunteerOpportunityAssign"]) && $_SESSION['bEditRecords'])
{
	$volIDs = $_POST["VolunteerOpportunityIDs"];
	if ($volIDs) {
		foreach ($volIDs as $volID) {
			AddVolunteerOpportunity($iPersonID, $volID);
		}
	}
}



// Service remove-volunteer-opportunity (these links set RemoveVO)
if ($iRemoveVO > 0  && $_SESSION['bEditRecords'])
{
	RemoveVolunteerOpportunity($iPersonID, $iRemoveVO);
}

// Get this person's data
$sSQL = "SELECT a.*, family_fam.*, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole, b.per_FirstName AS EnteredFirstName, b.per_ID AS EnteredId,
				b.Per_LastName AS EnteredLastName, c.per_FirstName AS EditedFirstName, c.per_LastName AS EditedLastName, c.per_ID AS EditedId
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
			ORDER BY custom_Order";
$rsCustomFields = RunQuery($sSQL);

// Get the custom field data for this person.
$sSQL = "SELECT * FROM person_custom WHERE per_ID = " . $iPersonID;
$rsCustomData = RunQuery($sSQL);
$aCustomData = mysql_fetch_array($rsCustomData, MYSQL_BOTH);

// Get the notes for this person
$sSQL = "SELECT nte_Private, nte_ID, nte_Text, nte_DateEntered, nte_EnteredBy, nte_DateLastEdited, nte_EditedBy, a.per_FirstName AS EnteredFirstName, a.Per_ID EnteredId, a.Per_LastName AS EnteredLastName, b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName, b.Per_ID EditedId ";
$sSQL .= "FROM note_nte ";
$sSQL .= "LEFT JOIN person_per a ON nte_EnteredBy = a.per_ID ";
$sSQL .= "LEFT JOIN person_per b ON nte_EditedBy = b.per_ID ";
$sSQL .= "WHERE nte_per_ID = " . $iPersonID;

// Admins should see all notes, private or not.  Otherwise, only get notes marked non-private or private to the current user.
if (!$_SESSION['bAdmin'])
	$sSQL .= " AND (nte_Private = 0 OR nte_Private = " . $_SESSION['iUserID'] . ")";

$rsNotes = RunQuery($sSQL);

// Get the Groups this Person is assigned to
$sSQL = "SELECT grp_ID, grp_Name, grp_hasSpecialProps, role.lst_OptionName AS roleName
		FROM group_grp
		LEFT JOIN person2group2role_p2g2r ON p2g2r_grp_ID = grp_ID
		LEFT JOIN list_lst role ON lst_OptionID = p2g2r_rle_ID AND lst_ID = grp_RoleListID
		WHERE person2group2role_p2g2r.p2g2r_per_ID = " . $iPersonID . "
		ORDER BY grp_Name";
$rsAssignedGroups = RunQuery($sSQL);
$sAssignedGroups = ",";

// Get all the Groups
$sSQL = "SELECT grp_ID, grp_Name FROM group_grp ORDER BY grp_Name";
$rsGroups = RunQuery($sSQL);

// Get the volunteer opportunities this Person is assigned to
$sSQL = "SELECT vol_ID, vol_Name, vol_Description FROM volunteeropportunity_vol
		LEFT JOIN person2volunteeropp_p2vo ON p2vo_vol_ID = vol_ID
		WHERE person2volunteeropp_p2vo.p2vo_per_ID = " . $iPersonID . " ORDER by vol_Order";
$rsAssignedVolunteerOpps = RunQuery($sSQL);

// Get all the volunteer opportunities
$sSQL = "SELECT vol_ID, vol_Name FROM volunteeropportunity_vol ORDER BY vol_Order";
$rsVolunteerOpps = RunQuery($sSQL);

// Get the Properties assigned to this Person
$sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		FROM record2property_r2p
		LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		WHERE pro_Class = 'p' AND r2p_record_ID = " . $iPersonID .
		" ORDER BY prt_Name, pro_Name";
$rsAssignedProperties = RunQuery($sSQL);

// Get all the properties
$sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'p' ORDER BY pro_Name";
$rsProperties = RunQuery($sSQL);

// Get Field Security List Matrix
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence";
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysql_fetch_array($rsSecurityGrp))
{
	extract ($aRow);
	$aSecurityType[$lst_OptionID] = $lst_OptionName;
}

if ($fam_ID != "") {
// Other family members by age
	$sSQL = "SELECT per_ID, per_Title, per_FirstName, per_LastName, per_Suffix, per_Gender, per_Email,
	per_BirthMonth, per_BirthDay, per_BirthYear, per_Flags, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole
	FROM person_per
	LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
	LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2 where per_fam_ID = " . $fam_ID . " and per_Id != " . $per_ID . " order by per_BirthYear";
	$rsOtherFamily = RunQuery($sSQL);
}

$dBirthDate = FormatBirthDate($per_BirthYear, $per_BirthMonth, $per_BirthDay,"-",$per_Flags);

$sFamilyInfoBegin = "<span style=\"color: red;\">";
$sFamilyInfoEnd = "</span>";

// Assign the values locally, after selecting whether to display the family or person information

SelectWhichAddress($sAddress1, $sAddress2, $per_Address1, $per_Address2, $fam_Address1, $fam_Address2, True);
$sCity = SelectWhichInfo($per_City, $fam_City, True);
$sState = SelectWhichInfo($per_State, $fam_State, True);
$sZip = SelectWhichInfo($per_Zip, $fam_Zip, True);
$sCountry = SelectWhichInfo($per_Country, $fam_Country, True);
$sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, False);
$sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_HomePhone,$fam_Country,$dummy), True);
$sWorkPhone = SelectWhichInfo(ExpandPhoneNumber($per_WorkPhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_WorkPhone,$fam_Country,$dummy), True);
$sCellPhone = SelectWhichInfo(ExpandPhoneNumber($per_CellPhone,$sPhoneCountry,$dummy), ExpandPhoneNumber($fam_CellPhone,$fam_Country,$dummy), True);
$sEmail = SelectWhichInfo($per_Email, $fam_Email, True);
$sUnformattedEmail = SelectWhichInfo($per_Email, $fam_Email, False);

if ($per_Envelope > 0)
	$sEnvelope = $per_Envelope;
else
	$sEnvelope = gettext("Not assigned");

// Set the page title and include HTML header

$sPageTitle = "Person Profile";
require "Include/Header.php";

$iTableSpacerWidth = 10;

$bOkToEdit = ($_SESSION['bEditRecords'] ||
			  ($_SESSION['bEditSelf'] && $per_ID==$_SESSION['iUserID']) ||
			  ($_SESSION['bEditSelf'] && $per_fam_ID==$_SESSION['iFamID'])
			 );
?>
<div class="btn-group pull-right">
	<button type="button" class="btn btn-warning"><span class="glyphicon glyphicon-cog" aria-hidden="true"></span> Manage Profile</button>
	<button type="button" class="btn btn-warning dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
		<span class="caret"></span>
		<span class="sr-only">Toggle Dropdown</span>
	</button>
	<ul class="dropdown-menu" role="menu">
		<?php
		if ($bOkToEdit) {
			echo "<li><a href=\"#\" data-toggle=\"modal\" data-target=\"#upload-image\">". gettext("Upload Photo") ."</a></li>";
			echo "<li><a href=\"#\" data-toggle=\"modal\" data-target=\"#confirm-delete-image\">". gettext("Delete Photo") ."</a></li>";
			echo "<li class=\"divider\"></li>";

		} ?>
		<li><a href="VCardCreate.php?PersonID=<?php echo $per_ID; ?>" ><?php echo gettext("Create vCard"); ?></a></li>
		<li><a href="PrintView.php?PersonID=<?php echo $per_ID; ?>"><?php echo gettext("Printable Page"); ?></a></li>
		<li><a href="PersonView.php?PersonID=<?php echo $per_ID; ?>&AddToPeopleCart=<?php echo $per_ID; ?>"><?php echo gettext("Add to Cart"); ?></a></li>
		<?php if ($_SESSION['bNotes']) { ?>
			<li class="divider"></li>
			<li><a href="WhyCameEditor.php?PersonID=<?php echo $per_ID ?>"><?php echo gettext("Edit \"Why Came\" Notes"); ?></a></li>
			<li><a href="NoteEditor.php?PersonID=<?php echo $per_ID ?>"><?php echo gettext("Add a Note to this Record"); ?></a></li>
		<?php } ?>
			<li class="divider"></li>
		<?php
		if ($_SESSION['bDeleteRecords']) {
			echo "<li><a href=\"SelectDelete.php?mode=person&PersonID=" . $per_ID . "\">" . gettext("Delete this Record") . "</a></li>";
		}
		if ($_SESSION['bAdmin'])
		{
			echo "<li class=\"divider\"></li>";
			$sSQL = "SELECT usr_per_ID FROM user_usr WHERE usr_per_ID = " . $per_ID;
			if (mysql_num_rows(RunQuery($sSQL)) == 0)
				echo "<li> <a href=\"UserEditor.php?NewPersonID=" . $per_ID . "\">" . gettext("Make User") . "</a> </li>" ;
			else
				echo "<li> <a role=\"button\" href=\"UserEditor.php?PersonID=" . $per_ID . "\">" . gettext("Edit User") . "</a> " ;
		}
		?>
	</ul>
	<a class="btn btn-default" role="button" href="SelectList.php?mode=person"><span class="glyphicon glyphicon-list" aria-hidden="true"></span></a>
</div>
<p><br/><br/></p>
<div class="alert alert-warning alert-dismissable">
	<i class="fa fa-magic"></i>
	<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
	<b><span style="color: red;"><?php echo gettext("Red text"); ?></span></b> <?php echo gettext("indicates items inherited from the associated family record.");?>
</div>
<div class="row">
	<div class="col-lg-3 col-md-4 col-sm-4">
		<div class="box box-solid box-info">
			<div class="box-header">
				<h3 class='box-title'>
					<?php
					echo getGenderIcon($per_Gender). " ".FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 0). " ";
					if ($bOkToEdit) {?>
						<a href="PersonEditor.php?PersonID=<?php echo $per_ID;?>">
						<span class="fa-stack">
							<i class="fa fa-square fa-stack-2x"></i>
							<i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
						</span>
						</a>
					<?php }?>
				</h3>
			</div>
			<div class="box-body">
				<div class="box-tools">
					<div class="label bg-light-blue"><?php
						if ($sFamRole != "")
							echo $sFamRole;
						else
							echo gettext("Member");
						?>
					</div>
					<p />
				</div>
				<img src="<?php echo getPersonPhoto($iPersonID, $per_Gender, $sFamRole) ?>" alt="" class="img-circle center-block" />

				<div class="box-tools">
					<div class="label bg-olive">
						<?php
						echo $sClassName;
						if ($per_MembershipDate) {
							echo " since: ".FormatDate($per_MembershipDate,false);
						} ?>
					</div>
				</div>
				<p><hr/></p>
				<div class="profile-details">
					<ul class="fa-ul">
						<li><i class="fa-li fa fa-group"></i>Family: <span>
								<?php
								if ($fam_ID != "") { ?>
									<a href="FamilyView.php?FamilyID=<?php echo $fam_ID; ?>"><?php echo $fam_Name; ?> </a>
									<a href="FamilyEditor.php?FamilyID=<?php echo $fam_ID; ?>" class="table-link">
										<span class="fa-stack">
											<i class="fa fa-square fa-stack-2x"></i>
											<i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
										</span>
									</a>
								<?php } else
									echo gettext("(No assigned family)");
								?>
							</span></li>
						<li><i class="fa-li glyphicon glyphicon-home"></i>Address: <span>
							<address>
							<?php
							if ($sAddress1 != "") { echo $sAddress1 . "<br>"; }
							if ($sAddress2 != "") { echo $sAddress2 . "<br>"; }
							if ($sCity != "") { echo $sCity . ", "; }
							if ($sState != "") { echo $sState; }
							if ($sZip != "") { echo " " . $sZip; }
							if ($sCountry != "") {echo "<br>" . $sCountry; }
							?>
							</address>
							</span></li>
						<?php if ($dBirthDate) {?>
						<li><i class="fa-li fa fa-calendar"></i><?php echo gettext("Birthdate:"); ?> <span><?php echo $dBirthDate; ?></span> (<?php PrintAge($per_BirthMonth,$per_BirthDay,$per_BirthYear,$per_Flags); ?>)</li>
						<?php } if (!$bHideFriendDate) { /* Friend Date can be hidden - General Settings */ ?>
						<li><i class="fa-li fa fa-tasks"></i><?php echo gettext("Friend Date:"); ?> <span><?php echo FormatDate($per_FriendDate,false); ?></span></li>
						<?php } if ($sCellPhone) {?>
						<li><i class="fa-li fa fa-mobile-phone"></i><?php echo gettext("Mobile Phone:"); ?> <span><?php echo $sCellPhone; ?></span></li>
						<?php }
						if ($sHomePhone) {
							?>
							<li><i class="fa-li fa fa-phone"></i><?php echo gettext("Home Phone:"); ?>
								<span><?php echo $sHomePhone; ?></span></li>
						<?php
						}
						if ($sEmail != "") { ?>
						<li><i class="fa-li fa fa-envelope"></i><?php echo gettext("Email:"); ?> <span><?php echo "<a href=\"mailto:" . $sUnformattedEmail . "\">" . $sEmail . "</a>"; ?></span></li>
							<?php if ($mailchimp->isActive()) { ?>
								<li><i class="fa-li glyphicon glyphicon-send"></i>MailChimp: <span><?php echo $mailchimp->isEmailInMailChimp($sEmail);?></span></li>
							<?php }
						}
						if ($sWorkPhone) {
						?>
							<li><i class="fa-li fa fa-phone"></i><?php echo gettext("Work Phone:"); ?> <span><?php echo $sWorkPhone; ?></span></li>
						<?php } ?>
						<?php if ($per_WorkEmail != "") { ?>
						<li><i class="fa-li fa fa-envelope"></i><?php echo gettext("Work/Other Email:"); ?>: <span><?php  echo "<a href=\"mailto:" . $per_WorkEmail . "\">" . $per_WorkEmail . "</a>"; ?></span></li>
							<?php if ($mailchimp->isActive()) { ?>
								<li><i class="fa-li glyphicon glyphicon-send"></i>MailChimp: <span><?php echo $mailchimp->isEmailInMailChimp($per_WorkEmail); ?></span></li>
							<?php
							}
						}

						// Display the right-side custom fields
						while ($Row = mysql_fetch_array($rsCustomFields)) {
							extract($Row);
							$currentData = trim($aCustomData[$custom_Field]);
							if ($currentData != "") {
								if ($type_ID == 11) $custom_Special = $sPhoneCountry;
								echo "<li><i class=\"fa-li glyphicon glyphicon-tag\"></i>" . $custom_Name . ": <span>";
								echo nl2br((displayCustomField($type_ID, $currentData, $custom_Special)));
								echo "</span></li>";
							}
						}
						?>
					</ul>
				</div>
			</div>
		</div>
	</div>
	<div class="col-lg-9 col-md-8 col-sm-8">
		<div class="box box-solid">
			<div class="box-body clearfix">
				<table width="100%">
					<tr>
						<td width="50%">
							<img src="<?php echo getPersonPhoto($EnteredId, "", "") ?>" alt="" width="40" height="40" class="img-circle"/>
							<?php echo gettext("Entered: ").FormatDate($per_DateEntered,false).gettext(" by ").$EnteredFirstName . " " . $EnteredLastName; ?>
						</td>
						<?php if (strlen($per_DateLastEdited) > 0) { ?>
						<td width="50%">
							<img src="<?php echo getPersonPhoto($EnteredId, "", "") ?>" alt="" width="40" height="40" class="img-circle"/>
							<?php  echo gettext("Updated: "). FormatDate($per_DateLastEdited,false) .gettext(" by ") . $EditedFirstName . " " . $EditedLastName."<br>"; ?>
						</td>
						<?php } ?>
					</tr>
				</table>
			</div>
		</div>
	</div>
	<div class="col-lg-9 col-md-8 col-sm-8">
		<?php if (mysql_num_rows($rsOtherFamily) != 0) { ?>
		<div class="row">
			<div class="box box-solid">
				<div class="box-body table-responsive clearfix">
					<table class="table user-list table-hover">
						<thead>
						<tr>
							<th><span>Family Members</span></th>
							<th class="text-center"><span>Role</span></th>
							<th><span>Birthday</span></th>
							<th><span>Email</span></th>
							<th>&nbsp;</th>
						</tr>
						</thead>
						<tbody>
						<?php while ($Row = mysql_fetch_array($rsOtherFamily)) {
							$tmpPersonId = $Row["per_ID"];
							?>
						<tr>
							<td>
								<img src="<?php echo getPersonPhoto($tmpPersonId, $Row["per_Gender"], $Row["sFamRole"]) ?>" width="40" height="40" class="img-circle" />
								<a href="PersonView.php?PersonID=<?php echo $tmpPersonId; ?>" class="user-link"><?php echo $Row["per_FirstName"]." ".$Row["per_LastName"]; ?> </a>
							</td>
							<td class="text-center">
								<?php echo getRoleLabel($Row["sFamRole"]) ?>
							</td>
							<td>
								<?php echo FormatBirthDate($Row["per_BirthYear"], $Row["per_BirthMonth"], $Row["per_BirthDay"],"-",$Row["per_Flags"]);?>
							</td>
							<td>
								<?php $tmpEmail = $Row["per_Email"];
								if ($tmpEmail != "") { ?>
								<a href="#"><a href="mailto:<?php echo $tmpEmail; ?>"><?php echo $tmpEmail; ?></a></a>
								<?php } ?>
							</td>
							<td style="width: 20%;">
								<a href="PersonView.php?PersonID=<?php echo $tmpPersonId; ?>&AddToPeopleCart=<?php echo $tmpPersonId; ?>">
									<span class="fa-stack">
										<i class="fa fa-square fa-stack-2x"></i>
										<i class="fa fa-shopping-cart fa-stack-1x fa-inverse"></i>
									</span>
								</a>
								<?php if ($bOkToEdit) { ?>
								<a href="PersonEditor.php?PersonID=<?php echo $tmpPersonId; ?>">
									<span class="fa-stack">
										<i class="fa fa-square fa-stack-2x"></i>
										<i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
									</span>
								</a>
								<a href="SelectDelete.php?mode=person&PersonID=<?php echo $tmpPersonId; ?>">
									<span class="fa-stack">
										<i class="fa fa-square fa-stack-2x"></i>
										<i class="fa fa-trash-o fa-stack-1x fa-inverse"></i>
									</span>
								</a>
								<?php } ?>
							</td>
						</tr>
						<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>
</div>
<div class="row">
	<div class="box box-solid">
		<div class="box-body clearfix">
			<div class="nav-tabs-custom">
				<!-- Nav tabs -->
				<ul class="nav nav-tabs" role="tablist">
					<li role="presentation" class="active"><a href="#groups" aria-controls="groups" role="tab" data-toggle="tab"><?php echo gettext("Assigned Groups"); ?></a></li>
					<li role="presentation"><a href="#properties" aria-controls="properties" role="tab" data-toggle="tab"><?php echo gettext("Assigned Properties"); ?></a></li>
					<li role="presentation"><a href="#volunteer" aria-controls="volunteer" role="tab" data-toggle="tab"><?php echo gettext("Volunteer opportunities"); ?></a></li>
					<?php if ($_SESSION['bNotes']) { ?>
						<li role="presentation"><a href="#notes" aria-controls="notes" role="tab" data-toggle="tab"><?php echo gettext("Notes"); ?></a></li>
					<?php } ?>
				</ul>

				<!-- Tab panes -->
				<div class="tab-content">
					<div role="tab-pane fade" class="tab-pane active" id="groups">
						<div class="main-box clearfix">
							<div class="main-box-body clearfix">
								<?php
								//Was anything returned?
								if (mysql_num_rows($rsAssignedGroups) == 0) {?>
									<br>
									<div class="alert alert-warning">
										<i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?php echo gettext("No group assignments."); ?></span>
									</div>
								<?php } else {
									echo "<div class=\"row\">";
									// Loop through the rows
									while ($aRow = mysql_fetch_array($rsAssignedGroups)) {
										extract($aRow); ?>
										<div class="col-md-3">
											<p><br/></p>
											<!-- Info box -->
											<div class="box box-info">
												<div class="box-header">
													<h3 class="box-title"><a href="GroupView.php?GroupID=<?php echo $grp_ID ?>"><?php echo $grp_Name; ?></a></h3>
													<div class="box-tools pull-right">
														<div class="label bg-aqua"><?php echo $roleName;?></div>
													</div>
												</div>
												<?php
												// If this group has associated special properties, display those with values and prop_PersonDisplay flag set.
												if ($grp_hasSpecialProps == 'true') {
													// Get the special properties for this group
													$sSQL = "SELECT groupprop_master.* FROM groupprop_master WHERE grp_ID = " . $grp_ID . " AND prop_PersonDisplay = 'true' ORDER BY prop_ID";
													$rsPropList = RunQuery($sSQL);

													$sSQL = "SELECT * FROM groupprop_" . $grp_ID . " WHERE per_ID = " . $iPersonID;
													$rsPersonProps = RunQuery($sSQL);
													$aPersonProps = mysql_fetch_array($rsPersonProps, MYSQL_BOTH);

													echo "<div class=\"box-body\">";

													while ($aProps = mysql_fetch_array($rsPropList)) {
														extract($aProps);
														$currentData = trim($aPersonProps[$prop_Field]);
														if (strlen($currentData) > 0) {
															$sRowClass = AlternateRowStyle($sRowClass);
															if ($type_ID == 11) $prop_Special = $sPhoneCountry;
															echo "<strong>" . $prop_Name . "</strong>: " . displayCustomField($type_ID, $currentData, $prop_Special) . "<br/>";
														}
													}

													echo "</div><!-- /.box-body -->";
												} ?>
												<div class="box-footer">
													<code>
														<?php if ($_SESSION['bManageGroups']) {?>
															<a href="GroupView.php?GroupID=<?php echo $grp_ID ?>" class="btn btn-default" role="button"><i class="glyphicon glyphicon-list"></i></a>
															<div class="btn-group">
																<button type="button" class="btn btn-default">Action</button>
																<button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
																	<span class="caret"></span>
																	<span class="sr-only">Toggle Dropdown</span>
																</button>
																<ul class="dropdown-menu" role="menu">
																	<li><a href="MemberRoleChange.php?GroupID=<?php echo $grp_ID; ?>&PersonID=<?php echo $iPersonID; ?>" >Change Role</a></li>
																	<?php if ($grp_hasSpecialProps == 'true') { ?>
																		<li><a href="GroupPropsEditor.php?GroupID=<?php echo $grp_ID; ?>&PersonID=<?php echo $iPersonID; ?>">Update Properties</a></li>
																	<?php } ?>
																</ul>
															</div>
															<a href="#" onclick="GroupRemove(<?php echo $grp_ID . ", " . $iPersonID ;?>);" class="btn btn-danger" role="button"><i class="fa fa-trash-o"></i></a>
														<?php } ?>
													</code>
												</div><!-- /.box-footer-->
											</div><!-- /.box -->
										</div>
										<?php
										// NOTE: this method is crude.  Need to replace this with use of an array.
										$sAssignedGroups .= $grp_ID . ",";
									}
									echo "</div>";
								}
								if ($_SESSION['bManageGroups']) { ?>
									<div class="alert alert-info">
										<h4><strong>Assign New Group</strong></h4>
										<i class="fa fa-info-circle fa-fw fa-lg"></i> <span><?php echo gettext("Person will be assigned to the Group in the Default Role."); ?></span>
										<p><br></p>
										<form method="post" action="PersonView.php?PersonID=<?php echo $iPersonID ?>">
											<select name="GroupAssignID">
												<?php while ($aRow = mysql_fetch_array($rsGroups)) {
													extract($aRow);

													//If the property doesn't already exist for this Person, write the <OPTION> tag
													if (strlen(strstr($sAssignedGroups,"," . $grp_ID . ",")) == 0) {
														echo "<option value=\"" . $grp_ID . "\">" . $grp_Name . "</option>";
													}
												}
												?>
											</select>
											<input type="submit" class="btn-primary" <?php echo 'value="' . gettext("Assign") . '"'; ?> name="GroupAssign">
											<br>
										</form>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>
					<div role="tab-pane fade" class="tab-pane" id="properties">
						<div class="main-box clearfix">
							<div class="main-box-body clearfix">
								<?php
								$sAssignedProperties = ",";

								//Was anything returned?
								if (mysql_num_rows($rsAssignedProperties) == 0) { ?>
									<br>
									<div class="alert alert-warning">
										<i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?php echo gettext("No property assignments."); ?></span>
									</div>
								<?php } else {
									//Yes, start the table
									echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
									echo "<tr class=\"TableHeader\">";
									echo "<td width=\"10%\" valign=\"top\"><b>" . gettext("Type") . "</b>";
									echo "<td width=\"15%\" valign=\"top\"><b>" . gettext("Name") . "</b>";
									echo "<td valign=\"top\"><b>" . gettext("Value") . "</b></td>";

									if ($bOkToEdit)
									{
										echo "<td valign=\"top\"><b>" . gettext("Edit") . "</b></td>";
										echo "<td valign=\"top\"><b>" . gettext("Remove") . "</b></td>";
									}
									echo "</tr>";

									$last_pro_prt_ID = "";
									$bIsFirst = true;

									//Loop through the rows
									while ($aRow = mysql_fetch_array($rsAssignedProperties))
									{
										$pro_Prompt = "";
										$r2p_Value = "";

										extract($aRow);

										if ($pro_prt_ID != $last_pro_prt_ID)
										{
											echo "<tr class=\"";
											if ($bIsFirst)
												echo "RowColorB";
											else
												echo "RowColorC";
											echo "\"><td><b>" . $prt_Name . "</b></td>";

											$bIsFirst = false;
											$last_pro_prt_ID = $pro_prt_ID;
											$sRowClass = "RowColorB";
										}
										else
										{
											echo "<tr class=\"" . $sRowClass . "\">";
											echo "<td valign=\"top\">&nbsp;</td>";
										}

										echo "<td valign=\"center\">" . $pro_Name . "&nbsp;</td>";
										echo "<td valign=\"center\">" . $r2p_Value . "&nbsp;</td>";

										if ($bOkToEdit)
										{
											if (strlen($pro_Prompt) > 0)
											{
												echo "<td valign=\"center\"><a href=\"PropertyAssign.php?PersonID=" . $iPersonID . "&PropertyID=" . $pro_ID . "\">" . gettext("Edit") . "</a></td>";
											}
											else
											{
												echo "<td>&nbsp;</td>";
											}
											echo "<td valign=\"center\"><a href=\"PropertyUnassign.php?PersonID=" . $iPersonID . "&PropertyID=" . $pro_ID . "\">" . gettext("Remove") . "</a></td>";
										}
										echo "</tr>";

										//Alternate the row style
										$sRowClass = AlternateRowStyle($sRowClass);

										$sAssignedProperties .= $pro_ID . ",";
									}
									echo "</table>";
								}

								?>

								<?php if ($bOkToEdit && mysql_num_rows($rsProperties) != 0) { ?>
									<div class="alert alert-info">
										<div>
											<h4><strong><?php echo gettext("Assign a New Property:"); ?></strong></h4>
											<p><br></p>
											<form method="post" action="PropertyAssign.php?PersonID=<?php echo $iPersonID; ?>">
												<select name="PropertyID">
													<?php
													while ($aRow = mysql_fetch_array($rsProperties)) {
														extract($aRow);
														//If the property doesn't already exist for this Person, write the <OPTION> tag
														if (strlen(strstr($sAssignedProperties,"," . $pro_ID . ",")) == 0) {
															echo "<option value=\"" . $pro_ID . "\">" . $pro_Name . "</option>";
														}
													}
													?>
												</select>
												<input type="submit" class="btn-primary" <?php echo 'value="' . gettext("Assign") . '"'; ?> name="Submit" >
											</form>
										</div>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>
					<div role="tab-pane fade" class="tab-pane" id="volunteer">
						<div class="main-box clearfix">
							<div class="main-box-body clearfix">
								<?php

								//Initialize row shading
								$sRowClass = "RowColorA";

								$sAssignedVolunteerOpps = ",";

								//Was anything returned?
								if (mysql_num_rows($rsAssignedVolunteerOpps) == 0)  {?>
									<br>
									<div class="alert alert-warning">
										<i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?php echo gettext("No volunteer opportunity assignments."); ?></span>
									</div>
								<?php } else {
									echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
									echo "<tr class=\"TableHeader\">";
									echo "<td>" . gettext("Name") . "</td>";
									echo "<td>" . gettext("Description") . "</td>";
									if ($_SESSION['bEditRecords']) {
										echo "<td width=\"10%\">" . gettext("Remove") . "</td>";
									}
									echo "</tr>";

									// Loop through the rows
									while ($aRow = mysql_fetch_array($rsAssignedVolunteerOpps))
									{
										extract($aRow);

										// Alternate the row style
										$sRowClass = AlternateRowStyle($sRowClass);

										echo "<tr class=\"" . $sRowClass . "\">";
										echo "<td>" . $vol_Name . "</a></td>";
										echo "<td>" . $vol_Description . "</a></td>";

										if ($_SESSION['bEditRecords']) echo "<td><a class=\"SmallText\" href=\"PersonView.php?PersonID=" . $per_ID . "&RemoveVO=" . $vol_ID . "\">" . gettext("Remove") . "</a></td>";

										echo "</tr>";

										// NOTE: this method is crude.  Need to replace this with use of an array.
										$sAssignedVolunteerOpps .= $vol_ID . ",";
									}
									echo "</table>";
								}
								?>

								<?php if ($_SESSION['bEditRecords']) { ?>
									<div class="alert alert-info">
										<div>
											<h4><strong><?php echo gettext("Assign a New Volunteer Opportunity:"); ?></strong></h4>
											<p><br></p>
											<form method="post" action="PersonView.php?PersonID=<?php echo $iPersonID ?>">
												<select name="VolunteerOpportunityIDs[]", size=6, multiple>
													<?php
													while ($aRow = mysql_fetch_array($rsVolunteerOpps)) {
														extract($aRow);
														//If the property doesn't already exist for this Person, write the <OPTION> tag
														if (strlen(strstr($sAssignedVolunteerOpps,"," . $vol_ID . ",")) == 0) {
															echo "<option value=\"" . $vol_ID . "\">" . $vol_Name . "</option>";
														}
													}
													?>
												</select>
												<input type="submit" <?php echo 'value="' . gettext("Assign") . '"'; ?> name="VolunteerOpportunityAssign" class="btn-primary">
											</form>
										</div>
									</div>
								<?php } ?>
							</div>
						</div>
					</div>
					<?php if ($_SESSION['bNotes']) { ?>
						<div role="tab-pane fade" class="tab-pane" id="notes">
							<div class="box box-solid">
								<div class="box-header">
									<p>
									<div class="pull-right top-page-ui text-center clearfix">
										<div class="profile-message-btn btn-group">
											<a class="btn btn-primary active" role="button" href="NoteEditor.php?FamilyID=<?php echo $fam_ID; ?>"><span class="fa fa-plus" aria-hidden="true"></span> Add Note</a>
										</div>
									</div>
									<br></p>
								</div>
								<div class="box-body chat" id="chat-box">
									<?php
									//Loop through all the notes
									while($aRow = mysql_fetch_array($rsNotes)){
										extract($aRow);
										?>
										<!-- chat item -->
										<div class="item">
											<img src="<?php echo getPersonPhoto($EnteredId, "", "") ?>"/>
											<p class="message">
												<a href="#" class="name">
													<small class="text-muted pull-right"><i class="fa fa-clock-o"></i> <?php
														if (!strlen($nte_DateLastEdited)) {
															echo FormatDate($nte_DateEntered, True);
														} else {
															echo FormatDate($nte_DateLastEdited,True);
														} ?>
													</small>
													<?php if (!strlen($nte_DateLastEdited)) {
														echo $EnteredFirstName . " " . $EnteredLastName;
													} else {
														echo $EditedFirstName . " " . $EditedLastName;
													}?>
												</a>
												<?php echo $nte_Text ?>
											</p>
											<?php if ($_SESSION['bNotes']) { ?>
												<div class="pull-right">
													<a href="NoteEditor.php?PersonID=<?php echo $iPersonID ?>&NoteID=<?php echo $nte_ID ?>">
											<span class="fa-stack">
												<i class="fa fa-square fa-stack-2x"></i>
												<i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
											</span>
													</a>
													<a href="NoteDelete.php?NoteID=<?php echo $nte_ID ?>">
											<span class="fa-stack">
												<i class="fa fa-square fa-stack-2x"></i>
												<i class="fa fa-trash-o fa-stack-1x fa-inverse"></i>
											</span>
													</a>
												</div>
											<?php } ?>
										</div><!-- /.item -->
									<?php } ?>
								</div>
							</div>
						</div>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- Modal -->
<div class="modal fade" id="upload-image" tabindex="-1" role="dialog" aria-labelledby="upload-Image-label" aria-hidden="true">
	<div class="modal-dialog">
		<form action="ImageUpload.php?PersonID=<?php echo $iPersonID;?>" method="post" enctype="multipart/form-data" id="UploadForm">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="upload-Image-label"><?php echo gettext("Upload Photo") ?></h4>
			</div>
			<div class="modal-body">
				<input type="file" name="file" size="50" />
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				<input type="submit" class="btn btn-primary" value="Upload Image">
			</div>
		</div>
		</form>
	</div>
</div>
<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title" id="delete-Image-label">Confirm Delete</h4>
			</div>

			<div class="modal-body">
				<p>You are about to delete the profile photo, this procedure is irreversible.</p>
				<p>Do you want to proceed?</p>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
				<a href="ImageDelete.php?PersonID=<?php echo $iPersonID;?>" class="btn btn-danger danger">Delete</a>
			</div>
		</div>
	</div>
</div>
<script language="javascript">
	function GroupRemove( Group, Person ) {
		var answer = confirm (<?php echo "'",  "'"; ?>)
		if ( answer )
			window.location="GroupMemberList.php?GroupID=" + Group + "&PersonToRemove=" + Person
	}
</script>
<!-- Latest compiled and minified CSS -->
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/jasny-bootstrap/3.1.3/css/jasny-bootstrap.min.css">

<!-- Latest compiled and minified JavaScript -->
<script src="//cdnjs.cloudflare.com/ajax/libs/jasny-bootstrap/3.1.3/js/jasny-bootstrap.min.js"></script>
<?php
require "Include/Footer.php";
?>
