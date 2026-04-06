<?php

$sPageTitle = gettext('Event Checkin');
$sPageSubtitle = gettext('Check in attendees for church events and activities');

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\view\PageHeader;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Events'), '/ListEvents.php'],
    [gettext('Check-in')],
]);
require_once __DIR__ . '/Include/Header.php';

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

// Default to current user if no adult ID provided
if (empty($iAdultID) && (isset($_POST['CheckIn']) || isset($_POST['CheckOut']))) {
    $currentUser = AuthenticationManager::getCurrentUser();
    if ($currentUser) {
        $iAdultID = $currentUser->getPersonId();
    }
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
    <i class="ti ti-circle-check me-2"></i>
    <strong><?= $iAddedCount ?></strong> <?= ngettext('person', 'people', $iAddedCount) ?> <?= gettext('added to this event') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($directEventAccess && $event !== null): ?>
<!-- Direct Event Access - Show event info bar with option to change -->
<div class="card card-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <span>
                <i class="ti ti-calendar-check me-2 text-primary"></i>
                <strong><?= gettext('Event') ?>:</strong> <?= InputUtils::escapeHTML($event->getTitle()) ?>
                <span class="text-secondary">(<?= $event->getStart('M j, Y') ?>)</span>
            </span>
            <div>
                <a href="EventEditor.php?EID=<?= $EventID ?>" class="btn btn-sm btn-outline-primary me-2">
                    <i class="ti ti-pencil me-1"></i><?= gettext('Edit Event') ?>
                </a>
                <a href="Checkin.php" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-switch-horizontal me-1"></i><?= gettext('Change Event') ?>
                </a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!--Select Event Form -->
<form name="selectEvent" action="Checkin.php" method="POST">
    <input type="hidden" name="EventTypeID" id="EventTypeIDHidden" value="<?= $eventTypeId ?>">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('Select Event for Check-In') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Event Type Filter -->
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="EventTypeFilter" class="form-label"><?= gettext('Filter by Type'); ?></label>
                        <select id="EventTypeFilter" class="form-select" onchange="filterByType(this.value)">
                            <option value="0"><?= gettext('All Event Types') ?></option>
                            <?php foreach ($eventTypes as $type) { ?>
                                <option value="<?= $type->getId() ?>" <?= ($eventTypeId == $type->getId()) ?"selected" :"" ?>>
                                    <?= InputUtils::escapeHTML($type->getName()) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <!-- Event Selector -->
                <div class="col-md-8">
                    <div class="mb-3">
                        <label for="EventID" class="form-label"><?= gettext('Select Event'); ?></label>
                        <select id="EventID" name="EventID" class="form-select" onchange="this.form.submit()">
                            <option value="" disabled <?= ($EventID === 0) ?"selected" :"" ?>><?= gettext('Select event') ?></option>
                            <?php foreach ($activeEvents as $evt) { ?>
                                <option value="<?= $evt->getId() ?>" <?= ($EventID === $evt->getId()) ?"selected" :"" ?>>
                                    <?= InputUtils::escapeHTML($evt->getTitle()) ?> (<?= $evt->getStart('M j, Y') ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-end">
            <a class="btn btn-primary" href="EventEditor.php">
                <i class="ti ti-plus me-1"></i><?= gettext('Add New') . ' ' . gettext('Event'); ?>
            </a>
        </div>
    </div>
</form> <!-- end selectEvent form -->
<?php endif; ?>

<!-- Roster-Based Check-in (for group-linked events) -->
<?php if (!$CheckoutOrDelete && $EventID > 0): ?>
<div id="rosterCheckin" class="d-none" data-event-id="<?= $EventID ?>">
    <div class="card">
        <div class="card-status-top bg-primary"></div>
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center w-100">
                <h3 class="card-title mb-0">
                    <i class="ti ti-users me-2"></i><?= gettext('Group Roster') ?>
                    <span id="rosterGroupName" class="text-secondary"></span>
                </h3>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary" id="rosterStats"></span>
                    <button type="button" class="btn btn-sm btn-success" id="checkinAllBtn">
                        <i class="ti ti-checks me-1"></i><?= gettext('Check In All') ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="checkoutAllBtn">
                        <i class="ti ti-door-exit me-1"></i><?= gettext('Check Out All') ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="rosterLoading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
            <div id="rosterGrid" class="row g-0 p-3 d-none">
                <!-- Left: Not Checked In -->
                <div class="col-md-6 pe-md-2">
                    <h4 class="text-secondary mb-2">
                        <i class="ti ti-clock me-1"></i><?= gettext('Waiting to Check In') ?>
                        <span class="badge bg-secondary ms-1" id="notCheckedInCount">0</span>
                    </h4>
                    <div id="notCheckedInList" class="d-flex flex-column gap-1"></div>
                    <div id="notCheckedInEmpty" class="text-center text-success py-3 d-none">
                        <i class="ti ti-circle-check me-1"></i><?= gettext('Everyone is checked in!') ?>
                    </div>
                </div>
                <!-- Right: Checked In -->
                <div class="col-md-6 ps-md-2 mt-3 mt-md-0">
                    <h4 class="text-secondary mb-2">
                        <i class="ti ti-circle-check me-1"></i><?= gettext('Checked In') ?>
                        <span class="badge bg-success ms-1" id="checkedInCount">0</span>
                    </h4>
                    <div id="checkedInList" class="d-flex flex-column gap-1"></div>
                    <div id="checkedInEmpty" class="text-center text-secondary py-3 d-none">
                        <i class="ti ti-mood-sad me-1"></i><?= gettext('No one checked in yet') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Attendees Form (walk-in search, also shown when no group roster) -->
<?php
// If event is known, then show 2 text boxes, person being checked in and the person checking them in.
// Show a verify button and a button to add new visitor in dbase.
if (!$CheckoutOrDelete &&  $EventID > 0) {
?>

    <form method="post" action="Checkin.php" id="AddAttendees" role="form">
        <input type="hidden" id="EventID" name="EventID" value="<?= $EventID; ?>">
        <input type="hidden" id="child-id" name="child-id">
        <input type="hidden" id="adult-id" name="adult-id">

        <div class="card" id="walkinCheckinCard">
            <div class="card-status-top bg-primary"></div>
            <div class="card-header">
                <h3 class="card-title" id="walkinCardTitle"><?= gettext('Check In Person'); ?></h3>
            </div>
            <div class="card-body">
                <!-- Two-column layout for check-in form -->
                <div class="row">
                    <!-- Left Column: Person Being Checked In -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="child" class="form-label required">
                                <?= gettext("Person Checking In") ?>
                            </label>
                            <select class="form-select person-search" id="child"
                                data-placeholder="<?= gettext("Search by name or email..."); ?>" required tabindex="1">
                            </select>
                            <div id="childDetails" class="mt-2"></div>
                        </div>
                    </div>

                    <!-- Right Column: Adult Supervisor (Optional) -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="adult" class="form-label">
                                <?= gettext('Checked In By') ?> <span class="text-secondary small">(<?= gettext('optional'); ?>)</span>
                            </label>
                            <select class="form-select person-search" id="adult"
                                data-placeholder="<?= gettext("Search for supervisor..."); ?>" tabindex="2">
                            </select>
                            <div id="adultDetails" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success" name="CheckIn" tabindex="3">
                        <i class="ti ti-check me-1"></i><?= gettext('Check In'); ?>
                    </button>
                    <button type="reset" class="btn btn-outline-secondary" name="Cancel" tabindex="4">
                        <?= gettext('Clear'); ?>
                    </button>
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
            $attendee->setCheckinDate(DateTimeUtils::getNowDateTime());
            if (!empty($iAdultID)) {
                $attendee->setCheckinId($iAdultID);
            }
            $attendee->save();
        }
    }

    //Checkout Update
    if (isset($_POST['CheckOut'])) {
        $values ="checkout_date=NOW(), checkout_id=" . ($iAdultID ?"'" . $iAdultID ."'" : 'null');
        $attendee = EventAttendQuery::create()
            ->filterByEventId($EventID)
            ->findOneByPersonId($iChildID);
        $attendee->setCheckoutDate(DateTimeUtils::getNowDateTime());
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

    $formTitle = (isset($_POST['CheckOutBtn']) ? gettext("Check Out Person") : gettext("Delete Check-in Entry")); ?>

    <form method="post" action="Checkin.php" id="CheckOut" role="form">
        <input type="hidden" name="EventID" value="<?= $EventID ?>">
        <input type="hidden" name="child-id" value="<?= $iChildID ?>">

        <div class="card">
            <?php if (isset($_POST['DeleteBtn'])): ?>
            <div class="card-status-top bg-danger"></div>
            <?php else: ?>
            <div class="card-status-top bg-success"></div>
            <?php endif; ?>
            <div class="card-header">
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
                            <div class="mb-3">
                                <label class="form-label"><?= gettext('Adult Checking Out Person') ?></label>
                                <small class="form-text text-secondary d-block mb-2"><?= gettext('Optional - leave blank if not tracking') ?></small>
                                <select class="form-select person-search" id="adultout" name="adult"
                                    data-placeholder="<?= gettext('Search for adult...') ?>">
                                </select>
                                <input type="hidden" id="adultout-id" name="adult-id">
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success" name="CheckOut">
                                    <i class="ti ti-check me-1"></i><?= gettext('Check Out') ?>
                                </button>
                                <button type="submit" class="btn btn-outline-secondary" name="CheckoutCancel">
                                    <?= gettext('Cancel') ?>
                                </button>
                            </div>
                        </div>
                    <?php
                    } else { // DeleteBtn
                    ?>
                        <div class="col-md-6 col-sm-12">
                            <div class="alert alert-warning text-dark mb-3">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <?= gettext('Are you sure you want to delete this check-in record?') ?>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-danger" name="Delete">
                                    <i class="ti ti-trash me-1"></i><?= gettext('Delete') ?>
                                </button>
                                <button type="submit" class="btn btn-outline-secondary" name="DeleteCancel">
                                    <?= gettext('Cancel') ?>
                                </button>
                            </div>
                        </div>
                    <?php
                    } ?>
                </div>
            </div>
        </div>
    </form>
<?php
}

// Populate data table - show when event is selected (via POST or GET)
if ($EventID > 0) {
?>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= gettext('People Checked In'); ?></h3>
        </div>
        <div class="card-body" style="overflow: visible;">
            <table id="checkedinTable" class="table table-vcenter table-hover data-table">
                <thead>
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('Checked In Time') ?></th>
                        <th><?= gettext('Checked In By') ?></th>
                        <th><?= gettext('Checked Out Time') ?></th>
                        <th><?= gettext('Checked Out By') ?></th>
                        <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
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
                        $sCheckinby ="";
                        if ($per->getCheckinId()) {
                            $checkedInBy = PersonQuery::create()
                                ->findOneById($per->getCheckinId());
                            $sCheckinby = $checkedInBy->getFullName();
                        }

                        //Get Person who checked person out
                        $sCheckoutby ="";
                        if ($per->getCheckoutId()) {
                            $checkedOutBy = PersonQuery::create()
                                ->findOneById($per->getCheckoutId());
                            $sCheckoutby = $checkedOutBy->getFullName();
                        }

                        // Check-out status badge
                        $isCheckedOut = $per->getCheckoutDate() !== null;
                    ?>
                        <tr>
                            <td>
                                <?php
                                $personPhoto = new \ChurchCRM\dto\Photo('person', $per->getPersonId());
                                if ($personPhoto->hasUploadedPhoto()) {
                                ?>
                                    <button class="btn btn-sm btn-ghost-secondary view-person-photo me-1" data-person-id="<?= $per->getPersonId() ?>" title="<?= gettext('View Photo') ?>">
                                        <i class="ti ti-camera"></i>
                                    </button>
                                <?php } ?>
                                <a href="PersonView.php?PersonID=<?= $per->getPersonId() ?>"><?= $sPerson ?></a>
                            </td>
                            <td><?= $per->getCheckinDate() ? InputUtils::escapeHTML(date_format($per->getCheckinDate(), SystemConfig::getValue('sDateTimeFormat'))) : '' ?></td>
                            <td><?= $sCheckinby ?></td>
                            <td>
                                <?php if ($isCheckedOut): ?>
                                    <?= InputUtils::escapeHTML(date_format($per->getCheckoutDate(), SystemConfig::getValue('sDateTimeFormat'))) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $sCheckoutby ?></td>

                            <td class="w-1">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary" type="button"
                                            data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="PersonView.php?PersonID=<?= $per->getPersonId() ?>">
                                            <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                        </a>
                                        <a class="dropdown-item" href="PersonEditor.php?PersonID=<?= $per->getPersonId() ?>">
                                            <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                        </a>
                                        <?php if ($checkedInPerson->getFamId()): ?>
                                        <a class="dropdown-item" href="v2/family/<?= $checkedInPerson->getFamId() ?>">
                                            <i class="ti ti-users me-2"></i><?= gettext('View Family') ?>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!$isCheckedOut): ?>
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="Checkin.php" class="d-inline">
                                            <input type="hidden" name="child-id" value="<?= $per->getPersonId() ?>">
                                            <input type="hidden" name="EventID" value="<?= $EventID ?>">
                                            <button type="submit" name="CheckOutBtn" class="dropdown-item">
                                                <i class="ti ti-door-exit me-2"></i><?= gettext('Check Out') ?>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <div class="dropdown-divider"></div>
                                        <span class="dropdown-item disabled text-success">
                                            <i class="ti ti-check me-2"></i><?= gettext('Checked Out') ?>
                                        </span>
                                        <?php endif; ?>
                                        <div class="dropdown-divider"></div>
                                        <?php
                                        $inCart = isset($_SESSION['aPeopleCart']) && in_array($per->getPersonId(), $_SESSION['aPeopleCart'], false);
                                        ?>
                                        <button type="button"
                                            class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                            data-cart-id="<?= $per->getPersonId() ?>"
                                            data-cart-type="person"
                                            data-label-add="<?= gettext('Add to Cart') ?>"
                                            data-label-remove="<?= gettext('Remove from Cart') ?>">
                                            <i class="<?= $inCart ? 'ti ti-trash' : 'ti ti-shopping-cart-plus' ?> me-2"></i>
                                            <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <form method="POST" action="Checkin.php" class="d-inline">
                                            <input type="hidden" name="child-id" value="<?= $per->getPersonId() ?>">
                                            <input type="hidden" name="EventID" value="<?= $EventID ?>">
                                            <button type="submit" name="DeleteBtn" class="dropdown-item text-danger">
                                                <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
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
<script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/js/checkin.js') ?>"></script>

<?php require_once __DIR__ . '/Include/Footer.php';

function loadPerson($iPersonID)
{
    if ($iPersonID === 0) {
        echo"";
    }
    $person = PersonQuery::create()
        ->findOneById($iPersonID);
    $familyRole ="(";
    if ($person->getFamId()) {
        if ($person->getFamilyRole()) {
            $familyRole .= $person->getFamilyRoleName();
        } else {
            $familyRole .=  gettext('Member');
        }
        $familyRole .= gettext(' of the') . ' <a href="v2/family/' . $person->getFamId() . '">' . InputUtils::escapeHTML($person->getFamily()->getName()) . '</a> ' . gettext('family') . ' )';
    } else {
        $familyRole = gettext('(No assigned family)');
    }

    $personPhoto = new \ChurchCRM\dto\Photo('person', $iPersonID);
    $html = '<div class="d-flex align-items-center">';
    if ($personPhoto->hasUploadedPhoto()) {
        $html .= '<span class="avatar avatar-md me-3" style="background-image: url(' . SystemURLs::getRootPath() . '/api/person/' . $iPersonID . '/photo)"></span>';
    } else {
        $html .= '<span class="avatar avatar-md me-3 bg-primary-lt"><i class="ti ti-user"></i></span>';
    }
    $html .= '<div>';
    $html .= '<a href="PersonView.php?PersonID=' . $iPersonID . '" class="fw-bold">' . InputUtils::escapeHTML($person->getTitle() . ' ' . $person->getFullName()) . '</a>';
    $html .= '<div class="text-secondary small">' . $familyRole . '</div>';
    $html .= '</div>';
    $html .= '</div>';
    echo $html;
}
?>