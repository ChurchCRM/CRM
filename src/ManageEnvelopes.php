<?php
/*******************************************************************************
 *
 *  filename    : ManageEnvelopes.php
 *  last change : 2005-02-21
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2006 Michael Wilt
 *
 *  ChurchCRM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

//Include the function library
require "Include/Config.php";
require "Include/Functions.php";

require "Include/EnvelopeFunctions.php";

//Set the page title
$sPageTitle = gettext("Envelope Manager");

// Security: User must have finance permission to use this form
if (!$_SESSION['bFinance']) {
    Redirect("Menu.php");
    exit;
}

$envelopesToWrite = array ();
// get the array of envelopes of interest, indexed by family id
$envelopesByFamID = getEnvelopes($iClassification);

// get the array of family name/description strings, also indexed by family id
$familyArray = getFamilyList($sDirRoleHead, $sDirRoleSpouse, $iClassification);
asort($familyArray);

if (isset($_POST["Confirm"])) {
    foreach ($familyArray as $fam_ID => $fam_Data) {
        $key = "EnvelopeID_" . $fam_ID;
        if (isset($_POST[$key])) {
            $newEnvelope = $_POST[$key];
            $priorEnvelope = $envelopesByFamID[$fam_ID];
            if ($newEnvelope <> $priorEnvelope) {
                $envelopesToWrite[$fam_ID] = $newEnvelope;
                $envelopesByFamID[$fam_ID] = $newEnvelope;
            }
        }
    }
    foreach ($envelopesToWrite as $fam_ID => $envelope) {
        $dSQL = "UPDATE family_fam SET fam_Envelope='" . $envelope . "' WHERE fam_ID='" . $fam_ID . "'";
        RunQuery($dSQL);
    }
}

if (isset($_POST["Classification"])) {
    $iClassification = $_POST["Classification"];
    $_SESSION['classification'] = $iClassification;
} elseif (isset ($_SESSION['classification'])) {
    $iClassification = $_SESSION['classification'];
} else {
    $iClassification = 0;
}

if (isset($_POST["SortBy"])) {
    $sSortBy = $_POST["SortBy"];
} else {
    $sSortBy = "name";
}
    
if (isset($_POST["AssignStartNum"])) {
    $iAssignStartNum = $_POST["AssignStartNum"];
} else {
    $iAssignStartNum = 1;
}



//Get Classifications for the drop-down
$sSQL = "SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence";
$rsClassifications = RunQuery($sSQL);
while ($aRow = mysql_fetch_array($rsClassifications)) {
    extract($aRow);
    $classification[$lst_OptionID] = $lst_OptionName;
}

require "Include/Header.php";

?>

<div class="box box-body">
<form method="post" action="ManageEnvelopes.php" name="ManageEnvelopes">
<?php

$duplicateEnvelopeHash = array();
$updateEnvelopes = 0;

// Service the action buttons
if (isset($_POST["PrintReport"])) {
    redirect ("Reports/EnvelopeReport.php");
} elseif (isset($_POST["AssignAllFamilies"])) {
    $newEnvNum = $iAssignStartNum;
    $envelopesToWrite = array(); // zero it out
    foreach ($familyArray as $fam_ID => $fam_Data) {
        $envelopesByFamID[$fam_ID] = $newEnvNum;
        $envelopesToWrite[$fam_ID] = $newEnvNum++;
    }
} elseif (isset($_POST["ZeroAll"])) {
    $envelopesByFamID = array(); // zero it out
    foreach ($familyArray as $fam_ID => $fam_Data) {
        $envelopesByFamID[$fam_ID] = 0;
        $envelopesToWrite[$fam_ID] = 0;
    }
}


    ?>



<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#updateEnvelopesModal"><?= gettext("Update Family Records") ?></button>
<button type="submit" class="btn" name="PrintReport"><i class="fa fa-print"></i></button>

<br><br>

<!-- Modal -->
<div class="modal fade" id="updateEnvelopesModal" tabindex="-1" role="dialog" aria-labelledby="updateEnvelopesModal" aria-hidden="true">
    <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="upload-Image-label"><?= gettext("Update Envelopes") ?></h4>
                </div>
                <div class="modal-body">
                <span style="color:red">This will overwrite the family envelope numbers in the database with those selected on this page.  Continue?</span>
                </div>
                <div class="modal-footer">
                    <input type="submit" class="btn btn-primary" value="<?= gettext("Confirm") ?>" name="Confirm">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?= gettext("Cancel") ?></button>
                </div>
            </div>
    </div>
</div>

<table class="table">
<thead>
<tr>
    <th>
    <b>Family Select</b> with at least one: 
        <select name="Classification">
        <option value="0"><?= gettext("All") ?></option>
        <?php
        foreach ($classification as $lst_OptionID => $lst_OptionName) {
            echo "<option value=\"" . $lst_OptionID . "\"";
            if ($iClassification == $lst_OptionID) echo " selected";
            echo ">" . $lst_OptionName . "&nbsp;";
        }
        ?>
        </select>
        <input type="submit" class="btn" value="<?= gettext("Sort by") ?>" name="Sort">
        <input type="radio" Name="SortBy" value="name"
        <?php if ($sSortBy == "name") echo " checked"; ?>><?= gettext("Last Name") ?>
        <input type="radio" Name="SortBy" value="envelope"
        <?php if ($sSortBy == "envelope") echo " checked"; ?>><?= gettext("Envelope #") ?>
    </th>
    <th>
        <b>Envelope</b>
        <input type="submit" class="btn" value="<?= gettext("Zero") ?>"
                 name="ZeroAll">
        <input type="submit" class="btn" value="<?= gettext("Assign starting at #") ?>"
                 name="AssignAllFamilies">
        <input type="text" name="AssignStartNum" value="<?= $iAssignStartNum ?>" maxlength="5">
    </th>
</tr>
</thead>

<?php

if ($sSortBy == "envelope") {
    asort($envelopesByFamID);
    $arrayToLoop = $envelopesByFamID;
} else {
    $arrayToLoop = $familyArray;
}

foreach ($arrayToLoop as $fam_ID => $value) {
    if ($sSortBy == "envelope") {
        $envelope = $value;
        $fam_Data = $familyArray[$fam_ID];
    } else {
        $fam_Data = $value;
        $envelope = $envelopesByFamID[$fam_ID];
    }
    echo "<tr>";
    echo "<td>" . $fam_Data . "&nbsp;</td>";
    if ($envelope and $duplicateEnvelopeHash and array_key_exists($envelope, $duplicateEnvelopeHash)) {
        $tdTag = "<td bgcolor='red'>";
    } else {
        $duplicateEnvelopeHash[$envelope] = $fam_ID;
        $tdTag = "<td>";
    }
    echo $tdTag;?><class="TextColumn">
    <input type="text" name="EnvelopeID_<?= $fam_ID ?>" value="<?= $envelope ?>" maxlength="10">
    </td></tr>
    <?php
}
?>    
</table><br>
</form>
</div>

<?php

// make an array of envelopes indexed by family id, subject to the classification filter if specified.
function getEnvelopes($classification) {
    if ($classification) {
        $sSQL = "SELECT fam_ID, fam_Envelope FROM family_fam LEFT JOIN person_per ON fam_ID = per_fam_ID WHERE per_cls_ID='" . $classification . "'";
    } else {
        $sSQL = "SELECT fam_ID, fam_Envelope FROM family_fam";
    }

    $sSQL .= " ORDER by fam_Envelope";
    $dEnvelopes = RunQuery($sSQL);
    $envelopes = array ();
    while ($aRow = mysql_fetch_array($dEnvelopes)) {
        extract($aRow);
        $envelopes[$fam_ID] = $fam_Envelope;
    }
    return $envelopes;
}

require "Include/Footer.php";
?>
