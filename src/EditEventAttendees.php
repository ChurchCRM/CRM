<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;

$sPageTitle = gettext('Church Event Editor');
require_once 'Include/Header.php';

$nameFormat = (int)SystemConfig::getValue('iPersonNameStyle');

$sAction = $_POST['Action'];
$EventID = InputUtils::legacyFilterInput($_POST['EID'], 'int'); // from ListEvents button=Attendees
$EvtName = $_POST['EName'];
$EvtDesc = $_POST['EDesc'];
$EvtDate = $_POST['EDate'];

// Process the action inputs
if ($sAction == 'Delete') {
    $dpeEventID = InputUtils::legacyFilterInput($_POST['DelPerEventID'], 'int');
    $dpePerID = InputUtils::legacyFilterInput($_POST['DelPerID'], 'int');
    $attendance = EventAttendQuery::create()
        ->filterByEventId($dpeEventID)
        ->filterByPersonId($dpePerID)
        ->findOne();
    if ($attendance !== null) {
        $attendance->delete();
    }
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
      <td width="15%" class="text-nowrap"><strong><?= gettext('Action') ?></strong></td>
  </tr>
<?php
$attendees = EventAttendQuery::create()
    ->filterByEventId($EventID)
    ->innerJoinWithPerson()
    ->orderByPersonId()
    ->find();

if ($attendees->count() > 0) {
    $sRowClass = 'RowColorA';
    foreach ($attendees as $attendance) {
        $person = $attendance->getPerson();
        $sRowClass = AlternateRowStyle($sRowClass);

        $family = $person->getFamily();
        $per_Country = $person->getCountry();
        $fam_Country = $family ? $family->getCountry() : '';

        $sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, false);

        $per_HomePhone = $person->getHomePhone();
        $fam_HomePhone = $family ? $family->getHomePhone() : '';
        $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy), ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), true);
        
        $per_Email = $person->getEmail();
        $fam_Email = $family ? $family->getEmail() : '';
        $sEmail = SelectWhichInfo($per_Email, $fam_Email, false); ?>
    <tr class="<?= $sRowClass ?>">
        <td class="TextColumn"><?= $person->getFormattedName($nameFormat) ?></td>
        <td class="TextColumn"><?= $sEmail ? '<a href="mailto:' . $sEmail . '" title="Send Email">' . $sEmail . '</a>' : 'Not Available' ?></td>
        <td class="TextColumn"><?= $sHomePhone ? $sHomePhone : 'Not Available' ?></td>
    <td  class="TextColumn text-center" colspan="1">
      <form method="POST" action="EditEventAttendees.php" name="DeletePersonFromEvent">
          <input type="hidden" name="DelPerID" value="<?= $person->getId() ?>">
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
<tr><td colspan="4" class="text-center"><?= gettext('No Attendees Assigned to Event') ?></td></tr>
    <?php
}

?>
</table>
</div>
<?php
require_once 'Include/Footer.php';
