<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventAttend;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: User must have Manage Groups & Roles permission
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isManageGroupsEnabled(), 'ManageGroups');

// Was the form submitted?
if (isset($_POST['Submit']) && count($_SESSION['aPeopleCart']) > 0 && isset($_POST['EventID'])) {
    $iEventID = InputUtils::legacyFilterInput($_POST['EventID'], 'int');

    $iCount = 0;
    foreach ($_SESSION['aPeopleCart'] as $element) {
        try {
            $eventAttend = new EventAttend();
            $eventAttend
                ->setEventId($iEventID)
                ->setPersonId($element);
            $eventAttend->save();
            $iCount++;
        } catch (\Throwable $ex) {
            $logger = LoggerUtils::getAppLogger();
            $logger->error('An error occurred when saving event attendance', ['exception' => $ex]);
        }
    }
    Cart::emptyAll();

    // Redirect to checkin page with count notification
    RedirectUtils::redirect('Checkin.php?EventID=' . $iEventID . '&AddedCount=' . $iCount);
}

$sPageTitle = gettext('Add Cart to Event');
require_once 'Include/Header.php';

if (count($_SESSION['aPeopleCart']) > 0) {
    // Get filter parameter
    $selectedEventType = isset($_POST['EventTypeFilter']) ? (int)InputUtils::legacyFilterInput($_POST['EventTypeFilter'], 'int') : 0;
    
    // Build event query with optional type filter
    $eventQuery = EventQuery::create();
    if ($selectedEventType > 0) {
        $eventType = EventTypeQuery::create()->findOneById($selectedEventType);
        if ($eventType) {
            $eventQuery->filterByEventType($eventType);
        }
    }
    $aEvents = $eventQuery->find();
    
    // Get all event types for filter dropdown
    $aEventTypes = EventTypeQuery::create()->find();
    
    $aPeopleInCart = PersonQuery::create()
        ->filterById($_SESSION['aPeopleCart'])
        ->find();
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <!-- Cart Contents -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h3 class="card-title"><?= gettext('People in Cart') ?></h3>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th><?= gettext('Name') ?></th>
                                    <th><?= gettext('Classification') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aPeopleInCart as $person) { ?>
                                    <tr>
                                        <td>
                                            <img data-image-entity-type="person"
                                                 data-image-entity-id="<?= $person->getId() ?>"
                                                 class="photo-tiny">&nbsp
                                            <a href="PersonView.php?PersonID=<?= $person->getId() ?>">
                                                <?= $person->getFullName() ?>
                                            </a>
                                        </td>
                                        <td><?= $person->getClsid() ? $person->getClassification()->getOptionName() : '<em class="text-muted">' . gettext('Unclassified') . '</em>' ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <div class="alert alert-info mt-3 mb-0">
                            <strong><?= count($aPeopleInCart) ?></strong> <?= ngettext('person', 'people', count($aPeopleInCart)) ?> <?= gettext('in cart') ?>
                        </div>
                    </div>
                </div>

                <!-- Add to Event Form -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h3 class="card-title"><?= gettext('Check In to Event') ?></h3>
                    </div>
                    <div class="card-body">
                        <p class="text-center text-muted mb-4"><?= gettext('Select the event to check in your cart members') ?>:</p>
                        
                        <!-- Event Type Filter -->
                        <form method="POST" action="CartToEvent.php" class="mb-4">
                            <div class="form-group">
                                <label for="EventTypeFilter"><?= gettext('Filter by Event Type') ?></label>
                                <div class="input-group">
                                    <select id="EventTypeFilter" name="EventTypeFilter" class="form-control" onchange="this.form.submit()">
                                        <option value="0" <?= ($selectedEventType == 0) ? 'selected' : '' ?>><?= gettext('All Event Types') ?></option>
                                        <?php foreach ($aEventTypes as $eventType) { ?>
                                            <option value="<?= $eventType->getId() ?>" <?= ($selectedEventType == $eventType->getId()) ? 'selected' : '' ?>>
                                                <?= $eventType->getName() ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Event Selection Form -->
                        <form name="CartToEvent" action="CartToEvent.php" method="POST">
                            <div class="form-group">
                                <label for="EventID"><?= gettext('Select Event') ?></label>
                                <select id="EventID" name="EventID" class="form-control" required>
                                    <option value="" disabled selected><?= gettext('Choose an event...') ?></option>
                                    <?php foreach ($aEvents as $evt) { ?>
                                        <option value="<?= $evt->getId() ?>"><?= $evt->getTitle() ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="text-center">
                                <button type="submit" name="Submit" class="btn btn-primary btn-lg"><?= gettext('Check In to Event') ?></button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-light text-center">
                        <small class="text-muted"><?= gettext('OR') ?></small>
                        <div class="mt-3">
                            <a href="EventEditor.php" class="btn btn-outline-info"><?= gettext('Create New Event') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
} else {
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="alert alert-warning text-center">
                    <i class="fa fa-info-circle"></i> <?= gettext('Your cart is empty!') ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

require_once 'Include/Footer.php';
