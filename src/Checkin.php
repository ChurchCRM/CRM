<?php

$sPageTitle = gettext('Event Checkin');

require_once 'Include/Config.php';
require_once 'Include/Functions.php';
require_once 'Include/Header.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$EventID = 0;
$CheckoutOrDelete = false;
$event = null;
$iChildID = 0;
$iAdultID = 0;
$directEventAccess = false;

// Check for AddedCount notification from CartToEvent redirect
$iAddedCount = isset($_GET['AddedCount']) ? (int)$_GET['AddedCount'] : 0;

// Check GET parameter for eventId (from ListEvents or cart links)
if (array_key_exists('EventID', $_GET)) {
    $EventID = InputUtils::legacyFilterInput($_GET['EventID'], 'int');
    $directEventAccess = true; // Hide filter UI when accessing event directly
} elseif (array_key_exists('eventId', $_GET)) {
    $EventID = InputUtils::legacyFilterInput($_GET['eventId'], 'int');
    $directEventAccess = true;
}
if (array_key_exists('EventID', $_POST)) {
    $EventID = InputUtils::legacyFilterInput($_POST['EventID'], 'int');
} // from ListEvents button=Attendees or form submission
if (isset($_POST['CheckOutBtn']) || isset($_POST['DeleteBtn'])) {
    $CheckoutOrDelete =  true;
}

if (isset($_POST['child-id'])) {
    $iChildID = InputUtils::legacyFilterInput($_POST['child-id'], 'int');
}
if (isset($_POST['adult-id'])) {
    $iAdultID = InputUtils::legacyFilterInput($_POST['adult-id'], 'int');
}

// Event type filter (only apply when not accessing event directly)
$eventTypeId = 0;
if (!$directEventAccess) {
    if (array_key_exists('EventTypeID', $_POST)) {
        $eventTypeId = InputUtils::legacyFilterInput($_POST['EventTypeID'], 'int');
    }
    if (array_key_exists('EventTypeID', $_GET)) {
        $eventTypeId = InputUtils::legacyFilterInput($_GET['EventTypeID'], 'int');
    }
}

// Get all active event types
$eventTypes = EventTypeQuery::create()
    ->filterByActive(true)
    ->orderByName()
    ->find();

// Build active events query with optional type filter
$activeEventsQuery = EventQuery::create()
    ->filterByInActive(1, Criteria::NOT_EQUAL)
    ->orderByStart(Criteria::DESC);

if ($eventTypeId > 0 && !$directEventAccess) {
    $eventType = EventTypeQuery::create()->findOneById($eventTypeId);
    if ($eventType) {
        $activeEventsQuery->filterByEventType($eventType);
    }
}

$activeEvents = $activeEventsQuery->find();

if ($EventID > 0) {
    //get Event Details
    $event = EventQuery::create()
        ->findOneById($EventID);
}
?>
<div id="errorcallout" class="alert alert-danger" hidden></div>

<?php if ($iAddedCount > 0): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fa-solid fa-check-circle mr-2"></i>
    <strong><?= $iAddedCount ?></strong> <?= ngettext('person', 'people', $iAddedCount) ?> <?= gettext('added to this event') ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<?php if ($directEventAccess && $event !== null): ?>
<!-- Direct Event Access - Show event info bar with option to change -->
<div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
    <span>
        <i class="fas fa-calendar-check mr-2"></i>
        <strong><?= gettext('Event') ?>:</strong> <?= InputUtils::escapeHTML($event->getTitle()) ?> 
        <span class="text-muted">(<?= $event->getStart('M j, Y') ?>)</span>
    </span>
    <div>
        <a href="EventEditor.php?EID=<?= $EventID ?>" class="btn btn-sm btn-outline-primary mr-2">
            <i class="fas fa-pen mr-1"></i><?= gettext('Edit Event') ?>
        </a>
        <a href="Checkin.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-exchange-alt mr-1"></i><?= gettext('Change Event') ?>
        </a>
    </div>
</div>
<?php else: ?>
<!--Select Event Form -->
<form class="well form-horizontal" name="selectEvent" action="Checkin.php" method="POST">
    <input type="hidden" name="EventTypeID" id="EventTypeIDHidden" value="<?= $eventTypeId ?>">
    <div class="row">
        <div class="col-12">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Select Event for Check-In') ?></h3>
                </div>
                <div class="card-body">
                    <?php if ($sGlobalMessage) : ?>
                        <p><?= $sGlobalMessage ?></p>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Event Type Filter -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="EventTypeFilter"><?= gettext('Filter by Type'); ?></label>
                                <select id="EventTypeFilter" class="form-control" onchange="filterByType(this.value)">
                                    <option value="0"><?= gettext('All Event Types') ?></option>
                                    <?php foreach ($eventTypes as $type) { ?>
                                        <option value="<?= $type->getId() ?>" <?= ($eventTypeId == $type->getId()) ? "selected" : "" ?>>
                                            <?= InputUtils::escapeHTML($type->getName()) ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <!-- Event Selector -->
                        <div class="col-md-8">
                            <div class="form-group">
                                <label for="EventID"><?= gettext('Select Event'); ?></label>
                                <select id="EventID" name="EventID" class="form-control" onchange="this.form.submit()">
                                    <option value="" disabled <?= ($EventID == 0) ? "selected" : "" ?>><?= gettext('Select event') ?></option>
                                    <?php foreach ($activeEvents as $evt) { ?>
                                        <option value="<?= $evt->getId() ?>" <?= ($EventID == $evt->getId()) ? "selected" : "" ?>>
                                            <?= InputUtils::escapeHTML($evt->getTitle()) ?> (<?= $evt->getStart('M j, Y') ?>)
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 text-right">
                            <a class="btn btn-primary" href="EventEditor.php">
                                <i class="fa-solid fa-plus mr-1"></i><?= gettext('Add New Event'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form> <!-- end selectEvent form -->
<?php endif; ?>

<!-- Add Attendees Form -->
<?php
// If event is known, then show 2 text boxes, person being checked in and the person checking them in.
// Show a verify button and a button to add new visitor in dbase.
if (!$CheckoutOrDelete &&  $EventID > 0) {
?>

    <form class="well form-horizontal" method="post" action="Checkin.php" id="AddAttendees" data-toggle="validator"
        role="form">
        <input type="hidden" id="EventID" name="EventID" value="<?= $EventID; ?>">
        <input type="hidden" id="child-id" name="child-id">
        <input type="hidden" id="adult-id" name="adult-id">

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header bg-primary">
                        <h3 class="card-title mb-0"><?= gettext('Check In Person'); ?></h3>
                        <div class="event-meta mt-2">
                            <br/>
                            <div class="event-title text-white">
                                <strong><?=
                                    // Use non-breaking hyphen entities so long dashed dates/titles don't split awkwardly across lines
                                    str_replace('-', '&#8209;', $event->getTitle())
                                ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Two-column layout for check-in form -->
                        <div class="row">
                            <!-- Left Column: Person Being Checked In -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="child" class="control-label font-weight-bold">
                                        <i class="fa-solid fa-user text-primary mr-1"></i>
                                        <?= gettext("Person Checking In") ?> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control person-search" id="child"
                                        data-placeholder="<?= gettext("Search by name or email..."); ?>" required tabindex="1">
                                    </select>
                                    <div id="childDetails" class="mt-2"></div>
                                </div>
                            </div>

                            <!-- Right Column: Adult Supervisor (Optional) -->
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="adult" class="control-label font-weight-bold">
                                        <i class="fa-solid fa-user-shield text-secondary mr-1"></i>
                                        <?= gettext('Checked In By') ?> <span class="text-muted small">(<?= gettext('optional'); ?>)</span>
                                    </label>
                                    <select class="form-control person-search" id="adult"
                                        data-placeholder="<?= gettext("Search for supervisor..."); ?>" tabindex="2">
                                    </select>
                                    <div id="adultDetails" class="mt-2"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success btn-lg mr-2" name="CheckIn" tabindex="3">
                                    <i class="fa-solid fa-check mr-1"></i> <?= gettext('Check In'); ?>
                                </button>
                                <button type="reset" class="btn btn-outline-secondary" name="Cancel" tabindex="4">
                                    <?= gettext('Clear'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form> <!-- end AddAttendees form -->

    <?php
}

// Checkin/Checkout Section update db
if (isset($_POST['EventID']) && isset($_POST['child-id']) && (isset($_POST['CheckIn']) || isset($_POST['CheckOut']) || isset($_POST['Delete']))) {
    //Fields -> event_id, person_id, checkin_date, checkin_id, checkout_date, checkout_id
    if (isset($_POST['CheckIn']) && !empty($iChildID)) {
        $attendee = EventAttendQuery::create()->filterByEventId($EventID)->findOneByPersonId($iChildID);
        if ($attendee) {
    ?>
            <script nonce="<?= SystemURLs::getCSPNonce() ?>">
                $('#errorcallout').text('<?= gettext("Person has been already checked in for this event") ?>').fadeIn();
            </script>
    <?php
        } else {
            $attendee = new EventAttend();
            $attendee->setEventId($EventID);
            $attendee->setPersonId($iChildID);
            $attendee->setCheckinDate(date("Y-m-d H:i:s"));
            if (!empty($iAdultID)) {
                $attendee->setCheckinId($iAdultID);
            }
            $attendee->save();
        }
    }

    //Checkout Update
    if (isset($_POST['CheckOut'])) {
        $values = "checkout_date=NOW(), checkout_id=" . ($iAdultID ? "'" . $iAdultID . "'" : 'null');
        $attendee = EventAttendQuery::create()
            ->filterByEventId($EventID)
            ->findOneByPersonId($iChildID);
        $attendee->setCheckoutDate(date("Y-m-d H:i:s"));
        if ($iAdultID) {
            $attendee->setCheckoutId($iAdultID);
        }
        $attendee->save();
    }

    //delete
    if (isset($_POST['Delete'])) {
        EventAttendQuery::create()
            ->filterByEventId($EventID)
            ->findOneByPersonId($iChildID)
            ->delete();
    }
}

//-- End checkin

//  Checkout / Delete section
if (
    isset($_POST['EventID']) && isset($_POST['child-id']) &&
    (isset($_POST['CheckOutBtn']) || isset($_POST['DeleteBtn']))
) {
    $iChildID = InputUtils::legacyFilterInput($_POST['child-id'], 'int');

    $formTitle = (isset($_POST['CheckOutBtn']) ? gettext("CheckOut Person") : gettext("Delete Checkin in Entry")); ?>

    <form class="well form-horizontal" method="post" action="Checkin.php" id="CheckOut" data-toggle="validator"
        role="form">
        <input type="hidden" name="EventID" value="<?= $EventID ?>">
        <input type="hidden" name="child-id" value="<?= $iChildID ?>">

        <div class="row">
            <div class="col-12">
                <div class="card card-primary">
                    <div class="card-header with-border">
                        <h3 class="card-title"><?= $formTitle ?></h3>
                    </div>

                    <div class="card-body">
                        <div class="row align-items-center">
                            <div id="checkoutChildDetails" class="col-md-6 col-sm-12 text-center mb-3 mb-md-0">
                                <?php
                                loadperson($iChildID); ?>
                            </div>
                            <?php
                            if (isset($_POST['CheckOutBtn'])) {
                            ?>
                                <div class="col-md-6 col-sm-12">
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold"><?= gettext('Adult Checking Out Person') ?>:</label>
                                        <small class="form-text text-muted mb-2"><?= gettext('Optional - leave blank if not tracking') ?></small>
                                        <select class="form-control person-search" id="adultout" name="adult"
                                            data-placeholder="<?= gettext('Search for adult...') ?>">
                                        </select>
                                        <input type="hidden" id="adultout-id" name="adult-id">
                                    </div>
                                    <div class="form-group mb-0">
                                        <input type="submit" class="btn btn-success btn-lg mr-2"
                                            value="<?= gettext('✓ CheckOut') ?>" name="CheckOut">
                                        <input type="submit" class="btn btn-outline-secondary btn-lg" value="<?= gettext('Cancel') ?>"
                                            name="CheckoutCancel">
                                    </div>
                                </div>
                            <?php
                            } else { // DeleteBtn
                            ?>
                                <div class="col-md-6 col-sm-12">
                                    <div class="alert alert-warning mb-3">
                                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                        <?= gettext('Are you sure you want to delete this check-in record?') ?>
                                    </div>
                                    <div class="form-group mb-0">
                                        <input type="submit" class="btn btn-danger btn-lg mr-2"
                                            value="<?= gettext('Delete') ?>" name="Delete">
                                        <input type="submit" class="btn btn-outline-secondary btn-lg" value="<?= gettext('Cancel') ?>"
                                            name="DeleteCancel">
                                    </div>
                                </div>
                            <?php
                            } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
<?php
}

// Populate data table - show when event is selected (via POST or GET)
if ($EventID > 0) {
?>
    <div class="card card-primary">
        <div class="card-header bg-primary">
            <h3 class="card-title"><?= gettext('People Checked In'); ?></h3>
        </div>
        <div class="card-body table-responsive">
            <table id="checkedinTable" class="table data-table table-striped table-hover">
                <thead class="table-light">
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><i class="fa-solid fa-door-open me-1"></i><?= gettext('Checked In Time') ?></th>
                        <th><i class="fa-solid fa-user-check me-1"></i><?= gettext('Checked In By') ?></th>
                        <th><i class="fa-solid fa-door-closed me-1"></i><?= gettext('Checked Out Time') ?></th>
                        <th><i class="fa-solid fa-user-xmark me-1"></i><?= gettext('Checked Out By') ?></th>
                        <th class="text-nowrap"><?= gettext('Action') ?></th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    // Get Event Attendees details
                    $eventAttendees = EventAttendQuery::create()
                        ->filterByEventId($EventID)
                        ->find();

                    foreach ($eventAttendees as $per) {
                        //Get Person who is checked in
                        $checkedInPerson = PersonQuery::create()
                            ->findOneById($per->getPersonId());

                        $sPerson = $checkedInPerson->getFullName();

                        //Get Person who checked person in
                        $sCheckinby = "";
                        if ($per->getCheckinId()) {
                            $checkedInBy = PersonQuery::create()
                                ->findOneById($per->getCheckinId());
                            $sCheckinby = $checkedInBy->getFullName();
                        }

                        //Get Person who checked person out
                        $sCheckoutby = "";
                        if ($per->getCheckoutId()) {
                            $checkedOutBy = PersonQuery::create()
                                ->findOneById($per->getCheckoutId());
                            $sCheckoutby = $checkedOutBy->getFullName();
                        } ?>
                        <tr>
                            <td><img data-image-entity-type="person"
                                    data-image-entity-id="<?= $per->getPersonId() ?>"
                                    class="photo-tiny">&nbsp
                                <a href="PersonView.php?PersonID=<?= $per->getPersonId() ?>"><?= $sPerson ?></a>
                            </td>
                            <td><?= $per->getCheckinDate() ? date_format($per->getCheckinDate(), SystemConfig::getValue('sDateTimeFormat')) : '' ?></td>
                            <td><?= $sCheckinby ?></td>
                            <td><?= $per->getCheckoutDate() ? date_format($per->getCheckoutDate(), SystemConfig::getValue('sDateTimeFormat'))  : '' ?></td>
                            <td><?= $sCheckoutby ?></td>

                            <td class="text-center">
                                <form method="POST" action="Checkin.php" name="DeletePersonFromEvent">
                                    <input type="hidden" name="child-id" value="<?= $per->getPersonId() ?>">
                                    <input type="hidden" name="EventID" value="<?= $EventID ?>">
                                    <?php
                                    if (!$per->getCheckoutDate()) {
                                    ?>
                                        <input class="btn btn-primary btn-sm" type="submit" name="CheckOutBtn"
                                            value="<?= gettext('CheckOut') ?>">
                                        <input class="btn btn-danger btn-sm" type="submit" name="DeleteBtn"
                                            value="<?= gettext('Delete') ?>">

                                    <?php
                                    } else {
                                    ?>
                                        <i class="fa-solid fa-check-circle"></i>
                                    <?php
                                    } ?>
                                </form>
                            </td>
                        </tr>
                    <?php
                    } ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
}
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
// Filter events by type - reload page with type filter
function filterByType(typeId) {
    window.location.href = 'Checkin.php?EventTypeID=' + typeId;
}
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::getRootPath() ?>/skin/js/checkin.js"></script>

<?php require_once 'Include/Footer.php';

function loadPerson($iPersonID)
{
    if ($iPersonID == 0) {
        echo "";
    }
    $person = PersonQuery::create()
        ->findOneById($iPersonID);
    $familyRole = "(";
    if ($person->getFamId()) {
        if ($person->getFamilyRole()) {
            $familyRole .= $person->getFamilyRoleName();
        } else {
            $familyRole .=  gettext('Member');
        }
        $familyRole .= gettext(' of the') . ' <a href="v2/family/' . $person->getFamId() . '">' . $person->getFamily()->getName() . '</a> ' . gettext('family') . ' )';
    } else {
        $familyRole = gettext('(No assigned family)');
    }

    $html = '<div class="text-center">' .
        '<a target="_top" href="PersonView.php?PersonID=' . $iPersonID . '"><h4>' . $person->getTitle() . ' ' . $person->getFullName() . '</h4></a>' .
        '<div class="">' . $familyRole . '</div>' .
        '<div class="text-center">' . $person->getAddress() . '</div>' .
        '<img src="' . SystemURLs::getRootPath() . '/api/person/' . $iPersonID . '/photo" class="photo-medium"> </div>';
    echo $html;
}
?>