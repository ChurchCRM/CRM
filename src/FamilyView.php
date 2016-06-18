<?php
/*******************************************************************************
 *
 *  filename    : FamilyView.php
 *  last change : 2013-02-02
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002 Deane Barker, 2003 Chris Gebhardt, 2004-2005 Michael Wilt
 *
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";
require "Include/GeoCoder.php";
require 'Include/PersonFunctions.php';
require 'Service/MailchimpService.php';
require 'Service/FamilyService.php';
require 'Service/TimelineService.php';

$timelineService = new TimelineService();
$mailchimp = new MailChimpService();
$familyService = new FamilyService();
//Set the page title
$sPageTitle = gettext("Family View");
require "Include/Header.php";

//Get the FamilyID out of the querystring
$iFamilyID = FilterInput($_GET["FamilyID"], 'int');

// Get the list of funds
$sSQL = "SELECT fun_ID,fun_Name,fun_Description,fun_Active FROM donationfund_fun WHERE fun_Active = 'true'";
$rsFunds = RunQuery($sSQL);

if (isset($_POST["UpdatePledgeTable"]) && $_SESSION['bFinance']) {
  $_SESSION['sshowPledges'] = isset($_POST["ShowPledges"]);
  $_SESSION['sshowPayments'] = isset($_POST["ShowPayments"]);
  $_SESSION['sshowSince'] = FilterInput($_POST["ShowSinceDate"]);
}

$dSQL = "SELECT fam_ID FROM family_fam order by fam_Name";
$dResults = RunQuery($dSQL);

$last_id = 0;
$next_id = 0;
$capture_next = 0;
while ($myrow = mysql_fetch_row($dResults)) {
  $fid = $myrow[0];
  if ($capture_next == 1) {
    $next_id = $fid;
    break;
  }
  if ($fid == $iFamilyID) {
    $previous_id = $last_id;
    $capture_next = 1;
  }
  $last_id = $fid;
}

//Get the information for this family
$sSQL = "SELECT *, a.per_FirstName AS EnteredFirstName, a.Per_LastName AS EnteredLastName, a.per_ID AS EnteredId,
			b.per_FirstName AS EditedFirstName, b.per_LastName AS EditedLastName, b.per_ID AS EditedId
		FROM family_fam
		LEFT JOIN person_per a ON fam_EnteredBy = a.per_ID
		LEFT JOIN person_per b ON fam_EditedBy = b.per_ID
		WHERE fam_ID = " . $iFamilyID;
$rsFamily = RunQuery($sSQL);
extract(mysql_fetch_array($rsFamily));

if ($iFamilyID == $fam_ID) {

// Get the lists of custom person fields
  $sSQL = "SELECT family_custom_master.* FROM family_custom_master ORDER BY fam_custom_Order";
  $rsFamCustomFields = RunQuery($sSQL);

// Get the custom field data for this person.
  $sSQL = "SELECT * FROM family_custom WHERE fam_ID = " . $iFamilyID;
  $rsFamCustomData = RunQuery($sSQL);
  $aFamCustomData = mysql_fetch_array($rsFamCustomData, MYSQL_BOTH);

//Get the family members for this family
  $sSQL = "SELECT per_ID, per_Title, per_FirstName, per_LastName, per_Suffix, per_Gender, per_Email,
		per_BirthMonth, per_BirthDay, per_BirthYear, per_Flags, cls.lst_OptionName AS sClassName, fmr.lst_OptionName AS sFamRole
		FROM person_per
		LEFT JOIN list_lst cls ON per_cls_ID = cls.lst_OptionID AND cls.lst_ID = 1
		LEFT JOIN list_lst fmr ON per_fmr_ID = fmr.lst_OptionID AND fmr.lst_ID = 2
		WHERE per_fam_ID = " . $iFamilyID . " ORDER BY fmr.lst_OptionSequence";
  $rsFamilyMembers = RunQuery($sSQL);

//Get the pledges for this family
  $sSQL = "SELECT plg_plgID, plg_FYID, plg_date, plg_amount, plg_schedule, plg_method,
         plg_comment, plg_DateLastEdited, plg_PledgeOrPayment, a.per_FirstName AS EnteredFirstName, 
         a.Per_LastName AS EnteredLastName, b.fun_Name AS fundName, plg_NonDeductible,
         plg_GroupKey
		 FROM pledge_plg 
		 LEFT JOIN person_per a ON plg_EditedBy = a.per_ID
		 LEFT JOIN donationfund_fun b ON plg_fundID = b.fun_ID
		 WHERE plg_famID = " . $iFamilyID . " ORDER BY pledge_plg.plg_date";
  $rsPledges = RunQuery($sSQL);

//Get the automatic payments for this family
  $sSQL = "SELECT *, a.per_FirstName AS EnteredFirstName,
                   a.Per_LastName AS EnteredLastName, 
                   b.fun_Name AS fundName
		 FROM autopayment_aut
		 LEFT JOIN person_per a ON aut_EditedBy = a.per_ID
		 LEFT JOIN donationfund_fun b ON aut_Fund = b.fun_ID
		 WHERE aut_famID = " . $iFamilyID . " ORDER BY autopayment_aut.aut_NextPayDate";
  $rsAutoPayments = RunQuery($sSQL);

//Get the Properties assigned to this Family
  $sSQL = "SELECT pro_Name, pro_ID, pro_Prompt, r2p_Value, prt_Name, pro_prt_ID
		FROM record2property_r2p
		LEFT JOIN property_pro ON pro_ID = r2p_pro_ID
		LEFT JOIN propertytype_prt ON propertytype_prt.prt_ID = property_pro.pro_prt_ID
		WHERE pro_Class = 'f' AND r2p_record_ID = " . $iFamilyID .
    " ORDER BY prt_Name, pro_Name";
  $rsAssignedProperties = RunQuery($sSQL);

//Get all the properties
  $sSQL = "SELECT * FROM property_pro WHERE pro_Class = 'f' ORDER BY pro_Name";
  $rsProperties = RunQuery($sSQL);

//Get classifications
  $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
  $rsClassifications = RunQuery($sSQL);

// Get Field Security List Matrix
  $sSQL = "SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence";
  $rsSecurityGrp = RunQuery($sSQL);

  while ($aRow = mysql_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
  }

//Set the spacer cell width
  $iTableSpacerWidth = 10;

// Format the phone numbers
  $sHomePhone = ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy);
  $sWorkPhone = ExpandPhoneNumber($fam_WorkPhone, $fam_Country, $dummy);
  $sCellPhone = ExpandPhoneNumber($fam_CellPhone, $fam_Country, $dummy);

  $sFamilyEmails = array();

  $bOkToEdit = ($_SESSION['bEditRecords'] || ($_SESSION['bEditSelf'] && ($iFamilyID == $_SESSION['iFamID'])));
  ?>
  <div class="row">
    <div class="col-lg-3 col-md-4 col-sm-4">
      <div class="box box-primary">
        <div class="box-body">
          <img src="<?= $familyService->getFamilyPhoto($fam_ID) ?>" alt="" class="img-circle img-responsive profile-user-img"/>

          <h3 class="profile-username text-center"><?= gettext("The") . " $fam_Name " . gettext("Family") ?></h3>
          <?php if ($bOkToEdit) { ?>
            <a href="FamilyEditor.php?FamilyID=<?= $fam_ID ?>" class="btn btn-primary btn-block"><b>Edit</b></a>
          <?php } ?>
          <hr/>
          <ul class="fa-ul">
            <li><i class="fa-li glyphicon glyphicon-home"></i>Address: <span>
					<a href="http://maps.google.com/?q=<?= getMailingAddress($fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country) ?>" target="_blank"><?php
            echo getMailingAddress($fam_Address1, $fam_Address2, $fam_City, $fam_State, $fam_Zip, $fam_Country);
            echo "</a></span><br>";
            if ($fam_Latitude && $fam_Longitude) {
              if ($nChurchLatitude && $nChurchLongitude) {
                $sDistance = LatLonDistance($nChurchLatitude, $nChurchLongitude, $fam_Latitude, $fam_Longitude);
                $sDirection = LatLonBearing($nChurchLatitude, $nChurchLongitude, $fam_Latitude, $fam_Longitude);
                echo $sDistance . " " . strtolower($sDistanceUnit) . " " . $sDirection . " of church<br>";
              }
            }
            ?>
            <?php if (!$bHideLatLon) { /* Lat/Lon can be hidden - General Settings */ ?>
              <li><i class="fa-li fa fa-compass"></i><?= gettext("Latitude/Longitude") ?> <span><?= $fam_Latitude . " / " . $fam_Longitude ?></span></li>
            <?php }
            if (!$bHideFamilyNewsletter) { /* Newsletter can be hidden - General Settings */ ?>
              <li><i class="fa-li fa fa-hacker-news"></i><?= gettext("Send newsletter:") ?> <span><?= $fam_SendNewsLetter ?></span></li>
            <?php }
            if (!$bHideWeddingDate) { /* Wedding Date can be hidden - General Settings */ ?>
              <li><i class="fa-li fa fa-magic"></i><?= gettext("Wedding Date:") ?> <span><?= FormatDate($fam_WeddingDate, false) ?></span></li>
            <?php }
            if ($bUseDonationEnvelopes) { ?>
              <li><i class="fa-li fa fa-phone"></i><?= gettext("Envelope Number") ?> <span><?= $fam_Envelope ?></span></li>
            <?php }
            if ($sHomePhone != "") { ?>
              <li><i class="fa-li fa fa-phone"></i><?= gettext("Home Phone:") ?> <span><?= $sHomePhone ?></span></li>
            <?php }
            if ($sWorkPhone != "") { ?>
              <li><i class="fa-li fa fa-building"></i><?= gettext("Work Phone:") ?> <span><?= $sWorkPhone ?></span></li>
            <?php }
            if ($sCellPhone != "") { ?>
              <li><i class="fa-li fa fa-mobile"></i><?= gettext("Mobile Phone:") ?> <span><?= $sCellPhone ?></span></li>
            <?php }
            if ($fam_Email != "") { ?>
            <li><i class="fa-li fa fa-envelope"></i><?= gettext("Email:") ?><a href="mailto:<?= $fam_Email ?>"> <span><?= $fam_Email ?></span></a></li>
            <?php if ($mailchimp->isActive()) { ?>
            <li><i class="fa-li glyphicon glyphicon-send"></i><?= gettext("Email:") ?> <span><?= $mailchimp->isEmailInMailChimp($fam_Email) ?></span>
          </a></li>
            <?php }
            }
            // Display the left-side custom fields
            while ($Row = mysql_fetch_array($rsFamCustomFields)) {
              extract($Row);
              if (($aSecurityType[$fam_custom_FieldSec] == 'bAll') || ($_SESSION[$aSecurityType[$fam_custom_FieldSec]])) {
                $currentData = trim($aFamCustomData[$fam_custom_Field]);
                if ($type_ID == 11) $fam_custom_Special = $sPhoneCountry;
                echo "<li><i class=\"fa-li glyphicon glyphicon-tag\"></i>" . $fam_custom_Name . ": <span>" . displayCustomField($type_ID, $currentData, $fam_custom_Special) . "</span></li>";
              }
            }
            ?>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-9 col-md-8 col-sm-8">
      <div class="row">
        <div class="box"><br/>
          <a class="btn btn-app bg-aqua-active" href="FamilyVerify.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-check-square"></i> Verify Info</a>
          <a class="btn btn-app bg-olive" href="PersonEditor.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-plus-square"></i> Add New Member</a>
          <?php if (($previous_id > 0)) { ?>
            <a class="btn btn-app" href="FamilyView.php?FamilyID=<?= $previous_id ?>"><i class="fa fa-hand-o-left"></i> Previous Family</a>
          <?php } ?>
          <a class="btn btn-app btn-danger" role="button" href="FamilyList.php"><i class="fa fa-list-ul"></i> Family List</a>
          <?php if (($next_id > 0)) { ?>
            <a class="btn btn-app" role="button" href="FamilyView.php?FamilyID=<?= $next_id ?>"><i class="fa fa-hand-o-right"></i>Next Family </a>
          <?php } ?>
          <?php if ($_SESSION['bDeleteRecords']) { ?>
            <a class="btn btn-app bg-maroon" href="SelectDelete.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-trash-o"></i> Delete this Family</a>
          <?php } ?>
          <br/>
          <?php if ($bOkToEdit) { ?>
            <a class="btn btn-app" href="#" data-toggle="modal" data-target="#upload-image"><i class="fa fa-camera"></i> <?= gettext("Upload Photo") ?> </a>
            <?php if ($familyService->getUploadedPhoto($iFamilyID) != "") { ?>
              <a class="btn btn-app bg-orange" href="#" data-toggle="modal" data-target="#confirm-delete-image"><i class="fa fa-remove"></i> <?= gettext("Delete Photo") ?> </a>
            <?php }
          }
          if ($_SESSION['bNotes']) { ?>
            <a class="btn btn-app" href="NoteEditor.php?FamilyID=<?= $iFamilyID ?>"><i class="fa fa-sticky-note"></i> Add a Note</a>
          <?php } ?>
          <a class="btn btn-app" href="Reports/ConfirmReport.php?familyId=<?= $iFamilyID ?>"><i class="fa fa-download"></i> Download PDF Report</a>
          <a class="btn btn-app" href="#" data-toggle="modal" data-target="#confirm-email-pdf"><i class="fa fa-send"></i> Email PDF Report</a>
          <a class="btn btn-app" href="FamilyView.php?FamilyID=<?= $iFamilyID ?>&AddFamilyToPeopleCart=<?= $iFamilyID ?>"> <i class="fa fa-cart-plus"></i> Add All Family Members to Cart</a>
        </div>
      </div>
    </div>

    <div class="col-lg-9 col-md-8 col-sm-8">
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
                <th></th>
              </tr>
              </thead>
              <tbody>
              <?php while ($Row = mysql_fetch_array($rsFamilyMembers)) {
                $tmpPersonId = $Row["per_ID"];
                ?>
                <tr>
                  <td>
                    <img src="<?= $personService->getPhoto($tmpPersonId) ?>" width="40" height="40" class="img-circle"/>
                    <a href="PersonView.php?PersonID=<?= $tmpPersonId ?>" class="user-link"><?= $Row["per_FirstName"] . " " . $Row["per_LastName"] ?> </a>
                  </td>
                  <td class="text-center">
                    <?= getRoleLabel($Row["sFamRole"]) ?>
                  </td>
                  <td>
                    <?= FormatBirthDate($Row["per_BirthYear"], $Row["per_BirthMonth"], $Row["per_BirthDay"], "-", $Row["per_Flags"]) ?>
                  </td>
                  <td>
                    <?php $tmpEmail = $Row["per_Email"];
                    if ($tmpEmail != "") {
                      array_push($sFamilyEmails, $tmpEmail);
                      ?>
                      <a href="#"><a href="mailto:<?= $tmpEmail ?>"><?= $tmpEmail ?></a></a>
                    <?php } ?>
                  </td>
                  <td style="width: 20%;">
                    <a href="FamilyView.php?FamilyID=<?= $iFamilyID ?>&AddToPeopleCart=<?= $tmpPersonId ?>">
                                        <span class="fa-stack">
                                            <i class="fa fa-square fa-stack-2x"></i>
                                            <i class="fa fa-cart-plus fa-stack-1x fa-inverse"></i>
                                        </span>
                    </a>
                    <?php if ($bOkToEdit) { ?>
                      <a href="PersonEditor.php?PersonID=<?= $tmpPersonId ?>" class="table-link">
                                    <span class="fa-stack">
                                        <i class="fa fa-square fa-stack-2x"></i>
                                        <i class="fa fa-pencil fa-stack-1x fa-inverse"></i>
                                    </span>
                      </a>
                      <a href="SelectDelete.php?mode=person&PersonID=<?= $tmpPersonId ?>" class="table-link">
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
    </div>
  </div>
  <div class="row">
    <div class="col-lg-12 col-md-6 col-sm-3">
      <div class="nav-tabs-custom">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs" role="tablist">
          <li role="presentation" class="active"><a href="#timeline" aria-controls="timeline" role="tab" data-toggle="tab"><?= gettext("Timeline") ?></a></li>
          <li role="presentation"><a href="#properties" aria-controls="properties" role="tab" data-toggle="tab"><?= gettext("Assigned Properties") ?></a></li>
          <?php if ($_SESSION['bFinance']) { ?>
            <li role="presentation"><a href="#finance" aria-controls="finance" role="tab" data-toggle="tab"><?= gettext("Automatic Payments") ?></a></li>
            <li role="presentation"><a href="#pledges" aria-controls="pledges" role="tab" data-toggle="tab"><?= gettext("Pledges and Payments") ?></a></li>
          <?php } ?>

        </ul>

        <!-- Tab panes -->
        <div class="tab-content">
          <div role="tab-pane fade" class="tab-pane active" id="timeline">
            <ul class="timeline">
              <!-- timeline time label -->
              <li class="time-label">
                    <span class="bg-red">
                      <?php $now = new DateTime('');
                      echo $now->format("Y-m-d") ?>
                    </span>
              </li>
              <!-- /.timeline-label -->

              <!-- timeline item -->
              <?php foreach ($timelineService->getForFamily($iFamilyID) as $item) { ?>
                <li>
                  <!-- timeline icon -->
                  <i class="fa <?= $item['style'] ?>"></i>

                  <div class="timeline-item">
                    <span class="time"><i class="fa fa-clock-o"></i> <?= $item['datetime'] ?></span>

                    <h3 class="timeline-header">
                      <?php if (in_array('headerlink', $item)) { ?>
                        <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                      <?php } else { ?>
                        <?= $item['header'] ?>
                      <?php } ?>
                    </h3>

                    <div class="timeline-body">
                      <?= $item['text'] ?>
                    </div>

                    <?php if (($_SESSION['bNotes']) && ($item["editLink"] != "" || $item["deleteLink"] != "")) { ?>
                      <div class="timeline-footer">
                        <?php if ($item["editLink"] != "") { ?>
                          <a href="<?= $item["editLink"] ?>">
                            <button type="button" class="btn btn-primary"><i class="fa fa-edit"></i></button>
                          </a>
                        <?php }
                        if ($item["deleteLink"] != "") { ?>
                          <a href="<?= $item["deleteLink"] ?>">
                            <button type="button" class="btn btn-danger"><i class="fa fa-trash"></i></button>
                          </a>
                        <?php } ?>
                      </div>
                    <?php } ?>
                  </div>
                </li>
              <?php } ?>
              <!-- END timeline item -->
            </ul>
          </div>
          <div role="tab-pane fade" class="tab-pane" id="properties">
            <div class="main-box clearfix">
              <div class="main-box-body clearfix">
                <?php
                $sAssignedProperties = ",";

                if (mysql_num_rows($rsAssignedProperties) == 0) { ?>
                  <br>
                  <div class="alert alert-warning">
                    <i class="fa fa-question-circle fa-fw fa-lg"></i> <span><?= gettext("No property assignments.") ?></span>
                  </div>
                <?php } else {
                  //Yes, start the table
                  echo "<table width=\"100%\" cellpadding=\"4\" cellspacing=\"0\">";
                  echo "<tr class=\"TableHeader\">";
                  echo "<td width=\"10%\" valign=\"top\"><b>" . gettext("Type") . "</b></td>";
                  echo "<td width=\"15%\" valign=\"top\"><b>" . gettext("Name") . "</b></td>";
                  echo "<td valign=\"top\"><b>" . gettext("Value") . "</b></td>";

                  if ($bOkToEdit) {
                    echo "<td width=\"10%\" valign=\"top\"><b>" . gettext("Edit Value") . "</td>";
                    echo "<td valign=\"top\"><b>" . gettext("Remove") . "</td>";
                  }

                  echo "</tr>";

                  $last_pro_prt_ID = "";
                  $bIsFirst = true;

                  //Loop through the rows
                  while ($aRow = mysql_fetch_array($rsAssignedProperties)) {
                    $pro_Prompt = "";
                    $r2p_Value = "";

                    extract($aRow);

                    if ($pro_prt_ID != $last_pro_prt_ID) {
                      echo "<tr class=\"";
                      if ($bIsFirst)
                        echo "RowColorB";
                      else
                        echo "RowColorC";
                      echo "\"><td><b>" . $prt_Name . "</b></td>";

                      $bIsFirst = false;
                      $last_pro_prt_ID = $pro_prt_ID;
                      $sRowClass = "RowColorB";
                    } else {
                      echo "<tr class=\"" . $sRowClass . "\">";
                      echo "<td valign=\"top\">&nbsp;</td>";
                    }

                    echo "<td valign=\"center\">" . $pro_Name . "</td>";
                    echo "<td valign=\"center\">" . $r2p_Value . "&nbsp;</td>";

                    if ($bOkToEdit) {
                      if (strlen($pro_Prompt) > 0) {
                        echo "<td valign=\"center\"><a href=\"PropertyAssign.php?FamilyID=" . $iFamilyID . "&amp;PropertyID=" . $pro_ID . "\">" . gettext("Edit Value") . "</a></td>";
                      } else {
                        echo "<td>&nbsp;</td>";
                      }

                      echo "<td valign=\"center\"><a href=\"PropertyUnassign.php?FamilyID=" . $iFamilyID . "&amp;PropertyID=" . $pro_ID . "\">" . gettext("Remove") . "</a></td>";
                    }

                    echo "</tr>";

                    //Alternate the row style
                    $sRowClass = AlternateRowStyle($sRowClass);

                    $sAssignedProperties .= $pro_ID . ",";
                  }

                  //Close the table
                  echo "</table>";

                }
                if ($bOkToEdit) { ?>
                  <div class="alert alert-info">
                    <div>
                      <h4><strong><?= gettext("Assign a New Property:") ?></strong></h4>

                      <p><br></p>

                      <form method="post" action="PropertyAssign.php?FamilyID=<?= $iFamilyID ?>">
                        <select name="PropertyID">
                          <?php
                          while ($aRow = mysql_fetch_array($rsProperties)) {
                            extract($aRow);
                            //If the property doesn't already exist for this Person, write the <OPTION> tag
                            if (strlen(strstr($sAssignedProperties, "," . $pro_ID . ",")) == 0) {
                              echo "<option value=\"" . $pro_ID . "\">" . $pro_Name . "</option>";
                            }
                          }
                          ?>
                        </select>
                        <input type="submit" class="btn btn-default" value="Assign" name="Submit2" style="font-size: 8pt;">
                        </p>
                      </form>
                    </div>
                  </div>
                <?php } ?>
              </div>
            </div>
          </div>
          <?php if ($_SESSION['bFinance']) { ?>
          <div role="tab-pane fade" class="tab-pane" id="finance">
            <div class="main-box clearfix">
              <div class="main-box-body clearfix">
                <?php if (mysql_num_rows($rsAutoPayments) > 0) { ?>
                  <table cellpadding="5" cellspacing="0" width="100%">

                    <tr class="TableHeader">
                      <td><?= gettext("Type") ?></td>
                      <td><?= gettext("Next payment date") ?></td>
                      <td><?= gettext("Amount") ?></td>
                      <td><?= gettext("Interval (months)") ?></td>
                      <td><?= gettext("Fund") ?></td>
                      <td><?= gettext("Edit") ?></td>
                      <td><?= gettext("Delete") ?></td>
                      <td><?= gettext("Date Updated") ?></td>
                      <td><?= gettext("Updated By") ?></td>
                    </tr>

                    <?php

                    $tog = 0;

                    //Loop through all automatic payments
                    while ($aRow = mysql_fetch_array($rsAutoPayments)) {
                      $tog = (!$tog);

                      extract($aRow);

                      $payType = "Disabled";
                      if ($aut_EnableBankDraft)
                        $payType = "Bank Draft";
                      if ($aut_EnableCreditCard)
                        $payType = "Credit Card";

                      //Alternate the row style
                      if ($tog)
                        $sRowClass = "RowColorA";
                      else
                        $sRowClass = "RowColorB";

                      ?>

                      <tr class="<?= $sRowClass ?>">
                        <td>
                          <?= $payType ?>&nbsp;
                        </td>
                        <td>
                          <?= $aut_NextPayDate ?>&nbsp;
                        </td>
                        <td>
                          <?= $aut_Amount ?>&nbsp;
                        </td>
                        <td>
                          <?= $aut_Interval ?>&nbsp;
                        </td>
                        <td>
                          <?= $fundName ?>&nbsp;
                        </td>
                        <td>
                          <a href="AutoPaymentEditor.php?AutID=<?= $aut_ID ?>&amp;FamilyID=<?= $iFamilyID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>">Edit</a>
                        </td>
                        <td>
                          <a href="AutoPaymentDelete.php?AutID=<?= $aut_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>">Delete</a>
                        </td>
                        <td>
                          <?= $aut_DateLastEdited ?>&nbsp;
                        </td>
                        <td>
                          <?= $EnteredFirstName . " " . $EnteredLastName ?>&nbsp;
                        </td>
                      </tr>
                      <?php
                    } ?>
                  </table>
                <?php } ?>
                <p align="center">
                  <a class="SmallText" href="AutoPaymentEditor.php?AutID=-1&FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= gettext("Add a new automatic payment") ?></a>
                </p>
              </div>
            </div>
          </div>
          <div role="tab-pane fade" class="tab-pane" id="pledges">
            <div class="main-box clearfix">
              <div class="main-box-body clearfix">
                <form method="post" action="FamilyView.php?FamilyID=<?= $iFamilyID ?>">
                  <input type="checkbox" name="ShowPledges" value="1" <?php if ($_SESSION['sshowPledges']) echo " checked"; ?>><?= gettext("Show Pledges") ?>
                  <input type="checkbox" name="ShowPayments" value="1" <?php if ($_SESSION['sshowPayments']) echo " checked"; ?>><?= gettext("Show Payments") ?>
                  Since:
                  <input type="text" class="TextColumnWithBottomBorder" Name="ShowSinceDate" value="<?= $_SESSION['sshowSince'] ?>" maxlength="10" id="ShowSinceDate" size="15">
                  <input type="submit" class="btn" <?= 'value="' . gettext("Update") . '"' ?> name="UpdatePledgeTable" style="font-size: 8pt;">
                </form>

                <table cellpadding="4" cellspacing="0" width="100%">

                  <tr class="TableHeader" align="center">
                    <td><?= gettext("Pledge or Payment") ?></td>
                    <td><?= gettext("Fund") ?></td>
                    <td><?= gettext("Fiscal Year") ?></td>
                    <td><?= gettext("Date") ?></td>
                    <td><?= gettext("Amount") ?></td>
                    <td><?= gettext("NonDeductible") ?></td>
                    <td><?= gettext("Schedule") ?></td>
                    <td><?= gettext("Method") ?></td>
                    <td><?= gettext("Comment") ?></td>
                    <td><?= gettext("Edit") ?></td>
                    <td><?= gettext("Delete") ?></td>
                    <td><?= gettext("Date Updated") ?></td>
                    <td><?= gettext("Updated By") ?></td>
                  </tr>

                  <?php


                  $tog = 0;

                  if ($_SESSION['sshowPledges'] || $_SESSION['sshowPayments']) {
                    //Loop through all pledges
                    while ($aRow = mysql_fetch_array($rsPledges)) {
                      $tog = (!$tog);

                      $plg_FYID = "";
                      $plg_date = "";
                      $plg_amount = "";
                      $plg_schedule = "";
                      $plg_method = "";
                      $plg_comment = "";
                      $plg_plgID = 0;
                      $plg_DateLastEdited = "";
                      $plg_EditedBy = "";

                      extract($aRow);

                      //Display the pledge or payment if appropriate
                      if ((($_SESSION['sshowPledges'] && $plg_PledgeOrPayment == 'Pledge') ||
                          ($_SESSION['sshowPayments'] && $plg_PledgeOrPayment == 'Payment')
                        ) &&
                        ($_SESSION['sshowSince'] == "" || $plg_date > $_SESSION['sshowSince'])
                      ) {
                        //Alternate the row style
                        if ($tog)
                          $sRowClass = "RowColorA";
                        else
                          $sRowClass = "RowColorB";

                        if ($plg_PledgeOrPayment == 'Payment') {
                          if ($tog)
                            $sRowClass = "PaymentRowColorA";
                          else
                            $sRowClass = "PaymentRowColorB";
                        }

                        ?>

                        <tr class="<?= $sRowClass ?>" align="center">
                          <td>
                            <?= $plg_PledgeOrPayment ?>&nbsp;
                          </td>
                          <td>
                            <?= $fundName ?>&nbsp;
                          </td>
                          <td>
                            <?= MakeFYString($plg_FYID) ?>&nbsp;
                          </td>
                          <td>
                            <?= $plg_date ?>&nbsp;
                          </td>
                          <td align=center>
                            <?= $plg_amount ?>&nbsp;
                          </td>
                          <td align=center>
                            <?= $plg_NonDeductible ?>&nbsp;
                          </td>
                          <td>
                            <?= $plg_schedule ?>&nbsp;
                          </td>
                          <td>
                            <?= $plg_method ?>&nbsp;
                          </td>
                          <td>
                            <?= $plg_comment ?>&nbsp;
                          </td>
                          <td>
                            <a href="PledgeEditor.php?GroupKey=<?= $plg_GroupKey ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>">Edit</a>
                          </td>
                          <td>
                            <a href="PledgeDelete.php?GroupKey=<?= $plg_GroupKey ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>">Delete</a>
                          </td>
                          <td>
                            <?= $plg_DateLastEdited ?>&nbsp;
                          </td>
                          <td>
                            <?= $EnteredFirstName . " " . $EnteredLastName ?>&nbsp;
                          </td>
                        </tr>
                        <?php
                      }
                    }
                  } // if bShowPledges

                  ?>

                </table>

                <p align="center">
                  <a class="SmallText" href="PledgeEditor.php?FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>&amp;PledgeOrPayment=Pledge"><?= gettext("Add a new pledge") ?></a>
                  <a class="SmallText" href="PledgeEditor.php?FamilyID=<?= $fam_ID ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>&amp;PledgeOrPayment=Payment"><?= gettext("Add a new payment") ?></a>
                </p>

                <?php } ?>

                <?php if ($_SESSION['bCanvasser']) { ?>

                <p align="center">
                  <a class="SmallText" href="CanvassEditor.php?FamilyID=<?= $fam_ID ?>&amp;FYID=<?= $_SESSION['idefaultFY'] ?>&amp;linkBack=FamilyView.php?FamilyID=<?= $iFamilyID ?>"><?= MakeFYString($_SESSION['idefaultFY']) . gettext(" Canvass Entry") ?></a>
                </p>
              </div>
            </div>
          </div>
        <?php } ?>

        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="upload-image" tabindex="-1" role="dialog" aria-labelledby="upload-Image-label" aria-hidden="true">
    <div class="modal-dialog">
      <form action="ImageUpload.php?FamilyID=<?= $iFamilyID ?>" method="post" enctype="multipart/form-data" id="UploadForm">
        <div class="modal-content">
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="upload-Image-label"><?= gettext("Upload Photo") ?></h4>
          </div>
          <div class="modal-body">
            <input type="file" name="file" size="50"/>
            Max Photo size: <?= ini_get('upload_max_filesize') ?>
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
          <a href="ImageDelete.php?FamilyID=<?= $iFamilyID ?>" class="btn btn-danger danger">Delete</a>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="confirm-email-pdf" tabindex="-1" role="dialog" aria-labelledby="delete-Image-label" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title" id="delete-Image-label">Confirm PDF Email</h4>
        </div>
        <?php if (count($sFamilyEmails) > 0) { ?>
          <div class="modal-body">
            <p>You are about to email copy of the family information in pdf to the following emails <i><?= implode(", ", $sFamilyEmails) ?></i></p>

            <p>Do you want to proceed?</p>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
            <a href="Reports/ConfirmReportEmail.php?familyId=<?= $iFamilyID ?>" class="btn btn-warning warning">Email</a>
            <a href="Reports/ConfirmReportEmail.php?updated=true&familyId=<?= $iFamilyID ?>" class="btn btn-warning warning">Email Updated</a>
          </div>
        <?php } else { ?>
          <div class="modal-body">
            <p>This family does not have any email address, so we can't send email </p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>

  <script>
    $("#ShowSinceDate").datepicker({format: 'yyyy-mm-dd'});
  </script>
<?php } else { ?>
  <div class="error-page">
    <h2 class="headline text-yellow"> 404</h2>

    <div class="error-content">
      <h3><i class="fa fa-warning text-yellow"></i> Oops! Family not found.</h3>

      <p>
        We could not find the family you were looking for.
        Meanwhile, you may <a href="/MembersDashboard.php">return to member dashboard</a>
      </p>
    </div>
  </div>
  <?php
}
require "Include/Footer.php" ?>
