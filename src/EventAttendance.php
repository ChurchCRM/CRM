<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$sPageTitle = gettext('Church Event Editor');
$nameFormat = (int)SystemConfig::getValue('iPersonNameStyle');

// Get input parameters
$sPostChoice = InputUtils::legacyFilterInput($_POST['Choice'] ?? null);
$sPostAction = InputUtils::legacyFilterInput($_POST['Action'] ?? null);
$sGetAction = InputUtils::legacyFilterInput($_GET['Action'] ?? null);
$sPostEvent = InputUtils::legacyFilterInput($_POST['Event'] ?? null);
$sGetEvent = InputUtils::legacyFilterInput($_GET['Event'] ?? null);
$sGetType = InputUtils::legacyFilterInput($_GET['Type'] ?? null);

if ($sPostAction === 'Retrieve' && !empty($sPostEvent)) {
    $iEventId = InputUtils::legacyFilterInput($sPostEvent, 'int');

    if ($sPostChoice === 'Attendees') {
        // Get attendees for the event who are members (class ID 1, 2, 5)
        $attendees = EventAttendQuery::create()
            ->filterByEventId($iEventId)
            ->innerJoinPerson()
            ->usePersonQuery()
                ->filterByClsId([1, 2, 5], Criteria::IN)
            ->endUse()
            ->find();
        
        $people = [];
        foreach ($attendees as $attendance) {
            $people[] = $attendance->getPerson();
        }
        
        $sPageTitle = gettext('Event Attendees');
    } elseif ($sPostChoice === 'Nonattendees') {
        // Get all attendee person IDs for this event
        $attendeeIds = EventAttendQuery::create()
            ->select('PersonId')
            ->filterByEventId($iEventId)
            ->find()
            ->toArray();
        
        // Get all members NOT in the attendee list
        $query = PersonQuery::create()
            ->filterByClsId([1, 2, 5], Criteria::IN);
        
        if (!empty($attendeeIds)) {
            $query->filterById($attendeeIds, Criteria::NOT_IN);
        }
        
        $people = $query->orderByLastName()->orderById()->find();
        $sPageTitle = gettext('Event Nonattendees');
    } elseif ($sPostChoice === 'Guests') {
        // Get guest attendees for the event (class ID 0, 3)
        $attendees = EventAttendQuery::create()
            ->filterByEventId($iEventId)
            ->innerJoinPerson()
            ->usePersonQuery()
                ->filterByClsId([0, 3], Criteria::IN)
            ->endUse()
            ->find();
        
        $people = [];
        foreach ($attendees as $attendance) {
            $people[] = $attendance->getPerson();
        }
        
        $sPageTitle = gettext('Event Guests');
    }
}

require_once 'Include/Header.php';

$eventsQuery = EventQuery::create()->orderByStart(Criteria::DESC);
if (array_key_exists('Action', $_GET) && $_GET['Action'] === 'List' && !empty($_GET['Event'])) {
    $eventType = EventTypeQuery::create()->findOneById($_GET['Event']);
    $eventsQuery = $eventsQuery->filterByEventType($eventType);

    //  text is All Events of type $_GET['Type'], because it doesn't work for portuguese, spanish, french, etc
    $sPageTitle = gettext('All Events of Type') . ': ' . $_GET['Type'];
}

$events = $eventsQuery->find();
$numRows = $events->count();

// Construct the form
?>

<div class="card">
    <div class="card-header">
        <h3><?php echo gettext("Event Attendance"); ?></h3>
    </div>
    <div class="card-body">
        <?php echo gettext("Generate attendance -AND- non-attendance reports for events"); ?>
        <p><br /></p>
        <?php
        //$sSQL = "SELECT * FROM event_types";
        $sSQL = "SELECT DISTINCT event_types.* FROM event_types RIGHT JOIN events_event ON event_types.type_id=events_event.event_type ORDER BY type_id ";
        $rsOpps = RunQuery($sSQL);
        $numRows2 = mysqli_num_rows($rsOpps);

        // List all events
        for ($row = 1; $row <= $numRows2; $row++) {
            $aRow = mysqli_fetch_array($rsOpps);
            echo '&nbsp;&nbsp;&nbsp;<a href="EventAttendance.php?Action=List&Event=' .
            $aRow['type_id'] . '&Type=' . gettext($aRow['type_name']) . '" title="List All ' .
                gettext($aRow['type_name']) . ' Events"><strong>' . gettext($aRow['type_name']) .
                '</strong></a>' . "<br>\n";
        }
        ?>
    </div>
</div>

<?php if ($sGetAction === 'List' && $numRows > 0) { ?>
    <div class="card">
        <div class="card-header">
            <h3> <?= ($numRows == 1 ? gettext('There is') : gettext('There are')) . ' ' . $numRows . ' ' . ($numRows == 1 ? gettext('event') : gettext('events')) . gettext(' in this category.') ?></h3>
        </div>
        <div class="card-body">
            <style>
                #eventsTable { table-layout: fixed; width: 100%; }
                #eventsTable td { word-wrap: break-word; overflow-wrap: break-word; padding: 6px 4px; }
                #eventsTable button { width: 100%; padding: 4px 2px; font-size: 12px; }
                #eventsTable .btn-sm { padding: 2px 4px; }
            </style>
            <div class="table-responsive">
            <table class="table table-striped data-table" id="eventsTable">
                <thead>
                    <tr class="TableHeader">
                        <td width="40%"><strong><?= gettext('Event Title') ?></strong></td>
                        <td width="13%"><strong><?= gettext('Date') ?></strong></td>
                        <td width="15%"><strong><?= gettext('Attendees') ?></strong></td>
                        <td width="15%"><strong><?= gettext('Non-Att.') ?></strong></td>
                        <td width="17%"><strong><?= gettext('Guests') ?></strong></td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event) { 
                        $eventId = $event->getId();
                        $eventTitle = htmlentities(stripslashes($event->getTitle()), ENT_NOQUOTES, 'UTF-8');
                        $eventStartDateTime = $event->getStart(\DateTimeInterface::ATOM);
                    ?>
                        <tr>
                            <td><?= $eventTitle ?></td>
                            <td><?= FormatDate($eventStartDateTime, 1) ?></td>
                            <td>
                                <form action="EventAttendance.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="Event" value="<?= $eventId ?>">
                                    <input type="hidden" name="Type" value="<?= $sGetType ?>">
                                    <input type="hidden" name="Action" value="Retrieve">
                                    <input type="hidden" name="Choice" value="Attendees">
                                    <?php
                                    // Count attending members for this event
                                    $cNumAttend = EventAttendQuery::create()
                                        ->filterByEventId($eventId)
                                        ->innerJoinPerson()
                                        ->usePersonQuery()
                                            ->filterByClsId([1, 2, 5], Criteria::IN)
                                        ->endUse()
                                        ->count();
                                    
                                    // Count total members
                                    $tNumTotal = PersonQuery::create()
                                        ->filterByClsId([1, 2, 5], Criteria::IN)
                                        ->count(); ?>
                                    <button type="submit" class="btn btn-sm btn-info"><?= $cNumAttend ?></button>
                                </form>
                            </td>
                            <td>
                                <form action="EventAttendance.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="Event" value="<?= $eventId ?>">
                                    <input type="hidden" name="Type" value="<?= $sGetType ?>">
                                    <input type="hidden" name="Action" value="Retrieve">
                                    <input type="hidden" name="Choice" value="Nonattendees">
                                    <button type="submit" class="btn btn-sm btn-warning"><?= ($tNumTotal - $cNumAttend) ?></button>
                                </form>
                            </td>
                            <td>
                                <form action="EventAttendance.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="Event" value="<?= $eventId ?>">
                                    <input type="hidden" name="Type" value="<?= $sGetType ?>">
                                    <input type="hidden" name="Action" value="Retrieve">
                                    <input type="hidden" name="Choice" value="Guests">
                                    <?php 
                                    // Count guest attendees for this event
                                    $gNumGuestAttend = EventAttendQuery::create()
                                        ->filterByEventId($eventId)
                                        ->innerJoinPerson()
                                        ->usePersonQuery()
                                            ->filterByClsId([0, 3], Criteria::IN)
                                        ->endUse()
                                        ->count(); ?>
                                    <button type="submit" class="btn btn-sm btn-success" <?= ($gNumGuestAttend == 0 ? 'disabled' : '') ?>><?= $gNumGuestAttend ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $(document).ready(function() {
            var dataTableConfig = $.extend({}, window.CRM.plugin.dataTable);
            dataTableConfig.responsive = false;
            dataTableConfig.columnDefs = [{ responsivePriority: false, targets: -1 }];
            $("#eventsTable").DataTable(dataTableConfig);
        });
    </script>
<?php } elseif ($sPostAction === 'Retrieve' && isset($people) && count($people) > 0) { 
    $numRows = count($people); ?>
    <div class="card">
        <div class="card-header">
            <h3><?= gettext('There ' . ($numRows == 1 ? 'was ' . $numRows . ' ' . $sPostChoice : 'were ' . $numRows . ' ' . $sPostChoice)) . ' for this Event' ?></h3>
        </div>
        <div class="card-body">
            <table class="table table-striped data-table" id="peopleTable">
                <thead>
                    <tr>
                        <td width="35%"><strong><?= gettext('Name') ?></strong></td>
                        <td><strong><?= gettext('Email') ?></strong></td>
                        <td><strong><?= gettext('Home Phone') ?></strong></td>
                        <td><strong><?= gettext('Gender') ?></strong></td>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $person) {
                        $family = $person->getFamily();
                        $per_Country = $person->getCountry();
                        $fam_Country = $family ? $family->getCountry() : '';
                        
                        $sPhoneCountry = SelectWhichInfo($per_Country, $fam_Country, false);
                        
                        $per_HomePhone = $person->getHomePhone();
                        $fam_HomePhone = $family ? $family->getHomePhone() : '';
                        $sHomePhone = SelectWhichInfo(ExpandPhoneNumber($per_HomePhone, $sPhoneCountry, $dummy), ExpandPhoneNumber($fam_HomePhone, $fam_Country, $dummy), true);
                        
                        $per_Email = $person->getEmail();
                        $fam_Email = $family ? $family->getEmail() : '';
                        $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
                        
                        $genderText = $person->getGender() == 1 ? gettext("Male") : gettext("Female");
                    ?>
                        <tr>
                            <td><?= $person->getFormattedName($nameFormat) ?></td>
                            <td><?= $sEmail ? '<a href="mailto:' . $sEmail . '">' . $sEmail . '</a>' : '' ?></td>
                            <td><?= $sHomePhone ? $sHomePhone : '' ?></td>
                            <td><?= $genderText ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $(document).ready(function() {
            $("#peopleTable").DataTable(window.CRM.plugin.dataTable);
        });
    </script>
<?php } else { ?>
    <div class="warning">
        <?= $_GET ? gettext('There are no events in this category') : "" ?>
    </div>
<?php }
require_once 'Include/Footer.php';
