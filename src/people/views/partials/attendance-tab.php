<?php
/**
 * Attendance History tab pane partial.
 *
 * Included from person-view.php.
 * Requires: $iPersonID (int), $person object
 *
 * All data is fetched lazily on first tab activation via `attendance-history.ts`.
 * The i18n strings are injected as a JSON data attribute (same pattern as
 * personMapConfig) so no inline <script> block is needed.
 */

use ChurchCRM\Utils\InputUtils;

$i18n = [
    'loading'          => gettext('Loading attendance history…'),
    'errorLoading'     => gettext('Failed to load attendance history.'),
    'noRecords'        => gettext('No attendance records found.'),
    'filterByType'     => gettext('Filter by event type'),
    'allTypes'         => gettext('All types'),
    'dateFrom'         => gettext('From'),
    'dateTo'           => gettext('To'),
    'clearFilters'     => gettext('Clear'),
    'colEvent'         => gettext('Event'),
    'colType'          => gettext('Type'),
    'colDate'          => gettext('Date'),
    'colCheckin'       => gettext('Check-in'),
    'colCheckout'      => gettext('Check-out'),
    'inactive'         => gettext('Inactive'),
    'totalEvents'      => gettext('Total Events'),
    'lastAttendance'   => gettext('Last Attendance'),
    'currentStreak'    => gettext('Streak'),
    'streakEvents'     => gettext('events'),
    'never'            => gettext('Never'),
    'noStreak'         => '—',
];
?>

<div id="attendance-tab"
     data-person-id="<?= InputUtils::escapeHTML((string) $iPersonID) ?>"
     data-api-root="<?= InputUtils::escapeHTML(\ChurchCRM\dto\SystemURLs::getRootPath()) ?>"
     data-i18n="<?= htmlspecialchars(json_encode($i18n, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>">

    <!-- Loading state -->
    <div class="attendance-loading text-center py-4">
        <div class="spinner-border spinner-border-sm text-secondary me-2" role="status" aria-hidden="true"></div>
        <span class="text-body-secondary"><?= gettext('Loading attendance history…') ?></span>
    </div>

    <!-- Summary stats (hidden until data loads) -->
    <div class="attendance-summary d-none mb-3">
        <div class="row g-2">
            <div class="col-sm-4">
                <div class="card card-sm bg-blue-lt">
                    <div class="card-body p-2 text-center">
                        <div class="h4 mb-0 attendance-stat-total">—</div>
                        <div class="small text-body-secondary"><?= gettext('Total Events') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card card-sm bg-teal-lt">
                    <div class="card-body p-2 text-center">
                        <div class="h4 mb-0 attendance-stat-last">—</div>
                        <div class="small text-body-secondary"><?= gettext('Last Attendance') ?></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card card-sm bg-orange-lt">
                    <div class="card-body p-2 text-center">
                        <div class="h4 mb-0 attendance-stat-streak">—</div>
                        <div class="small text-body-secondary"><?= gettext('Best Streak') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter controls (hidden until data loads) -->
    <div class="attendance-filters d-none mb-2">
        <div class="row g-2 align-items-end">
            <div class="col-sm-4">
                <label class="form-label form-label-sm mb-1"><?= gettext('Event Type') ?></label>
                <select class="form-select form-select-sm attendance-filter-type" aria-label="<?= gettext('Filter by event type') ?>">
                    <option value=""><?= gettext('All types') ?></option>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label form-label-sm mb-1"><?= gettext('From') ?></label>
                <input type="date" class="form-control form-control-sm attendance-filter-from" aria-label="<?= gettext('From date') ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label form-label-sm mb-1"><?= gettext('To') ?></label>
                <input type="date" class="form-control form-control-sm attendance-filter-to" aria-label="<?= gettext('To date') ?>">
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 attendance-filter-clear">
                    <?= gettext('Clear') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Attendance table (hidden until data loads) -->
    <div class="attendance-table-wrapper d-none">
        <div class="table-responsive">
            <table class="table table-sm table-hover table-vcenter">
                <thead>
                    <tr>
                        <th><?= gettext('Event') ?></th>
                        <th><?= gettext('Type') ?></th>
                        <th><?= gettext('Date') ?></th>
                        <th><?= gettext('Check-in') ?></th>
                        <th><?= gettext('Check-out') ?></th>
                    </tr>
                </thead>
                <tbody class="attendance-tbody"></tbody>
            </table>
        </div>
        <div class="attendance-empty text-center text-body-secondary py-3 d-none">
            <?= gettext('No attendance records found.') ?>
        </div>
    </div>

    <!-- Error state (hidden until error) -->
    <div class="attendance-error d-none">
        <div class="alert alert-danger" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <?= gettext('Failed to load attendance history.') ?>
        </div>
    </div>

</div>
