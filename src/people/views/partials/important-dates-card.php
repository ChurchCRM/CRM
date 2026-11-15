<?php

use ChurchCRM\dto\SystemURLs;

/**
 * Yearly dates on a person or a family, soonest first, with inline add and edit.
 *
 * @var string $importantDatesOwner 'person' or 'family'
 * @var int    $importantDatesOwnerId
 */
?>
<div class="card mb-3 d-print-none" id="important-dates-card"
     data-owner="<?= $importantDatesOwner === 'family' ? 'family' : 'person' ?>"
     data-owner-id="<?= (int) $importantDatesOwnerId ?>"
     data-manage-types-url="<?= SystemURLs::getRootPath() ?>/admin/people/important-dates">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title m-0"><i class="fa-solid fa-calendar-day me-1"></i><?= gettext('Important Dates') ?></h3>
        <div class="ms-auto">
            <button type="button" class="btn btn-sm btn-ghost-primary d-none" id="important-dates-add" aria-expanded="false" aria-controls="important-dates-form">
                <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Date') ?>
            </button>
        </div>
    </div>
    <div class="card-body">
        <form id="important-dates-form" class="d-none mb-3" novalidate>
            <input type="hidden" name="dateId" value="">
            <div class="mb-2" id="important-dates-type-row">
                <label class="form-label" for="important-dates-type"><?= gettext('Date type') ?></label>
                <select class="form-select" id="important-dates-type" name="typeId" required></select>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-5">
                    <label class="form-label" for="important-dates-month"><?= gettext('Month') ?></label>
                    <select class="form-select" id="important-dates-month" name="month" required></select>
                </div>
                <div class="col-3">
                    <label class="form-label" for="important-dates-day"><?= gettext('Day') ?></label>
                    <select class="form-select" id="important-dates-day" name="day" required></select>
                </div>
                <div class="col-4">
                    <label class="form-label" for="important-dates-year"><?= gettext('Year') ?></label>
                    <input type="number" class="form-control" id="important-dates-year" name="year" inputmode="numeric" min="1800" placeholder="<?= gettext('Optional') ?>">
                </div>
            </div>
            <div class="mb-2">
                <label class="form-label" for="important-dates-note"><?= gettext('Note') ?></label>
                <input type="text" class="form-control" id="important-dates-note" name="note" maxlength="255" placeholder="<?= gettext('Optional, e.g. where or by whom') ?>">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check me-1"></i><?= gettext('Save') ?></button>
                <button type="button" class="btn btn-sm btn-ghost-secondary" id="important-dates-cancel"><?= gettext('Cancel') ?></button>
            </div>
        </form>
        <div id="important-dates-list" aria-live="polite">
            <div class="text-body-secondary small"><?= gettext('Loading...') ?></div>
        </div>
    </div>
</div>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/important-dates.min.js') ?>"></script>
