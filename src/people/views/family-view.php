<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\Service\PropertyService;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\ActiveQuery\Criteria;

$sPageTitle = InputUtils::escapeHTML($family->getName());
$sPageSubtitle = gettext('Family Profile') . ' — ID: ' . $family->getId();
require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$familyAddress = $family->getAddress();

$memberCount = count($family->getPeople());

// Get unique family emails for the verification modal
$familyEmails = $family->getEmails();

// Build active events list for the family check-in modal (#6838)
$showFamilyCheckin = AuthenticationManager::getCurrentUser()->canManageEvents();
$activeEventsForCheckin = [];
$familyPersonIds = [];
if ($showFamilyCheckin) {
    foreach ($family->getPeople() as $member) {
        $familyPersonIds[] = (int) $member->getId();
    }
    $activeEvents = EventQuery::create()
        ->filterByInActive(1, Criteria::NOT_EQUAL)
        ->filterByStart(['min' => date('Y-m-d 00:00:00', strtotime('-1 day'))])
        ->orderByStart()
        ->limit(50)
        ->find();
    foreach ($activeEvents as $evt) {
        $activeEventsForCheckin[] = [
            'id' => (int) $evt->getId(),
            'title' => $evt->getTitle(),
            'date' => $evt->getStart('M j, Y g:i A'),
        ];
    }
}

// Store family email for JavaScript (used by MailChimp plugin if active)
$familyEmailMD5 = $family->getEmail() ? md5(strtolower($family->getEmail())) : '';

// Group family members by role
$headPeople = $family->getHeadPeople();
$spousePeople = $family->getSpousePeople();
$keyPeople = array_merge($headPeople, $spousePeople);
$childPeople = $family->getChildPeople();
$otherPeople = $family->getOtherPeople();

$assignedFamilyProperties = PropertyService::getAssigned($family);
$allFamilyProperties = PropertyService::getAll($family);

$canEditRecords = AuthenticationManager::getCurrentUser()->isEditRecordsEnabled();
?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentFamily = <?= $family->getId() ?>;
    window.CRM.currentFamilyName = <?= json_encode($family->getName(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    window.CRM.currentActive = <?= $family->isActive() ?"true" :"false" ?>;
    window.CRM.currentFamilyView = 2;
    window.CRM.familyEmail ="<?= InputUtils::escapeAttribute($family->getEmail() ?? '') ?>";
    window.CRM.familyEmailMD5 ="<?= $familyEmailMD5 ?>";
    <?php if ($showFamilyCheckin): ?>
    window.CRM.familyCheckin = {
        familyPersonIds: <?= json_encode($familyPersonIds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        activeEvents: <?= json_encode($activeEventsForCheckin, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    };
    <?php endif; ?>
</script>

<div id="family-deactivated" class="alert alert-warning d-none">
    <strong><?= gettext("This Family is Inactive") ?> </strong>
</div>

<div class="row">
    <!-- LEFT COLUMN: Actions, Members, Timeline -->
    <div class="col-12 col-lg-8">
        <!-- Family Action Toolbar -->
        <div class="d-flex align-items-center mb-3 gap-2 flex-wrap d-print-none">
            <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
            <a class="btn btn-ghost-primary" href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>">
                <i class="fa-solid fa-pen me-1"></i><?= gettext('Edit') ?>
            </a>
            <?php } ?>
            <button class="btn btn-ghost-secondary" id="printFamily" title="<?= gettext('Print') ?>">
                <i class="fa-solid fa-print me-1"></i><?= gettext('Print') ?>
            </button>
            <button class="btn btn-ghost-success AddToCart" id="AddFamilyToCart" data-cart-id="<?= $family->getId() ?>" data-cart-type="family">
                <i class="fa-solid fa-cart-plus me-1"></i><span class="cartActionDescription"><?= gettext('Cart') ?></span>
            </button>
            <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled()) { ?>
            <a class="btn btn-ghost-info" href="<?= SystemURLs::getRootPath() ?>/NoteEditor.php?FamilyID=<?= $family->getId() ?>">
                <i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Add Note') ?>
            </a>
            <?php } ?>
            <?php if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) { ?>
            <div class="dropdown">
                <button class="btn btn-ghost-warning dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="fa-solid fa-circle-dollar-to-slot me-1"></i><?= gettext("Finance") ?>
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=people/family/<?= $family->getId() ?>&PledgeOrPayment=Pledge">
                        <i class="fa-solid fa-hand-holding-dollar me-2"></i><?= gettext('Add Pledge') ?>
                    </a>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath()?>/PledgeEditor.php?FamilyID=<?= $family->getId() ?>&amp;linkBack=people/family/<?= $family->getId() ?>&PledgeOrPayment=Payment">
                        <i class="fa-solid fa-money-bill-wave me-2"></i><?= gettext('Add Payment') ?>
                    </a>
                </div>
            </div>
            <?php } ?>
            <div class="dropdown ms-auto">
                <button class="btn btn-ghost-secondary dropdown-toggle" id="family-actions-dropdown" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical me-1"></i><?= gettext("Actions") ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($showFamilyCheckin && $memberCount > 0) { ?>
                    <button type="button" class="dropdown-item text-success fw-semibold" id="checkInFamilyBtn" data-bs-toggle="modal" data-bs-target="#familyCheckinModal">
                        <i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Check In Family') ?>
                    </button>
                    <div class="dropdown-divider"></div>
                    <?php } ?>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#confirm-verify">
                        <i class="fa-solid fa-clipboard-check me-2"></i><?= gettext('Verify Info') ?>
                    </a>
                    <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header"><?= gettext("Photo") ?></h6>
                        <a class="dropdown-item" id="uploadImageButton" href="#"><i class="fa-solid fa-camera me-2"></i><?= gettext("Upload Photo") ?></a>
                        <?php if ($family->getPhoto()->hasUploadedPhoto()) { ?>
                            <a class="dropdown-item" id="view-larger-image-btn" href="#"><i class="fa-solid fa-magnifying-glass-plus me-2"></i><?= gettext("View Photo") ?></a>
                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#confirm-delete-image"><i class="fa-solid fa-trash-can me-2"></i><?= gettext("Delete Photo") ?></a>
                        <?php } ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" id="activateDeactivate">
                            <i class="fa-solid fa-power-off me-2"></i><?= ($family->isActive() ? gettext('Set Inactive') : gettext('Set Active')) ?>
                        </a>
                    <?php } ?>
                    <?php if (AuthenticationManager::getCurrentUser()->isDeleteRecordsEnabled()) { ?>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-danger" id="deleteFamilyBtn" href="<?= SystemURLs::getRootPath() ?>/SelectDelete.php?FamilyID=<?= $family->getId() ?>">
                            <i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete Family') ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Family Members Card (table layout matching Person page) -->
        <?php
        // Helper: section header
        function renderSectionHeader(string $label, string $icon, string $color, int $count): void { ?>
            <div class="d-flex align-items-center py-2">
                <i class="fa-solid <?= $icon ?> text-<?= $color ?> me-2"></i>
                <span class="fw-bold"><?= $label ?></span>
                <span class="badge bg-<?= $color ?>-lt text-<?= $color ?> ms-2"><?= $count ?></span>
            </div>
        <?php }

        // Helper: action dropdown for a member row
        function renderMemberActions($person): void { ?>
            <div class="dropdown d-print-none">
                <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonView.php?PersonID=<?= $person->getID() ?>"><i class="fa-solid fa-eye me-2"></i><?= gettext('View') ?></a>
                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $person->getID() ?>"><i class="fa-solid fa-pen me-2"></i><?= gettext('Edit') ?></a>
                    <button class="dropdown-item AddToCart" data-cart-id="<?= $person->getId() ?>" data-cart-type="person"><i class="fa-solid fa-cart-plus me-2"></i><?= gettext('Add to Cart') ?></button>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item text-danger delete-person" data-person_name="<?= $person->getFullName() ?>" data-person_id="<?= $person->getId() ?>" data-view="family"><i class="fa-solid fa-trash-can me-2"></i><?= gettext('Delete') ?></button>
                </div>
            </div>
        <?php }

        // Helper: standard member table (Key People / Other)
        function renderMemberTable(array $members, string $label, string $icon, string $color): void {
            if (empty($members)) { return; } ?>
            <div class="mb-1">
                <?php renderSectionHeader($label, $icon, $color, count($members)); ?>
                <div style="overflow: visible;">
                    <table class="table table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th class="text-center"><?= gettext('Role') ?></th>
                                <th><?= gettext('Birthday') ?></th>
                                <th><?= gettext('Email') ?></th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $person) { ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="avatar avatar-sm me-2">
                                            <a href="<?= $person->getViewURI() ?>"><?= $person->getTitle() ?> <?= $person->getFullName() ?></a>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-lt text-secondary"><?= $person->getFamilyRoleName() ?></span>
                                    </td>
                                    <td><?= $person->getFormattedBirthDate() ?></td>
                                    <td>
                                        <?php $tmpEmail = $person->getEmail();
                                        if (!empty($tmpEmail)) { ?>
                                            <a href="mailto:<?= InputUtils::escapeAttribute($tmpEmail) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($tmpEmail) ?></a>
                                        <?php } ?>
                                    </td>
                                    <td><?php renderMemberActions($person); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php }

        // Helper: children table (no Role column, adds Sunday School column if enabled)
        function renderChildrenTable(array $members, string $label, string $icon, string $color): void {
            if (empty($members)) { return; }

            $ssEnabled = SystemConfig::getBooleanValue('bEnabledSundaySchool');

            // Pre-fetch Sunday School groups for all children in one query (only if enabled)
            $ssGroups = [];
            if ($ssEnabled) {
                try {
                    $childIds = array_map(fn($p) => $p->getId(), $members);
                    if (!empty($childIds)) {
                        $groups = GroupQuery::create()
                            ->filterByType(4) // Sunday School type
                            ->usePerson2group2roleP2g2rQuery()
                                ->filterByPersonId($childIds)
                            ->endUse()
                            ->find();
                        foreach ($groups as $group) {
                            foreach ($group->getPerson2group2roleP2g2rs() as $p2g2r) {
                                $pid = $p2g2r->getPersonId();
                                if (in_array($pid, $childIds)) {
                                    $ssGroups[$pid][] = $group->getName();
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Query failed — show column but with no data
                    $ssGroups = [];
                }
            }
        ?>
            <div class="mb-1">
                <?php renderSectionHeader($label, $icon, $color, count($members)); ?>
                <div style="overflow: visible;">
                    <table class="table table-vcenter card-table mb-0">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Birthday') ?></th>
                                <?php if ($ssEnabled) { ?>
                                <th><?= gettext('Sunday School') ?></th>
                                <?php } ?>
                                <th><?= gettext('Email') ?></th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $person) { ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="avatar avatar-sm me-2">
                                            <a href="<?= $person->getViewURI() ?>"><?= $person->getTitle() ?> <?= $person->getFullName() ?></a>
                                        </div>
                                    </td>
                                    <td><?= $person->getFormattedBirthDate() ?></td>
                                    <?php if ($ssEnabled) { ?>
                                    <td>
                                        <?php $personSS = $ssGroups[$person->getId()] ?? [];
                                        if (!empty($personSS)) {
                                            foreach ($personSS as $ssName) { ?>
                                                <span class="badge bg-info-lt text-info me-1"><?= InputUtils::escapeHTML($ssName) ?></span>
                                            <?php }
                                        } else { ?>
                                            <span class="text-muted">—</span>
                                        <?php } ?>
                                    </td>
                                    <?php } ?>
                                    <td>
                                        <?php $tmpEmail = $person->getEmail();
                                        if (!empty($tmpEmail)) { ?>
                                            <a href="mailto:<?= InputUtils::escapeAttribute($tmpEmail) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($tmpEmail) ?></a>
                                        <?php } ?>
                                    </td>
                                    <td><?php renderMemberActions($person); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>

        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-people-roof me-1"></i> <?= gettext("Family Members") ?></h3>
                <span class="badge bg-primary-lt text-primary ms-2"><?= $memberCount ?></span>
                <?php if (AuthenticationManager::getCurrentUser()->isEditRecordsEnabled()) { ?>
                <a class="btn btn-sm btn-outline-primary ms-auto" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?FamilyID=<?= $family->getId() ?>">
                    <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add Member') ?>
                </a>
                <?php } ?>
            </div>
            <div class="card-body">
                <?php renderMemberTable($keyPeople, gettext("Key People"), 'fa-crown', 'warning'); ?>
                <?php renderChildrenTable($childPeople, gettext("Children"), 'fa-children', 'info'); ?>
                <?php renderMemberTable($otherPeople, gettext("Other Members"), 'fa-user-group', 'secondary'); ?>
            </div>
        </div>

        <!-- Timeline Card -->
        <div class="card mb-3 timeline-container" id="family-timeline-container">
            <div class="card-header d-flex align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#family-timeline-body" aria-expanded="true">
                <h3 class="card-title m-0"><i class="fa-solid fa-clock-rotate-left me-1"></i> <?= gettext("Timeline") ?></h3>
                <div class="ms-auto"><i class="fa-solid fa-chevron-down"></i></div>
            </div>
            <div class="collapse show" id="family-timeline-body">
                <div class="card-body">
                    <?php if (empty($familyTimeline)) { ?>
                        <div class="alert alert-info">
                            <i class="fa-solid fa-circle-info fa-fw fa-lg"></i>
                            <span><?= gettext('No timeline events yet.') ?></span>
                        </div>
                    <?php } else {
                        $timelineCounts = ['notes' => 0, 'events' => 0, 'system' => 0];
                        foreach ($familyTimeline as $tlItem) {
                            $cat = $tlItem['category'] ?? 'notes';
                            if (isset($timelineCounts[$cat])) {
                                $timelineCounts[$cat]++;
                            }
                        }
                        ?>
                        <div class="timeline-filters d-flex flex-wrap align-items-center gap-2 mb-3" role="group" aria-label="<?= gettext('Timeline filters') ?>">
                            <span class="text-muted small me-1"><i class="fa-solid fa-filter me-1"></i><?= gettext('Show:') ?></span>
                            <button type="button" class="btn btn-sm btn-primary timeline-filter-chip active" data-filter="notes">
                                <i class="fa-solid fa-note-sticky me-1"></i><?= gettext('Notes') ?>
                                <span class="badge bg-white text-primary ms-1"><?= $timelineCounts['notes'] ?></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary timeline-filter-chip" data-filter="events">
                                <i class="fa-solid fa-calendar-days me-1"></i><?= gettext('Events') ?>
                                <span class="badge bg-secondary-lt text-secondary ms-1"><?= $timelineCounts['events'] ?></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary timeline-filter-chip" data-filter="system">
                                <i class="fa-solid fa-gear me-1"></i><?= gettext('System') ?>
                                <span class="badge bg-secondary-lt text-secondary ms-1"><?= $timelineCounts['system'] ?></span>
                            </button>
                            <button type="button" class="btn btn-sm btn-link ms-auto timeline-filter-all" data-filter="all">
                                <?= gettext('Show all') ?>
                            </button>
                        </div>
                        <div class="timeline-empty-notice alert alert-info" style="display:none;">
                            <i class="fa-solid fa-circle-info fa-fw me-1"></i><?= gettext('No matching entries for the selected filters.') ?>
                        </div>
                        <?php $currentYear = ''; ?>
                        <div class="timeline mt-3">
                            <?php foreach ($familyTimeline as $item) {
                                if ($currentYear !== $item['year']) {
                                    $currentYear = $item['year']; ?>
                                    <div class="hr-text timeline-year" data-timeline-year="<?= htmlspecialchars((string)$currentYear) ?>"><i class="fa-solid fa-calendar-days"></i> <?= $currentYear ?></div>
                                <?php } ?>
                                <div class="timeline-event" data-timeline-category="<?= htmlspecialchars($item['category'] ?? 'notes') ?>" data-timeline-year="<?= htmlspecialchars((string)$item['year']) ?>">
                                    <div class="timeline-event-icon bg-<?= $item['color'] ?>-lt text-<?= $item['color'] ?>">
                                        <i class="fa-solid <?= $item['style'] ?>"></i>
                                    </div>
                                    <div class="timeline-event-card card">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div>
                                                    <?php if ($item['slim']) { ?>
                                                        <span class="text-secondary"><?= $item['text'] ?> <?= gettext($item['header']) ?></span>
                                                    <?php } else { ?>
                                                        <strong>
                                                            <?php if (array_key_exists('headerlink', $item)) { ?>
                                                                <a href="<?= $item['headerlink'] ?>"><?= $item['header'] ?></a>
                                                            <?php } else { ?>
                                                                <?= gettext($item['header']) ?>
                                                            <?php } ?>
                                                        </strong>
                                                    <?php } ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-1 ms-2 flex-shrink-0">
                                                    <?php if (AuthenticationManager::getCurrentUser()->isNotesEnabled() && (isset($item["editLink"]) || isset($item["deleteLink"]))) { ?>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" data-bs-display="static"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                                            <div class="dropdown-menu dropdown-menu-end">
                                                                <?php if (isset($item["editLink"])) { ?>
                                                                    <a href="<?= $item["editLink"] ?>" class="dropdown-item"><i class="fa-solid fa-pen me-2"></i><?= gettext('Edit') ?></a>
                                                                <?php }
                                                                if (isset($item["deleteLink"])) { ?>
                                                                    <a href="<?= $item["deleteLink"] ?>" class="dropdown-item text-danger"><i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?></a>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <?php if (!$item['slim'] && !empty($item['text'])) { ?>
                                                <div class="text-secondary mt-1" style="white-space: pre-wrap; font-size: 0.875rem;"><?= $item['text'] ?></div>
                                            <?php } ?>
                                            <small class="text-muted"><i class="fa-solid fa-clock me-1"></i><?= $item['datetime'] ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: Navigation, Photo, Address, Contact, Properties -->
    <div class="col-12 col-lg-4">
        <!-- Family Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="<?= SystemURLs::getRootPath()?>/people/family" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Back to Families'); ?>
            </a>
            <div class="btn-group" role="group" aria-label="<?= gettext('Family Navigation'); ?>">
                <a id="lastFamily" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <a id="nextFamily" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>
        </div>

        <!-- Family Photo & Attributes Card -->
        <div class="card mb-3">
            <div class="card-body p-0">
                <div class="d-flex">
                    <!-- Photo (left) — click to upload -->
                    <div class="flex-shrink-0 position-relative" style="width: 160px; aspect-ratio: 1 / 1;">
                        <a href="#" id="uploadImageTrigger" class="d-block w-100 h-100" title="<?= AuthenticationManager::getCurrentUser()->isEditRecordsEnabled() ? gettext("Click to upload photo") : gettext("View Photo") ?>">
                            <img data-image-entity-type="family"
                                 data-image-entity-id="<?= $family->getId() ?>" class="photo-profile w-100 h-100 object-fit-cover"
                                 style="border-radius: var(--tblr-border-radius) 0 0 var(--tblr-border-radius);">
                        </a>
                        <button type="button"
                                class="photo-view-overlay btn btn-sm position-absolute bottom-0 end-0 m-1 d-none"
                                data-entity-type="family"
                                data-entity-id="<?= $family->getId() ?>"
                                title="<?= gettext('View full photo') ?>"
                                aria-label="<?= gettext('View full photo') ?>">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true" style="color:white; text-shadow: 0 1px 3px rgba(0,0,0,.8);"></i>
                        </button>
                    </div>
                    <!-- Attributes (right) -->
                    <div class="p-3 flex-grow-1">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-1">
                                <i class="fa-solid fa-circle me-2 <?= $family->isActive() ? 'text-success' : 'text-secondary' ?>" style="width: 1rem; text-align: center;"></i><?= $family->isActive() ? gettext('Active') : gettext('Inactive') ?>
                            </li>
                            <li class="mb-1"><i class="fa-solid fa-person-half-dress me-2 text-muted" style="width: 1rem; text-align: center;"></i><?= $memberCount ?> <?= $memberCount == 1 ? gettext('Member') : gettext('Members') ?></li>
                            <?php if (!empty($family->getHomePhone())) { ?>
                            <li class="mb-1">
                                <i class="fa-solid fa-phone me-2 text-muted" style="width: 1rem; text-align: center;"></i><a href="tel:<?= InputUtils::escapeAttribute($family->getHomePhone()) ?>"><?= InputUtils::escapeHTML($family->getHomePhone()) ?></a>
                            </li>
                            <?php } ?>
                            <?php if (!SystemConfig::getBooleanValue("bHideFamilyNewsletter")) { ?>
                            <li class="mb-1">
                                <i class="fa-solid fa-newspaper me-2 text-muted" style="width: 1rem; text-align: center;"></i><?= gettext("Newsletter") ?>:
                                <span class="<?= ($family->isSendNewsletter() ? "text-success" : "text-danger") ?>"><i class="fa-solid fa-<?= ($family->isSendNewsletter() ? "check" : "times") ?>"></i></span>
                            </li>
                            <?php } ?>
                            <?php if ($family->getEnvelope()) { ?>
                            <li class="mb-1"><i class="fa-solid fa-envelope me-2 text-muted" style="width: 1rem; text-align: center;"></i><?= gettext('Envelope') ?> #<?= $family->getEnvelope() ?></li>
                            <?php } ?>
                            <?php if (!SystemConfig::getBooleanValue("bHideWeddingDate") && !empty($family->getWeddingdate())) { ?>
                            <li class="mb-1"><i class="fa-solid fa-ring me-2 text-muted" style="width: 1rem; text-align: center;"></i><?= $family->getWeddingDate()->format(SystemConfig::getValue("sDateFormatLong")) ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Address Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-map me-1"></i> <?= gettext("Address") ?>
                    <?php if ($family->hasLatitudeAndLongitude()): ?>
                    <span class="badge bg-green-lt text-green ms-2" title="<?= gettext('Address has been geocoded (coordinates stored)') ?>">
                        <i class="fa-solid fa-check"></i> <?= gettext('Geocoded') ?>
                    </span>
                    <?php elseif ($family->hasAddress()): ?>
                    <span class="badge bg-warning text-dark ms-2" title="<?= gettext('Address entered but coordinates not yet set') ?>">
                        <i class="fa-solid fa-triangle-exclamation"></i> <?= gettext('Unverified') ?>
                    </span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="card-body">
                <a href="https://maps.google.com/?q=<?= urlencode($familyAddress) ?>"
                   target="_blank" rel="noopener noreferrer"><?= $familyAddress ?></a>
                <?php
                $directionsUrl = $family->getDirectionsUrl();
                $appleDirectionsUrl = $family->getAppleMapsDirectionsUrl();
                ?>
                <div class="mt-2">
                    <?php if (!empty($directionsUrl) || !empty($appleDirectionsUrl)) : ?>
                    <div class="btn-group directions-btn-group">
                        <?php if (!empty($directionsUrl)) : ?>
                        <a href="<?= $directionsUrl ?>" target="_blank" rel="noopener noreferrer"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-diamond-turn-right me-1"></i><?= gettext('Get Directions') ?>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($appleDirectionsUrl)) : ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary dropdown-toggle dropdown-toggle-split directions-provider-toggle d-none"
                                data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                            <span class="visually-hidden"><?= gettext('Choose map provider') ?></span>
                        </button>
                        <div class="dropdown-menu directions-provider-menu d-none">
                            <?php if (!empty($directionsUrl)) : ?>
                            <a class="dropdown-item" href="<?= $directionsUrl ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fa-brands fa-google me-2"></i><?= gettext('Open in Google Maps') ?>
                            </a>
                            <?php endif; ?>
                            <a class="dropdown-item apple-maps-option" href="<?= $appleDirectionsUrl ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fa-brands fa-apple me-2"></i><?= gettext('Open in Apple Maps') ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!$family->hasLatitudeAndLongitude()) : ?>
                    <button type="button" class="btn btn-sm btn-outline-success" id="refresh-coordinates-btn"
                            data-family-id="<?= $family->getId() ?>"
                            title="<?= gettext('Automatically detect coordinates using address') ?>">
                        <i class="fa-solid fa-location-dot me-1"></i><?= gettext('Refresh Coordinates') ?>
                    </button>
                    <?php endif; ?>
                </div>

                <?php if ($family->hasLatitudeAndLongitude()) : ?>
                    <div class="mt-2 rounded overflow-hidden">
                        <div id="map1" style="height: 200px;"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($family->getEmail())) { ?>
        <!-- Email Card (with Mailchimp status if plugin enabled) -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-envelope me-1"></i> <?= gettext("Email") ?></h3>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-1">
                        <i class="fa-solid fa-envelope me-2 text-muted" style="width: 1rem; text-align: center;"></i><a href="mailto:<?= InputUtils::escapeAttribute($family->getEmail()) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($family->getEmail()) ?></a>
                        <button class="btn btn-sm btn-ghost-secondary ms-1 copy-email-btn" type="button"
                                data-email="<?= InputUtils::escapeAttribute($family->getEmail()) ?>"
                                title="<?= gettext('Copy to clipboard') ?>">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </li>
                    <!-- MailChimp status - populated by JavaScript if plugin is active -->
                    <li class="d-none mb-1" id="mailchimp-status-container">
                        <i class="fa-regular fa-paper-plane me-2 text-muted" style="width: 1rem; text-align: center;"></i><?= gettext("Mailchimp") ?>:
                        <span id="mailchimp-status">... <?= gettext("loading")?> ...</span>
                    </li>
                </ul>
            </div>
        </div>
        <?php } ?>

        <?php if (!empty($familyCustom)) { ?>
        <!-- Custom Fields Card (collapsible) -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#family-custom-body" aria-expanded="true">
                <h3 class="card-title m-0"><i class="fa-solid fa-sliders me-1"></i> <?= gettext("Custom Fields") ?></h3>
                <div class="ms-auto"><i class="fa-solid fa-chevron-down"></i></div>
            </div>
            <div class="collapse show" id="family-custom-body">
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($familyCustom as $customField) { ?>
                            <li class="mb-1">
                                <i class="<?= $customField->getIcon() ?> me-2 text-muted" style="width: 1rem; text-align: center;"></i><?= $customField->getDisplayValue() ?>:
                                <?php if ($customField->getLink()) { ?>
                                    <a href="<?= $customField->getLink() ?>"><?= $customField->getFormattedValue() ?></a>
                                <?php } else {
                                    $val = $customField->getFormattedValue();
                                    if (strlen($val) > 40) { ?>
                                        <span class="d-block text-muted text-truncate" title="<?= InputUtils::escapeAttribute($val) ?>"><?= $val ?></span>
                                    <?php } else { ?>
                                        <span><?= $val ?></span>
                                    <?php }
                                } ?>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php } ?>

        <!-- Properties Card (inline, matching Person page style) -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-hashtag me-1"></i> <?= gettext("Properties") ?></h3>
            </div>
            <div class="card-body">
                <?php if (count($assignedFamilyProperties) === 0) : ?>
                    <div class="text-center text-muted py-3">
                        <i class="fa-solid fa-tags fa-2x mb-2 d-block opacity-50"></i>
                        <p class="mb-0"><?= gettext("No properties assigned.") ?></p>
                    </div>
                <?php else : ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($assignedFamilyProperties as $rp) {
                            $prop = $rp->getProperty();
                            $propType = $prop->getPropertyType();
                            $value = $rp->getPropertyValue(); ?>
                            <div class="list-group-item px-0 d-flex align-items-center">
                                <div class="me-auto">
                                    <strong><?= InputUtils::escapeHTML($prop->getProName()) ?></strong>
                                    <?php if ($propType) { ?>
                                        <span class="badge bg-secondary-lt text-secondary ms-1"><?= InputUtils::escapeHTML($propType->getPrtName()) ?></span>
                                    <?php } ?>
                                    <?php if (!empty($value)) { ?>
                                        <small class="text-muted d-block"><?= InputUtils::escapeHTML($value) ?></small>
                                    <?php } ?>
                                </div>
                                <?php if ($canEditRecords) { ?>
                                    <button class="btn btn-sm btn-ghost-danger remove-family-property-btn" data-property_id="<?= (int) $prop->getProId() ?>" title="<?= gettext('Remove') ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php endif; ?>

                <?php if ($canEditRecords && count($allFamilyProperties) > 0) : ?>
                    <div class="mt-3 d-print-none">
                        <form method="post" id="assign-family-property-form">
                            <div class="mb-2">
                                <select name="PropertyId" id="input-family-properties" class="form-select" data-placeholder="<?= gettext('Choose a property...') ?>">
                                    <option value=""></option>
                                    <?php
                                    $valueByPropId = [];
                                    foreach ($assignedFamilyProperties as $rp) {
                                        $valueByPropId[(int) $rp->getProperty()->getProId()] = $rp->getPropertyValue();
                                    }
                                    foreach ($allFamilyProperties as $prop) {
                                        $pid = (int) $prop->getProId();
                                        $isAssigned = array_key_exists($pid, $valueByPropId);
                                        $attrs = 'value="' . $pid . '"';
                                        $prompt = $prop->getProPrompt();
                                        if (!empty($prompt)) {
                                            $attrs .= ' data-pro_Prompt="' . InputUtils::escapeAttribute($prompt) . '"';
                                            $attrs .= ' data-pro_Value="' . InputUtils::escapeAttribute($valueByPropId[$pid] ?? '') . '"';
                                        }
                                        $optionText = InputUtils::escapeHTML($prop->getProName());
                                        if ($isAssigned) {
                                            $optionText .= ' (' . gettext('assigned') . ')';
                                        }
                                        echo "<option {$attrs}>{$optionText}</option>";
                                    } ?>
                                </select>
                            </div>
                            <div id="family-property-prompt-box" class="mb-2"></div>
                            <button id="assign-family-property-btn" type="button" class="btn btn-sm btn-primary w-100">
                                <i class="fa-solid fa-check me-1"></i><?= gettext('Assign') ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
if (AuthenticationManager::getCurrentUser()->isFinanceEnabled()) { ?>
<!-- Pledges and Payments — full width row -->
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <h3 class="card-title m-0"><i class="fa-solid fa-circle-dollar-to-slot me-1"></i> <?= gettext("Pledges and Payments") ?></h3>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <ul class="nav nav-pills" role="tablist">
                        <li class="nav-item"><a class="nav-link active pledge-type-pill" href="#" data-filter=""><?= gettext("All") ?></a></li>
                        <li class="nav-item"><a class="nav-link pledge-type-pill" href="#" data-filter="Pledge"><?= gettext("Pledges") ?></a></li>
                        <li class="nav-item"><a class="nav-link pledge-type-pill" href="#" data-filter="Payment"><?= gettext("Payments") ?></a></li>
                    </ul>
                    <span class="vr mx-1"></span>
                    <ul class="nav nav-pills" role="tablist">
                        <li class="nav-item"><a class="nav-link pledge-fy-pill" href="#" data-fy=""><?= gettext("All Time") ?></a></li>
                        <li class="nav-item"><a class="nav-link active pledge-fy-pill" href="#" data-fy="<?= $currentFY ?>"><?= sprintf(gettext("FY %s"), $currentFY) ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="table-responsive" style="overflow: visible;">
                <table id="pledge-payment-v2-table" class="table table-vcenter card-table" style="width: 100%;">
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<!-- Leaflet map (loaded only if geocoded) -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.css') ?>">
<?php if ($family->hasLatitudeAndLongitude()) : ?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.familyMapConfig = <?= json_encode(['lat' => (float) $family->getLatitude(), 'lng' => (float) $family->getLongitude()]) ?>;
</script>
<?php endif; ?>
<?php if ($showFamilyCheckin): ?>
<!-- Family Check-In Modal (#6838) -->
<div class="modal fade" id="familyCheckinModal" tabindex="-1" aria-labelledby="familyCheckinModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyCheckinModalLabel">
                    <i class="fa-solid fa-clipboard-check me-2 text-success"></i>
                    <?= gettext('Check In Family') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary">
                    <?= sprintf(gettext('Check in all %d family members to a single event.'), $memberCount) ?>
                </p>
                <div class="mb-3">
                    <label for="familyCheckinEventSelect" class="form-label fw-bold"><?= gettext('Select Event') ?></label>
                    <select class="form-select" id="familyCheckinEventSelect">
                        <option value=""><?= gettext('Choose an event...') ?></option>
                    </select>
                </div>
                <div id="familyCheckinNoEvents" class="alert alert-warning d-none">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    <?= gettext('No active events found.') ?>
                    <a href="<?= SystemURLs::getRootPath() ?>/event/editor"><?= gettext('Create one now') ?></a>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="button" class="btn btn-success" id="familyCheckinSubmit" disabled>
                    <i class="fa-solid fa-check me-1"></i><?= gettext('Check In') ?>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="<?= SystemURLs::assetVersioned('/skin/external/leaflet/leaflet.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-family-view.min.js') ?>"></script>

<!-- Photo uploader bundle - loaded only on this page -->
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/photo-uploader.min.js') ?>"></script>

<script src="<?= SystemURLs::assetVersioned('/skin/js/MemberView.js') ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/FamilyView.js') ?>"></script>

<!-- Photos start -->
<div class="modal fade" id="confirm-delete-image" tabindex="-1" role="dialog"
     aria-labelledby="delete-Image-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="modal-title" id="delete-Image-label"><?= gettext("Confirm Delete") ?></h4>
            </div>

            <div class="modal-body">
                <p><?= gettext("You are about to delete the profile photo, this procedure is irreversible.") ?></p>

                <p><?= gettext("Do you want to proceed?") ?></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button class="btn btn-danger danger" id="deletePhoto"><?= gettext("Delete") ?></button>

            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    // Copy photo uploader function from temporary storage to window.CRM
    // This must happen after Header-function.php initializes window.CRM
    if (window._CRM_createPhotoUploader) {
        window.CRM.createPhotoUploader = window._CRM_createPhotoUploader;
    } else {
        console.error('Photo uploader function not found in window._CRM_createPhotoUploader');
    }

    // Initialize photo uploader when window loads
    window.addEventListener('load', function() {
        if (typeof window.CRM.createPhotoUploader !== 'function') {
            console.error('window.CRM.createPhotoUploader is not a function');
            return;
        }

        window.CRM.photoUploader = window.CRM.createPhotoUploader({
            uploadUrl: window.CRM.root +"/api/family/" + window.CRM.currentFamily +"/photo",
            maxFileSize: window.CRM.maxUploadSizeBytes,
            photoHeight: <?= Photo::PHOTO_HEIGHT ?>,
            photoWidth: <?= Photo::PHOTO_WIDTH ?>,
            onComplete: function() {
                location.reload();
            }
        });
    });

    // Set up click handlers for photo upload (both action menu item and photo click)
    $(document).on('click', '#uploadImageButton, #uploadImageTrigger', function(e) {
        e.preventDefault();
        if (window.CRM && window.CRM.photoUploader) {
            window.CRM.photoUploader.show();
        } else {
            console.error('Photo uploader not initialized!');
        }
    });
</script>
<!-- Photos end -->

<div class="modal fade" id="confirm-verify" tabindex="-1" role="dialog" aria-labelledby="confirm-verify-label"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <h4 class="modal-title"
                    id="confirm-verify-label"><?= gettext("Request Family Info Verification") ?></h4>
            </div>
            <div class="modal-body">
                <b><?= gettext("Select how do you want to request the family information to be verified") ?></b>
                <p>
                    <?php if (count($familyEmails) > 0) {
                        ?>
                <p><?= gettext("You are about to email copy of the family information to the following emails") ?>
                <ul>
                        <?php foreach ($familyEmails as $tmpEmail) { ?>
                        <li><?= $tmpEmail ?></li>
                        <?php } ?>
                </ul>
                </p>
            </div>
                        <?php
                    } ?>
            <div class="modal-footer text-center">
                <?php if (count($familyEmails) > 0 && SystemConfig::isEmailEnabled()) {
                    ?>
                    <button type="button" id="onlineVerify"
                            class="btn btn-warning warning"><i
                            class="fa-solid fa-envelope"></i><?= gettext("Online Verification") ?>
                    </button>
                    <button type="button" id="verifyEmailPDF"
                            class="btn btn-warning"><i
                            class="fa-solid fa-file-pdf"></i><?= gettext("Email PDF") ?>
                    </button>
                    <?php
                } ?>
                <button type="button" id="verifyURL"
                        class="btn btn-secondary"><i class="fa-solid fa-link"></i><?= gettext("URL") ?></button>
                <button type="button" id="verifyDownloadPDF"
                        class="btn btn-info"><i class="fa-solid fa-download"></i><?= gettext("PDF") ?></button>
                <button type="button" id="verifyNow"
                        class="btn btn-success"><i class="fa-solid fa-check"></i><?= gettext("Verified In Person") ?>
                </button>
            </div>
        </div>
    </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
(function () {
    function initTimelineFilter(container) {
        if (!container) { return; }
        var chips = container.querySelectorAll(".timeline-filter-chip");
        var allChip = container.querySelector(".timeline-filter-all");
        var events = container.querySelectorAll(".timeline-event[data-timeline-category]");
        var years = container.querySelectorAll(".timeline-year");
        var emptyNotice = container.querySelector(".timeline-empty-notice");
        if (chips.length === 0 || events.length === 0) { return; }
        var active = new Set(["notes"]);
        function applyFilter() {
            var showAll = active.has("all");
            var visibleYears = {};
            var anyVisible = false;
            events.forEach(function (el) {
                var cat = el.getAttribute("data-timeline-category") || "notes";
                var visible = showAll || active.has(cat);
                el.style.display = visible ? "" : "none";
                if (visible) { anyVisible = true; visibleYears[el.getAttribute("data-timeline-year") || ""] = true; }
            });
            years.forEach(function (y) { y.style.display = visibleYears[y.getAttribute("data-timeline-year") || ""] ? "" : "none"; });
            if (emptyNotice) { emptyNotice.style.display = anyVisible ? "none" : ""; }
            chips.forEach(function (chip) {
                var f = chip.getAttribute("data-filter"), on = showAll || active.has(f);
                chip.classList.toggle("btn-primary", on); chip.classList.toggle("active", on); chip.classList.toggle("btn-outline-secondary", !on);
            });
            if (allChip) { allChip.classList.toggle("active", showAll); }
        }
        chips.forEach(function (chip) {
            chip.addEventListener("click", function () {
                var f = chip.getAttribute("data-filter"); if (!f) return;
                active.delete("all"); if (active.has(f)) active.delete(f); else active.add(f);
                if (active.size === 0) active.add("notes");
                applyFilter();
            });
        });
        if (allChip) { allChip.addEventListener("click", function () { active = new Set(["all"]); applyFilter(); }); }
        applyFilter();
    }
    function isAppleMobile() {
        var ua = navigator.userAgent || "";
        if (/iPad|iPhone|iPod/.test(ua) && !window.MSStream) return true;
        if (navigator.platform === "MacIntel" && typeof navigator.maxTouchPoints === "number" && navigator.maxTouchPoints > 1) return true;
        return false;
    }
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".timeline-container").forEach(initTimelineFilter);
        if (isAppleMobile()) {
            document.querySelectorAll(".directions-provider-toggle").forEach(function (el) { el.classList.remove("d-none"); });
            document.querySelectorAll(".directions-provider-menu").forEach(function (el) { el.classList.remove("d-none"); });
        }
    });
})();
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
