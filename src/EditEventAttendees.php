<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;

$sPageTitle = gettext('Church Event Editor');
require_once 'Include/Header.php';

// Safely fetch inputs (use legacyFilterInputArr to avoid undefined index warnings)
$sAction = InputUtils::legacyFilterInputArr($_POST, 'Action');
$EventID = InputUtils::legacyFilterInputArr($_POST, 'EID', 'int'); // from ListEvents button=Attendees
$EvtName = InputUtils::legacyFilterInputArr($_POST, 'EName');
$EvtDesc = InputUtils::legacyFilterInputArr($_POST, 'EDesc');
$EvtDate = InputUtils::legacyFilterInputArr($_POST, 'EDate');

// Process the action inputs
if ($sAction == 'Delete') {
    $dpeEventID = InputUtils::legacyFilterInputArr($_POST, 'DelPerEventID', 'int');
    $dpePerID = InputUtils::legacyFilterInputArr($_POST, 'DelPerID', 'int');
    if ($dpeEventID && $dpePerID) {
        // Use Propel to delete the attendance row (prevents SQL injection)
        EventAttendQuery::create()
            ->filterByEventId($dpeEventID)
            ->filterByPersonId($dpePerID)
            ->delete();
    }
}


if (empty($EventID)) {
    $EventID = InputUtils::legacyFilterInputArr($_GET, 'eventId', 'int');
}

$event = EventQuery::create()->findPk((int) $EventID);

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
    <div class="mb-3">
      <a href="Checkin.php?eventId=<?= $EventID ?>" class="btn btn-primary" title="<?= gettext('Check people in to this event') ?>">
        <i class="fas fa-check-circle"></i> <?= gettext('Check In People') ?>
      </a>
    </div>
    <form method="post" action="EditEventAttendees.php" name="AttendeeEditor">
      <input type="hidden" name="EID" value="<?= $EventID  ?>">
  <table class="table table-sm table-striped">
  <thead>
  <tr>
    <th width="35%"><?= gettext('Name') ?></th>
    <th width="25%"><?= gettext('Email') ?></th>
    <th width="25%"><?= gettext('Home Phone') ?></th>
      <th width="15%" class="text-nowrap"><?= gettext('Action') ?></th>
  </tr>
  </thead>
  <tbody>
<?php
// Fetch attendees using Propel to avoid raw SQL
$attendees = EventAttendQuery::create()
    ->filterByEventId((int) $EventID)
    ->joinWithPerson()
    ->usePersonQuery()
    ->orderByLastName()
    ->orderByFirstName()
    ->endUse()
    ->find();

if ($attendees->count() != 0) {
    foreach ($attendees as $att) {
        $person = $att->getPerson();
        if ($person === null) {
            continue;
        }

        $family = $person->getFamily();

        $famCountry = $family ? $family->getFamCountry() : null;
        $sPhoneCountry = SelectWhichInfo($person->getPerCountry(), $famCountry, false);
        $sHomePhone = SelectWhichInfo(
            ExpandPhoneNumber($person->getPerHomephone(), $sPhoneCountry, $dummy),
            ExpandPhoneNumber($family ? $family->getFamHomePhone() : null, $famCountry, $dummy),
            true
        );
        $sEmail = SelectWhichInfo($person->getPerEmail(), $family ? $family->getFamEmail() : null, false);
?>
    <tr>
        <td class="TextColumn"><?= FormatFullName($person->getPerTitle(), $person->getPerFirstName(), $person->getPerMiddleName(), $person->getPerLastName(), $person->getPerSuffix(), 3) ?></td>
        <td class="TextColumn"><?= $sEmail ? '<a href="mailto:' . $sEmail . '" title="Send Email">' . $sEmail . '</a>' : 'Not Available' ?></td>
        <td class="TextColumn"><?= $sHomePhone ? $sHomePhone : 'Not Available' ?></td>
    <td class="TextColumn text-center" colspan="1">
      <form method="POST" action="EditEventAttendees.php" name="DeletePersonFromEvent">
          <input type="hidden" name="DelPerID" value="<?= $person->getPerId() ?>">
          <input type="hidden" name="DelPerEventID" value="<?= $EventID ?>">
          <input type="hidden" name="EID" value="<?= $EventID ?>">
          <input type="hidden" name="EName" value="<?= htmlspecialchars($event->getTitle()) ?>">
          <input type="hidden" name="EDesc" value="<?= htmlspecialchars($event->getDesc()) ?>">
          <input type="hidden" name="EDate" value="<?= $event->getStart()->format('Y-m-d H:i') ?>">
          <input type="submit" name="Action" value="<?= gettext('Delete') ?>" class="btn btn-danger" onClick="return confirm('<?= gettext('Are you sure you want to DELETE this person from Event ID: ') . $EventID ?>')">
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
</tbody>
</table>
</div>
<?php
require_once 'Include/Footer.php';
