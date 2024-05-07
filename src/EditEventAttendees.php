<?php
//*******************************************************************************

require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\EventQuery;

$sPageTitle = gettext('Church Event Editor');
require 'Include/Header.php';

$sAction = $_POST['Action'];
$EventID = $_POST['EID']; // from ListEvents button=Attendees
$EvtName = $_POST['EName'];
$EvtDesc = $_POST['EDesc'];
$EvtDate = $_POST['EDate'];
//
// process the action inputs
//
if ($sAction == 'Delete') {
    $dpeEventID = InputUtils::legacyFilterInput($_POST['DelPerEventID'], 'int');
    $dpePerID = InputUtils::legacyFilterInput($_POST['DelPerID'], 'int');
    $dpeSQL = "DELETE FROM event_attend WHERE event_id=$dpeEventID AND person_id=$dpePerID LIMIT 1";
    RunQuery($dpeSQL);
    $ShowAttendees = 1;
}

if ($EventID === null) {
    $EventID = InputUtils::legacyFilterInput($_GET['eventId'], 'int');
}

$event = EventQuery::create()->findPk($EventID);

if (empty($event)) {
    RedirectUtils::redirect('ListEvents.php');
}

// Construct the form
?>
<div class='card'>
  <div class='card-header'>
    <h3 class='card-title'><?= gettext('Attendees for Event') . ' : ' . $event->getTitle() ?></h3>
  </div>
  <div class='card-body'>
    <strong><?= gettext('Name')?>:</strong> <?= $EvtName ?><br/>
    <strong><?= gettext('Date')?>:</strong> <?= $EvtDate ?><br/>
    <strong><?= gettext('Description')?>:</strong><br/>
    <?= $EvtDesc ?>
    <p/>
    <form method="post" action="EditEventAttendees.php" name="AttendeeEditor">
      <input type="hidden" name="EID" value="<?= $EventID  ?>">
  <table class="table">
  <tr class="TableHeader">
    <td width="35%"><strong><?= gettext('Name') ?></strong></td>
    <td width="25%"><strong><?= gettext('Email') ?></strong></td>
    <td width="25%"><strong><?= gettext('Home Phone') ?></strong></td>
      <td width="15%" nowrap><strong><?= gettext('Action') ?></strong></td>
  </tr>
<?php
$sSQL = 'SELECT person_id, per_LastName FROM event_attend JOIN person_per ON person_per.per_id = event_attend.person_id WHERE event_id = ' . $EventID . ' ORDER by per_LastName, per_FirstName';
$rsOpps = RunQuery($sSQL);
$numAttRows = mysqli_num_rows($rsOpps);
if ($numAttRows != 0) {
    $sRowClass = 'RowColorA';
    for ($na = 0; $na < $numAttRows; $na++) {
        $attRow = mysqli_fetch_array($rsOpps, MYSQLI_BOTH);
        extract($attRow);
        $sSQL = 'SELECT per_Title, per_ID, per_FirstName, per_MiddleName, per_LastName, per_Suffix, per_Email, per_HomePhone, per_Country, fam_HomePhone, fam_Email, fam_Country FROM person_per LEFT JOIN family_fam ON per_fam_id=fam_id WHERE per_ID = ' . $person_id;
        $perOpps = RunQuery($sSQL);
        $perRow = mysqli_fetch_array($perOpps, MYSQLI_BOTH);
        extract($perRow);
        $sRowClass = AlternateRowStyle($sRowClass);

        $sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, false);
        $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy), ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), true);
        $sEmail = SelectWhichInfo($per_Email, $fam_Email, false); ?>
    <tr class="<?= $sRowClass ?>">
        <td class="TextColumn"><?= FormatFullName($per_Title, $per_FirstName, $per_MiddleName, $per_LastName, $per_Suffix, 3) ?></td>
        <td class="TextColumn"><?= $sEmail ? '<a href="mailto:' . $sEmail . '" title="Send Email">' . $sEmail . '</a>' : 'Not Available' ?></td>
        <td class="TextColumn"><?= $sHomePhone ? $sHomePhone : 'Not Available' ?></td>
    <td  class="TextColumn" colspan="1" align="center">
      <form method="POST" action="EditEventAttendees.php" name="DeletePersonFromEvent">
          <input type="hidden" name="DelPerID" value="<?= $per_ID ?>">
          <input type="hidden" name="DelPerEventID" value="<?= $EventID ?>">
          <input type="hidden" name="EID" value="<?= $EventID ?>">
          <input type="hidden" name="EName" value="<?= $EvtName ?>">
          <input type="hidden" name="EDesc" value="<?= $EvtDesc ?>">
          <input type="hidden" name="EDate" value="<?= $EvtDate ?>">
          <input type="submit" name="Action" value="<?= gettext('Delete') ?>" class="btn btn-danger" onClick="return confirm("<?= gettext('Are you sure you want to DELETE this person from Event ID: ') . $EventID ?>")">
      </form>
     </td>
    </tr>
        <?php
    }
} else {
    ?>
<tr><td colspan="4" align="center"><?= gettext('No Attendees Assigned to Event') ?></td></tr>
    <?php
}

?>
</table>
</div>
<?php require 'Include/Footer.php' ?>
