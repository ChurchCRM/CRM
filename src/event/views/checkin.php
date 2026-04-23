<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="errorcallout" class="alert alert-danger" hidden></div>

<?php if ($addedCount > 0): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="ti ti-circle-check me-2"></i>
    <strong><?= $addedCount ?></strong> <?= ngettext('person', 'people', $addedCount) ?> <?= gettext('added to this event') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if ($directEventAccess && $event !== null): ?>
<!-- Direct Event Access - Show event info bar with option to view, edit, or change -->
<div class="card card-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>
                <i class="ti ti-calendar-check me-2 text-primary"></i>
                <strong><?= gettext('Event') ?>:</strong> <?= InputUtils::escapeHTML($event->getTitle()) ?>
                <span class="text-secondary">(<?= $event->getStart('M j, Y') ?>)</span>
            </span>
            <div class="btn-group">
                <a href="<?= $sRootPath ?>/event/view/<?= $eventId ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-eye me-1"></i><?= gettext('View Event') ?>
                </a>
                <a href="<?= $sRootPath ?>/event/editor/<?= $eventId ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-pencil me-1"></i><?= gettext('Edit Event') ?>
                </a>
                <a href="<?= $sRootPath ?>/event/checkin" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-switch-horizontal me-1"></i><?= gettext('Change Event') ?>
                </a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Select Event Form -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Select Event for Check-In') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- Event Type Filter -->
            <div class="col-md-4">
                <div class="mb-3">
                    <label for="typeFilter" class="form-label"><?= gettext('Filter by Type') ?></label>
                    <select id="typeFilter" class="form-select">
                        <option value="0"><?= gettext('All Event Types') ?></option>
                        <?php foreach ($eventTypes as $type): ?>
                            <option value="<?= $type->getId() ?>" <?= ($eventTypeId == $type->getId()) ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($type->getName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <!-- Event Selector -->
            <div class="col-md-8">
                <div class="mb-3">
                    <label for="EventSelector" class="form-label"><?= gettext('Select Event') ?></label>
                    <select id="EventSelector" class="form-select">
                        <option value="" disabled <?= ($eventId === 0) ? 'selected' : '' ?>><?= gettext('Select event') ?></option>
                        <?php foreach ($activeEvents as $evt): ?>
                            <option value="<?= $evt->getId() ?>" <?= ($eventId === $evt->getId()) ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($evt->getTitle()) ?> (<?= $evt->getStart('M j, Y') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer text-end">
        <a class="btn btn-primary" href="<?= $sRootPath ?>/event/editor">
            <i class="ti ti-plus me-1"></i><?= gettext('Add New') . ' ' . gettext('Event') ?>
        </a>
    </div>
</div>
<?php endif; ?>

<?php if ($eventId > 0): ?>
<!-- Roster-Based Check-in (for group-linked events) -->
<div id="rosterCheckin" class="d-none" data-event-id="<?= $eventId ?>">
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

<?php if (!empty($eventInactive)): ?>
<!-- Inactive event banner — block check-in entirely -->
<div class="alert alert-warning mb-3">
    <div class="d-flex align-items-center">
        <i class="ti ti-alert-triangle me-2 fs-3"></i>
        <div class="flex-grow-1">
            <strong><?= gettext('This event is inactive.') ?></strong>
            <div class="small text-muted">
                <?= gettext('Check-in is disabled for inactive events. Activate the event from the Events Dashboard to enable check-in.') ?>
            </div>
        </div>
        <a href="<?= $sRootPath ?>/event/dashboard" class="btn btn-outline-secondary ms-3">
            <?= gettext('Back to Events Dashboard') ?>
        </a>
    </div>
</div>
<?php else: ?>
<!-- Walk-in / Visitor Check-In (API-driven, no page reload) -->
<div class="card" id="walkinCheckinCard">
    <div class="card-status-top bg-primary"></div>
    <div class="card-header">
        <h3 class="card-title" id="walkinCardTitle"><?= gettext('Check In Person') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="child" class="form-label required"><?= gettext('Person Checking In') ?></label>
                    <select class="form-select person-search" id="child"
                        data-placeholder="<?= gettext('Search by name or email...') ?>" required tabindex="1">
                    </select>
                    <div id="childDetails" class="mt-2"></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="adult" class="form-label">
                        <?= gettext('Checked In By') ?> <span class="text-secondary small">(<?= gettext('optional') ?>)</span>
                    </label>
                    <div class="input-group">
                        <select class="form-select person-search" id="adult"
                            data-placeholder="<?= gettext('Search for supervisor...') ?>" tabindex="2">
                        </select>
                        <button type="button" class="btn btn-outline-secondary assign-me-btn" id="assignMeCheckin"
                            title="<?= gettext('Assign to me') ?>">
                            <i class="ti ti-user-check"></i>
                        </button>
                    </div>
                    <div id="adultDetails" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" id="checkinBtn" tabindex="3">
                <i class="ti ti-check me-1"></i><?= gettext('Check In') ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="clearBtn" tabindex="4">
                <?= gettext('Clear') ?>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Attendance Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('People Checked In') ?></h3>
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
                <?php foreach ($attendees as $att): ?>
                <tr data-person-id="<?= $att['personId'] ?>">
                    <td>
                        <div class="d-flex align-items-center">
                            <img data-image-entity-type="person" data-image-entity-id="<?= $att['personId'] ?>"
                                 class="avatar avatar-sm rounded-circle me-2" alt="" />
                            <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $att['personId'] ?>"><?= InputUtils::escapeHTML($att['fullName']) ?></a>
                        </div>
                    </td>
                    <td><?= $att['checkinDate'] ? InputUtils::escapeHTML($att['checkinDate']) : '' ?></td>
                    <td><?= InputUtils::escapeHTML($att['checkinBy']) ?></td>
                    <td class="checkout-date"><?= $att['checkoutDate'] ? InputUtils::escapeHTML($att['checkoutDate']) : '' ?></td>
                    <td class="checkout-by"><?= InputUtils::escapeHTML($att['checkoutBy']) ?></td>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button"
                                    data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <i class="ti ti-dots-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $att['personId'] ?>">
                                    <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                </a>
                                <?php if ($att['familyId']): ?>
                                <a class="dropdown-item" href="<?= $sRootPath ?>/people/family/<?= $att['familyId'] ?>">
                                    <i class="ti ti-users me-2"></i><?= gettext('View Family') ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!$att['isCheckedOut']): ?>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item checkout-btn"
                                        data-person-id="<?= $att['personId'] ?>"
                                        data-person-name="<?= InputUtils::escapeAttribute($att['fullName']) ?>"
                                        <?php if ($att['checkinId']): ?>data-checkin-id="<?= $att['checkinId'] ?>"<?php endif; ?>
                                        <?php if ($att['checkinBy']): ?>data-checkin-name="<?= InputUtils::escapeAttribute($att['checkinBy']) ?>"<?php endif; ?>>
                                    <i class="ti ti-door-exit me-2"></i><?= gettext('Check Out') ?>
                                </button>
                                <?php else: ?>
                                <div class="dropdown-divider"></div>
                                <span class="dropdown-item disabled text-success">
                                    <i class="ti ti-check me-2"></i><?= gettext('Checked Out') ?>
                                </span>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-danger delete-attendance-btn"
                                        data-person-id="<?= $att['personId'] ?>"
                                        data-person-name="<?= InputUtils::escapeAttribute($att['fullName']) ?>">
                                    <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
window.CRM = window.CRM || {};
window.CRM.checkinEventId = <?= $eventId ?>;
window.CRM.checkinRootPath = <?= json_encode($sRootPath) ?>;
</script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/event-checkin.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
