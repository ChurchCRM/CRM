<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<?php if ($cartCount > 0) { ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Cart Contents -->
            <div class="card mb-4">
                <div class="card-status-top bg-primary"></div>
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('People in Cart') ?></h3>
                    <span class="badge bg-primary text-white ms-auto"><?= $cartCount ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Classification') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aPeopleInCart as $person) { ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="avatar avatar-sm rounded-circle me-2" alt="" />
                                            <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $person->getId() ?>">
                                                <?= $person->getFullName() ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?= $person->getClsid() ? InputUtils::escapeHTML($person->getClassification()->getOptionName()) : '<em class="text-muted">' . gettext('Unclassified') . '</em>' ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add to Event Form -->
            <div class="card">
                <div class="card-status-top bg-green"></div>
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Check In to Event') ?></h3>
                </div>
                <div class="card-body">
                    <!-- Event Type Filter -->
                    <form id="eventTypeFilterForm" method="GET" action="<?= $sRootPath ?>/event/cart-to-event" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label" for="EventTypeFilter"><?= gettext('Filter by Event Type') ?></label>
                            <select id="EventTypeFilter" name="EventTypeFilter" class="form-select">
                                <option value="0" <?= ($selectedEventType === 0) ? 'selected' : '' ?>><?= gettext('All Event Types') ?></option>
                                <?php foreach ($aEventTypes as $eventType) { ?>
                                    <option value="<?= $eventType->getId() ?>" <?= ($selectedEventType === $eventType->getId()) ? 'selected' : '' ?>>
                                        <?= $eventType->getName() ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </form>

                    <hr class="my-3">

                    <!-- Event Selection Form -->
                    <form name="CartToEvent" action="<?= $sRootPath ?>/event/cart-to-event" method="POST">
                        <div class="mb-3">
                            <label class="form-label" for="EventID"><?= gettext('Select Event') ?></label>
                            <select id="EventID" name="EventID" class="form-select" required>
                                <option value="" disabled selected><?= gettext('Choose an event...') ?></option>
                                <?php foreach ($aEvents as $evt) { ?>
                                    <option value="<?= $evt->getId() ?>"><?= $evt->getTitle() ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="card-footer text-end">
                            <a href="<?= $sRootPath ?>/EventEditor.php" class="btn btn-outline-secondary me-2"><?= gettext('Create New Event') ?></a>
                            <button type="submit" name="Submit" class="btn btn-primary"><?= gettext('Check In to Event') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="ti ti-shopping-cart-off" style="font-size: 3rem; color: var(--tblr-muted);"></i>
                    </div>
                    <h3 class="text-muted"><?= gettext('Your cart is empty!') ?></h3>
                    <p class="text-secondary"><?= gettext('Add people to your cart first, then come back to check them in to an event.') ?></p>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    document.getElementById('EventTypeFilter')?.addEventListener('change', function () {
        document.getElementById('eventTypeFilterForm').submit();
    });
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
