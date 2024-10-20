<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$events = null;
$aEventID = [];
$aEventTitle = [];
$aEventStartDateTime = [];

$sPostChoice = null;
if (array_key_exists('Choice', $_POST)) {
    $sPostChoice = InputUtils::legacyFilterInput($_POST['Choice']);
}

$sPostAction = null;
if (array_key_exists('Action', $_POST)) {
    $sPostAction = InputUtils::legacyFilterInput($_POST['Action']);
}

$sGetAction = null;
if (array_key_exists('Action', $_GET)) {
    $sGetAction = InputUtils::legacyFilterInput($_GET['Action']);
}

$sPostEvent = null;
if (array_key_exists('Event', $_POST)) {
    $sPostEvent = InputUtils::legacyFilterInput($_POST['Event']);
}

$sGetEvent = null;
if (array_key_exists('Event', $_GET)) {
    $sGetEvent = InputUtils::legacyFilterInput($_GET['Event']);
}

$sGetType = null;
if (array_key_exists('Type', $_GET)) {
    $sGetType = InputUtils::legacyFilterInput($_GET['Type']);
}

if ($sPostAction === 'Retrieve' && !empty($sPostEvent)) {
    $iEventId = InputUtils::legacyFilterInput($sPostEvent, 'int');

    if ($sPostChoice === 'Attendees') {
        $sSQL = 'SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_Email, t1.per_HomePhone, t1.per_Country, t1.per_MembershipDate, t4.fam_HomePhone, t4.fam_Country, t1.per_Gender
            FROM person_per AS t1, events_event AS t2, event_attend AS t3, family_fam AS t4
            WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ' . $iEventId . " AND t1.per_fam_ID = t4.fam_ID AND per_cls_ID IN ('1','2','5')
    ORDER BY t1.per_LastName, t1.per_ID";
        $sPageTitle = gettext('Event Attendees');
    } elseif ($sPostChoice === 'Nonattendees') {
        $aSQL = 'SELECT DISTINCT(person_id) FROM event_attend WHERE event_id = ' . $iEventId;
        $raOpps = RunQuery($aSQL);
        $aArr = [];
        while ($aRow = mysqli_fetch_row($raOpps)) {
            $aArr[] = $aRow[0];
        }
        if (count($aArr) > 0) {
            $aArrJoin = implode(',', $aArr);
            $sSQL = 'SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_Email, t1.per_HomePhone, t1.per_Country, t1.per_MembershipDate, t2.fam_HomePhone, t2.fam_Country, t1.per_Gender
                FROM person_per AS t1, family_fam AS t2
                WHERE t1.per_fam_ID = t2.fam_ID AND t1.per_ID NOT IN (' . $aArrJoin . ") AND per_cls_ID IN ('1','2','5')
        ORDER BY t1.per_LastName, t1.per_ID";
        } else {
            $sSQL = "SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_Email, t1.per_HomePhone, t1.per_Country, t1.per_MembershipDate, t2.fam_HomePhone, t2.fam_Country, t1.per_Gender
                    FROM person_per AS t1, family_fam AS t2
                    WHERE t1.per_fam_ID = t2.fam_ID AND per_cls_ID IN ('1','2','5')
        ORDER BY t1.per_LastName, t1.per_ID";
        }
        $sPageTitle = gettext('Event Nonattendees');
    } elseif ($sPostChoice === 'Guests') {
        $sSQL = 'SELECT t1.per_ID, t1.per_Title, t1.per_FirstName, t1.per_MiddleName, t1.per_LastName, t1.per_Suffix, t1.per_HomePhone, t1.per_Country, t1.per_Gender
            FROM person_per AS t1, events_event AS t2, event_attend AS t3
            WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ' . $iEventId . " AND per_cls_ID IN ('0','3')
    ORDER BY t1.per_LastName, t1.per_ID";
        $sPageTitle = gettext('Event Guests');
    }
} elseif ($sGetAction === 'List' && !empty($sGetEvent)) {
    $sSQL = 'SELECT * FROM events_event WHERE event_type = ' . $sGetEvent . ' ORDER BY event_start';

    // Page title text was changed from "All $_GET['Type'] Events" to "All Events of type $_GET['Type']" because it doesn't work for portuguese, spanish, french, and so on
    $sPageTitle = gettext('All Events of Type') . ': ' . $sGetType;
} else {
    $sSQL = 'SELECT * FROM events_event ORDER BY event_start';
}
require_once 'Include/Header.php';

// Get data for the form as it now exists..
$rsOpps = RunQuery($sSQL);
$numRows = mysqli_num_rows($rsOpps);

// Create arrays of the attendees.
for ($row = 1; $row <= $numRows; $row++) {
    $aRow = mysqli_fetch_assoc($rsOpps);

    $aPersonID[$row] = $aRow['per_ID'];
    $aTitle[$row] = $aRow['per_Title'];
    $aFirstName[$row] = $aRow['per_FirstName'];
    $aMiddleName[$row] = $aRow['per_MiddleName'];
    $aLastName[$row] = $aRow['per_LastName'];
    $aSuffix[$row] = $aRow['per_Suffix'];
    $aEmail[$row] = $aRow['per_Email'];
    $aGender[$row] = $aRow['per_Gender'] == 1 ? gettext("Male") : gettext("Female");

    $aHomePhone[$row] = SelectWhichInfo(
        ExpandPhoneNumber(
            $aRow['per_HomePhone'],
            $aRow['per_Country'],
            $dummy
        ),
        ExpandPhoneNumber(
            $aRow['fam_HomePhone'],
            $aRow['fam_Country'],
            $dummy
        ),
        true
    );
}

$eventsQuery = EventQuery::create()->orderByStart(Criteria::DESC);
if (array_key_exists('Action', $_GET) && $_GET['Action'] === 'List' && !empty($_GET['Event'])) {
    $eventType = EventTypeQuery::create()->findOneById($_GET['Event']);
    $eventsQuery = $eventsQuery->filterByEventType($eventType);

    //  text is All Events of type $_GET['Type'], because it doesn't work for portuguese, spanish, french, etc
    $sPageTitle = gettext('All Events of Type') . ': ' . $_GET['Type'];
}

$events = $eventsQuery->find();
$numRows = $events->count();
for ($row = 1; $row <= $events->count(); $row++) {
    $event = $events[$row - 1];
    $aEventID[$row] = $event->getId();
    $aEventTitle[$row] = htmlentities(stripslashes($event->getTitle()), ENT_NOQUOTES, 'UTF-8');
    $aEventStartDateTime[$row] = $event->getStart(\DateTimeInterface::ATOM);
}

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
            <table class="table table-striped data-table" id="eventsTable">
                <thead>
                    <tr class="TableHeader">
                        <td width="33%"><strong><?= gettext('Event Title') ?></strong></td>
                        <td width="33%"><strong><?= gettext('Event Date') ?></strong></td>
                        <td> </td>
                        <td> </td>
                        <td> </td>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($row = 1; $row <= $numRows; $row++) { ?>
                        <tr>
                            <td><?= $aEventTitle[$row] ?></td>
                            <td><?= FormatDate($aEventStartDateTime[$row], 1) ?></td>
                            <td align="center">
                                <form name="Attend" action="EventAttendance.php" method="POST">
                                    <input type="hidden" name="Event" value="<?= $aEventID[$row] ?>">
                                    <input type="hidden" name="Type" value="<?= $sGetType ?>">
                                    <input type="hidden" name="Action" value="Retrieve">
                                    <input type="hidden" name="Choice" value="Attendees">
                                    <?php
                                    $cSQL = 'SELECT COUNT(per_ID) AS cCount
         FROM person_per as t1, events_event as t2, event_attend as t3
         WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ' . $aEventID[$row] . " AND per_cls_ID IN ('1','2','5')";
                                    $cOpps = RunQuery($cSQL);
                                    $cNumAttend = mysqli_fetch_row($cOpps)[0];
                                    $tSQL = "SELECT COUNT(per_ID) AS tCount
         FROM person_per
         WHERE per_cls_ID IN ('1','2','5')";
                                    $tOpps = RunQuery($tSQL);
                                    $tNumTotal = mysqli_fetch_row($tOpps)[0]; ?>
                                    <input type="submit" name="Type" value="<?= gettext('Attending Members') . ' [' . $cNumAttend . ']' ?>" class="btn btn-default">
                                </form>
                            </td>
                            <td>
                                <form name="NonAttend" action="EventAttendance.php" method="POST">
                                    <input type="hidden" name="Event" value="<?= $aEventID[$row] ?>">
                                    <input type="hidden" name="Type" value="<?= $sGetType ?>">
                                    <input type="hidden" name="Action" value="Retrieve">
                                    <input type="hidden" name="Choice" value="Nonattendees">
                                    <input id="Non-Attending-<?= $row ?>" type="submit" name="Type" value="<?= gettext('Non-Attending Members') . ' [' . ($tNumTotal - $cNumAttend) . ']' ?>" class="btn btn-default">
                                </form>
                            </td>
                            <td>
                                <form name="GuestAttend" action="EventAttendance.php" method="POST">
                                    <input type="hidden" name="Event" value="<?= $aEventID[$row] ?>">
                                    <input type="hidden" name="Type" value="<?= $sGetType ?>">
                                    <input type="hidden" name="Action" value="Retrieve">
                                    <input type="hidden" name="Choice" value="Guests">
                                    <?php $gSQL = 'SELECT COUNT(per_ID) AS gCount FROM person_per as t1, events_event as t2, event_attend as t3
                     WHERE t1.per_ID = t3.person_id AND t2.event_id = t3.event_id AND t3.event_id = ' . $aEventID[$row] . ' AND per_cls_ID = 3';
                                    $gOpps = RunQuery($gSQL);
                                    $gNumGuestAttend = mysqli_fetch_row($gOpps)[0]; ?>
                                    <input <?= ($gNumGuestAttend == 0 ? 'type="button"' : 'type="submit"') ?> name="Type" value="<?= gettext('Guests') . ' [' . $gNumGuestAttend . ']' ?>" class="btn btn-default">
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $(document).ready(function() {
            $("#eventsTable").DataTable(window.CRM.plugin.dataTable);
        });
    </script>
<?php } elseif ($sPostAction === 'Retrieve' && $numRows > 0) { ?>
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
                    <?php for ($row = 1; $row <= $numRows; $row++) { ?>
                        <tr>
                            <td><?= FormatFullName($aTitle[$row], $aFirstName[$row], $aMiddleName[$row], $aLastName[$row], $aSuffix[$row], 3) ?></td>
                            <td><?= $aEmail[$row] ? '<a href="mailto:' . $aEmail[$row] . '">' . $aEmail[$row] . '</a>' : '' ?></td>
                            <td><?= $aHomePhone[$row] ? $aHomePhone[$row] : '' ?></td>
                            <td><?= $aGender[$row] ?></td>
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
