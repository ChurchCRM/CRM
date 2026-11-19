<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

?>
<!-- Stat Cards Row -->
<div class="row row-cards mb-3 g-2">
    <?php if ($canReviewSelfRegistrations) { ?>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/people/self-register" class="card card-sm text-decoration-none" id="selfRegisterDashboardCard">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-user-clock icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="selfRegisterDashboardCount">0</div>
                        <div class="text-body-secondary"><?= gettext('Self-Registration') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/people/family" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-roof icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="familyCountDashboard"><?= $dashboardCounts["families"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Families') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/people/list" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-group icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="peopleStatsDashboard"><?= $dashboardCounts["People"] ?></div>
                        <div class="text-body-secondary"><?= gettext('People') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= $sRootPath ?>/groups/dashboard" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="groupsCountDashboard"><?= $dashboardCounts["Groups"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Groups') ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <?php if ($sundaySchoolEnabled): ?>
        <a href="<?= $sRootPath ?>/groups/sundayschool" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-child icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body" id="groupStatsSundaySchool"><?= $dashboardCounts["SundaySchool"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Sunday School') ?></div>
                    </div>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="card card-sm opacity-50">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-warning text-white avatar rounded-circle">
                            <i class="fa-solid fa-child icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body-secondary"><?= gettext('No Sunday School') ?></div>
                        <div class="text-body-secondary small"><?= gettext('Disabled in settings') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <?php if ($eventsEnabled): ?>
        <a href="<?= $sRootPath ?>/event/dashboard" class="card card-sm text-decoration-none">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-regular fa-calendar-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body"><?= $dashboardCounts["events"] ?></div>
                        <div class="text-body-secondary"><?= gettext('Check-ins') ?></div>
                    </div>
                </div>
            </div>
        </a>
        <?php else: ?>
        <div class="card card-sm opacity-50">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-regular fa-calendar-check icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium text-body-secondary"><?= gettext('No Check-ins') ?></div>
                        <div class="text-body-secondary small"><?= gettext('Disabled in settings') ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($eventsEnabled && $canViewEvents) { ?>
<div class="card mb-3" id="todayEventsCard">
    <div class="card-header py-2">
        <h3 class="card-title">
            <i class="fa-solid fa-calendar-day me-2"></i><?= gettext("Today's Events") ?>
            <span class="ms-2 text-body-secondary fw-normal" id="todayEventsCount"></span>
        </h3>
        <div class="card-actions d-flex gap-2">
            <a href="<?= $sRootPath ?>/event/checkin" id="todayEventsCheckin" class="btn btn-sm btn-outline-primary d-none">
                <i class="fa-solid fa-clipboard-check me-1"></i><?= gettext('Check-in') ?>
            </a>
            <button type="button" id="todayEventsToggle" class="btn btn-sm btn-ghost-secondary d-none" data-bs-toggle="collapse" data-bs-target="#todayEventsBody" aria-expanded="false" aria-controls="todayEventsBody" aria-label="<?= InputUtils::escapeAttribute(gettext("Today's Events")) ?>">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse" id="todayEventsBody">
        <div class="card-body p-0">
            <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="todayEventsDashboardItem"></table>
        </div>
    </div>
</div>
<?php } ?>

<div class="d-flex justify-content-end align-items-center gap-2 mb-2">
    <label for="people-range" class="form-label mb-0 small text-body-secondary fw-semibold"><?= gettext('Time Range') ?>:</label>
    <select id="people-range" class="form-select form-select-sm w-auto" aria-label="<?= InputUtils::escapeAttribute(gettext('Time range for the cards below')) ?>">
        <?php foreach ([7 => gettext('7 days'), 14 => gettext('14 days'), 30 => gettext('30 days'), 60 => gettext('60 days'), 90 => gettext('90 days'), 180 => gettext('6 months')] as $value => $label) : ?>
            <option value="<?= $value ?>"<?= $peopleDays === $value ? ' selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="row">
    <!-- People card — primary content (2/3 width) -->
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-users me-2 text-body-secondary"></i><?= gettext('People') ?>
                </div>
                <div class="card-options">
                    <ul class="nav nav-tabs card-header-tabs" id="people-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="latest-fam-tab" data-bs-toggle="tab" href="#latest-fam-pane" role="tab" aria-controls="latest-fam-pane" aria-selected="true">
                                <i class="fa-solid fa-house-chimney me-1"></i><span class="d-none d-xl-inline"><?= gettext('Latest Families') ?></span><span class="d-xl-none"><?= gettext('New') ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="updated-fam-tab" data-bs-toggle="tab" href="#updated-fam-pane" role="tab" aria-controls="updated-fam-pane" aria-selected="false">
                                <i class="fa-solid fa-house-circle-check me-1"></i><span class="d-none d-xl-inline"><?= gettext('Updated Families') ?></span><span class="d-xl-none"><?= gettext('Updated') ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="latest-ppl-tab" data-bs-toggle="tab" href="#latest-ppl-pane" role="tab" aria-controls="latest-ppl-pane" aria-selected="false">
                                <i class="fa-solid fa-user-plus me-1"></i><span class="d-none d-xl-inline"><?= gettext('Latest People') ?></span><span class="d-xl-none"><?= gettext('New') ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="updated-ppl-tab" data-bs-toggle="tab" href="#updated-ppl-pane" role="tab" aria-controls="updated-ppl-pane" aria-selected="false">
                                <i class="fa-solid fa-user-pen me-1"></i><span class="d-none d-xl-inline"><?= gettext('Updated People') ?></span><span class="d-xl-none"><?= gettext('Updated') ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="tab-content" id="people-tabs-content">
                    <div class="tab-pane fade show active" id="latest-fam-pane" role="tabpanel" aria-labelledby="latest-fam-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="latestFamiliesDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="updated-fam-pane" role="tabpanel" aria-labelledby="updated-fam-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="updatedFamiliesDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="latest-ppl-pane" role="tabpanel" aria-labelledby="latest-ppl-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="latestPersonDashboardItem"></table>
                    </div>
                    <div class="tab-pane fade" id="updated-ppl-pane" role="tabpanel" aria-labelledby="updated-ppl-tab">
                        <table class="table table-vcenter table-hover card-table mb-0" width="100%" id="updatedPersonDashboardItem"></table>
                    </div>
                </div>
            </div>
        </div>
<?php if ($notesEnabled) : ?>
        <div class="card mb-3" id="latestNotesCard">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-note-sticky me-2"></i><?= gettext('Latest Notes') ?></h3>
                <div class="card-actions d-flex align-items-center gap-1<?= count($latestNotes) > 5 ? '' : ' d-none' ?>">
                    <button type="button" class="btn btn-sm btn-ghost-secondary" id="notesPrev" aria-label="<?= InputUtils::escapeAttribute(gettext('Previous')) ?>"><i class="fa-solid fa-chevron-left"></i></button>
                    <span class="small text-body-secondary" id="notesPage"></span>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" id="notesNext" aria-label="<?= InputUtils::escapeAttribute(gettext('Next')) ?>"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <?php if ($latestNotes === []) : ?>
                <div class="card-body text-body-secondary small" id="latestNotesEmpty"><?= gettext('No notes in this time range.') ?></div>
            <?php else : ?>
                <div class="list-group list-group-flush" id="latestNotesList">
                    <?php foreach ($latestNotes as $note) : ?>
                        <a href="<?= InputUtils::escapeAttribute($note['url']) ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-center gap-2">
                                <strong class="text-truncate"><?= InputUtils::escapeHTML($note['subject']) ?></strong>
                                <?php if ($note['private']) : ?>
                                    <span class="badge bg-secondary-lt" title="<?= InputUtils::escapeAttribute(gettext('Private')) ?>"><i class="fa-solid fa-lock" aria-hidden="true"></i><span class="visually-hidden"><?= gettext('Private') ?></span></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($note['excerpt'] !== '') : ?>
                                <div class="text-body text-break small"><?= InputUtils::escapeHTML($note['excerpt']) ?></div>
                            <?php endif; ?>
                            <div class="text-body-secondary small"><?= InputUtils::escapeHTML(sprintf(gettext('%s by %s'), $note['date'], $note['author'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
<?php endif; ?>
    </div>

    <!-- Birthdays & Anniversaries — sidebar (1/3 width) -->
    <div class="col-lg-4">
        <div class="card mb-3" id="birthdayCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-cake-candles me-2"></i><?= gettext('Birthdays') ?></h3>
            </div>
            <div class="card-body p-0">
                <p class="text-body-secondary small px-3 pt-3 mb-2"><?= sprintf(gettext('Past 7 days and next %d days'), $peopleDays) ?></p>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" width="100%" id="PersonBirthdayDashboardItem"></table>
                </div>
            </div>
        </div>
        <div class="card mb-3" id="anniversaryCard">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fa-solid fa-heart me-2"></i><?= gettext('Anniversaries') ?></h3>
            </div>
            <div class="card-body p-0">
                <p class="text-body-secondary small px-3 pt-3 mb-2"><?= sprintf(gettext('Past 7 days and next %d days'), $peopleDays) ?></p>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" width="100%" id="FamiliesWithAnniversariesDashboardItem"></table>
                </div>
            </div>
        </div>
    </div>
</div>

