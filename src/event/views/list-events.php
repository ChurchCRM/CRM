<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<!-- Stat Cards -->
<div class="row mb-3">
  <div class="col-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-primary text-white avatar rounded-circle">
              <i class="fa-solid fa-calendar-days icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= (int) $totalEventsThisYear ?></div>
            <div class="text-body-secondary"><?= gettext('Events This Year') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-success text-white avatar rounded-circle">
              <i class="fa-solid fa-clipboard-check icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= (int) $totalCheckInsThisYear ?></div>
            <div class="text-body-secondary"><?= gettext('Total Check-ins') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-info text-white avatar rounded-circle">
              <i class="fa-solid fa-circle-check icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium">
              <?= (int) $totalCurrentEvents ?>
              <?php if ($totalPastEvents > 0): ?>
                <small class="text-body-secondary">/ <?= (int) $totalPastEvents ?> <?= gettext('past') ?></small>
              <?php endif; ?>
            </div>
            <div class="text-body-secondary"><?= gettext('Current Events') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card card-sm">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <span class="bg-warning text-white avatar rounded-circle">
              <i class="fa-solid fa-tags icon"></i>
            </span>
          </div>
          <div class="col">
            <div class="fw-medium"><?= (int) $totalEventTypes ?></div>
            <div class="text-body-secondary"><?= gettext('Event Types') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row mb-3">
  <div class="col-12">
    <div class="card">
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
          <?php if ($canEditEvents): ?>
            <a href="<?= $sRootPath ?>/event/editor" class="btn btn-primary btn-sm">
              <i class="ti ti-plus me-1"></i><?= gettext('Add Event') ?>
            </a>
            <a href="<?= $sRootPath ?>/event/repeat-editor" class="btn btn-outline-primary btn-sm">
              <i class="ti ti-repeat me-1"></i><?= gettext('Add Recurring Event') ?>
            </a>
          <?php endif; ?>
          <a href="<?= $sRootPath ?>/event/checkin" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-user-check me-1"></i><?= gettext('Check-in') ?>
          </a>
          <a href="<?= $sRootPath ?>/event/calendars" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-calendar me-1"></i><?= gettext('Calendar') ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form id="eventFilterForm" name="EventFilterForm" method="GET" action="<?= $sRootPath ?>/event/dashboard">
      <div class="row align-items-end">
        <div class="col-md-5">
          <label for="type" class="form-label mb-1"><?= gettext('Event Type') ?></label>
          <select name="type" id="type" class="form-select form-select-sm">
            <option value="All"><?= gettext('All Types') ?></option>
            <?php foreach ($eventTypesWithEvents as $type): ?>
              <option value="<?= InputUtils::escapeAttribute($type->getId()) ?>" <?= ($type->getId() == $eType) ? 'selected' : '' ?>>
                <?= InputUtils::escapeHTML($type->getName()) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label for="year" class="form-label mb-1"><?= gettext('Year') ?></label>
          <select name="year" id="year" class="form-select form-select-sm">
            <?php foreach ($availableYears as $year): ?>
              <option value="<?= InputUtils::escapeAttribute($year) ?>" <?= ($year == $EventYear) ? 'selected' : '' ?>>
                <?= InputUtils::escapeHTML($year) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 text-end">
          <?php if ($eType !== 'All'): ?>
            <a href="<?= $sRootPath ?>/event/dashboard" class="btn btn-sm btn-ghost-secondary">
              <i class="ti ti-x me-1"></i><?= gettext('Clear Filter') ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</div>

<?php
$hasEvents = !empty($monthlyData);

foreach ($monthlyData as $monthData):
    $currentEvents = $monthData['currentEvents'];
    $pastEvents    = $monthData['pastEvents'];
    $currentCount  = $monthData['currentCount'];
    $pastCount     = $monthData['pastCount'];
    $numRows       = $monthData['count'];
    $monthName     = $monthData['monthName'];
    $averages      = $monthData['averages'];
    $monthNum      = (int) $monthData['month'];

    // Auto-expand the past section when the month has NO current events
    // (e.g., filtering a past year or a future-only month).
    $autoExpand = ($currentCount === 0 && $pastCount > 0);

    // Collapse element ID includes year so localStorage entries don't bleed
    // across years (e.g. March 2025 vs March 2026).
    $collapseId = 'past-events-' . $EventYear . '-month-' . $monthNum;

    // Column span — 7 when the edit actions column is present, 6 otherwise.
    $colSpan = $canEditEvents ? 7 : 6;
?>
<div class="card mb-3" id="month-<?= $monthNum ?>">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">
      <i class="ti ti-calendar me-2 text-body-secondary"></i>
      <?= sprintf(ngettext('%d event in %s', '%d events in %s', $numRows), $numRows, gettext($monthName)) ?>
    </h3>
    <span class="badge bg-blue-lt ms-auto"><?= (int) $EventYear ?></span>
  </div>
  <div class="card-body p-0" style="overflow: visible;">
    <div style="overflow: visible;">
      <table class="table table-hover table-vcenter mb-0">
        <thead>
          <tr>
            <th><?= gettext('Event') ?></th>
            <th><?= gettext('Type') ?></th>
            <th class="text-center"><?= gettext('Attendance') ?></th>
            <th><?= gettext('Head Count') ?></th>
            <th><?= gettext('Date') ?></th>
            <th class="text-center"><?= gettext('Status') ?></th>
            <?php if ($canEditEvents): ?>
              <th class="text-center no-export"><?= gettext('Actions') ?></th>
            <?php endif; ?>
          </tr>
        </thead>

        <?php if (!empty($currentEvents)): ?>
        <!-- ============ CURRENT EVENTS ============ -->
        <tbody>
          <?php foreach ($currentEvents as $event): ?>
            <?php include __DIR__ . '/partials/event-row.php'; ?>
          <?php endforeach; ?>
        </tbody>
        <?php endif; ?>

        <?php if (!empty($pastEvents)): ?>
        <!-- ============ PAST EVENTS (collapsible) ============ -->
        <tbody class="past-events-header-tbody">
          <tr>
            <td colspan="<?= $colSpan ?>" class="bg-body-tertiary p-0">
              <button
                class="btn btn-sm btn-ghost-secondary w-100 text-start rounded-0 py-2 px-3"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?= $collapseId ?>"
                aria-expanded="<?= $autoExpand ? 'true' : 'false' ?>"
                aria-controls="<?= $collapseId ?>"
              >
                <i class="ti ti-chevron-right me-1 past-events-chevron"></i>
                <i class="ti ti-archive me-1 text-body-secondary"></i>
                <?= sprintf(
                    ngettext('%d past event', '%d past events', $pastCount),
                    $pastCount
                ) ?>
              </button>
            </td>
          </tr>
        </tbody>

        <tbody id="<?= $collapseId ?>" class="collapse<?= $autoExpand ? ' show' : '' ?>">
          <?php foreach ($pastEvents as $event): ?>
            <?php include __DIR__ . '/partials/event-row.php'; ?>
          <?php endforeach; ?>
        </tbody>

        <?php if (!empty($averages)): ?>
        <!-- Monthly Averages always visible, outside the collapsible past tbody -->
        <tbody>
          <tr class="table-light">
            <td><strong><?= gettext('Monthly Averages') ?></strong></td>
            <td></td>
            <td></td>
            <td>
              <?php
              $avgParts = [];
              foreach ($averages as $avg) {
                  $avgParts[] = '<span class="text-body-secondary small">' . InputUtils::escapeHTML($avg['name']) . '</span> ' . sprintf('%.1f', $avg['avg_count']);
              }
              echo implode('<br>', $avgParts);
              ?>
            </td>
            <td colspan="2"></td>
            <?php if ($canEditEvents): ?><td></td><?php endif; ?>
          </tr>
        </tbody>
        <?php endif; ?>
        <?php else: ?>
        <!-- No past events — Monthly Averages (if any) go in the current tbody -->
        <?php if (!empty($averages)): ?>
        <tbody>
          <tr class="table-light">
            <td><strong><?= gettext('Monthly Averages') ?></strong></td>
            <td></td>
            <td></td>
            <td>
              <?php
              $avgParts = [];
              foreach ($averages as $avg) {
                  $avgParts[] = '<span class="text-body-secondary small">' . InputUtils::escapeHTML($avg['name']) . '</span> ' . sprintf('%.1f', $avg['avg_count']);
              }
              echo implode('<br>', $avgParts);
              ?>
            </td>
            <td colspan="2"></td>
            <?php if ($canEditEvents): ?><td></td><?php endif; ?>
          </tr>
        </tbody>
        <?php endif; ?>
        <?php endif; ?>

      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if ($hasEvents && $EventYear === (int) date('Y')): ?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  document.addEventListener('DOMContentLoaded', function () {
    var m = document.getElementById('month-<?= (int) date('n') ?>');
    if (m) m.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
</script>
<?php endif; ?>

<?php if (!$hasEvents): ?>
<div class="card">
  <div class="card-body text-center py-5">
    <div class="mb-3">
      <i class="ti ti-calendar-off text-body-secondary" style="font-size: 3rem;"></i>
    </div>
    <h3 class="text-body-secondary"><?= gettext('No Events Found') ?></h3>
    <p class="text-body-secondary mb-3">
      <?= sprintf(gettext('No events found for %s.'), (int) $EventYear) ?>
      <?php if ($eType !== 'All'): ?>
        <?= gettext('Try selecting a different event type or year.') ?>
      <?php endif; ?>
    </p>
    <?php if ($canEditEvents): ?>
      <a href="<?= $sRootPath ?>/event/editor" class="btn btn-primary me-2">
        <i class="ti ti-plus me-1"></i><?= gettext('Create First Event') ?>
      </a>
      <a href="<?= $sRootPath ?>/event/repeat-editor" class="btn btn-outline-primary">
        <i class="ti ti-repeat me-1"></i><?= gettext('Create Repeat Events') ?>
      </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  // Auto-submit the filter form when the user picks a type or year.
  // (Replaces the previous inline onchange="this.form.submit()" which CSP blocks.)
  document.querySelectorAll('#eventFilterForm select').forEach(function (sel) {
    sel.addEventListener('change', function () {
      document.getElementById('eventFilterForm').submit();
    });
  });

  // Hydrate the placeholder cells with the standard event action menu.
  // The renderer + global click handlers live in src/skin/js/CRMJSOM.js.
  (function hydrateEventActionMenus() {
    function render() {
      var nodes = document.querySelectorAll('.event-action-menu-placeholder');
      nodes.forEach(function (node) {
        var id = parseInt(node.dataset.eventId, 10);
        var title = node.dataset.eventTitle || '';
        var inactive = node.dataset.eventInactive === '1';
        node.innerHTML = window.CRM.renderEventActionMenu(id, title, { inactive: inactive });
      });
    }
    if (window.CRM && window.CRM.localesLoaded) {
      render();
    } else {
      window.addEventListener('CRM.localesReady', render, { once: true });
    }
  })();

  // ---------------------------------------------------------------------------
  // Past Events collapse — localStorage persistence + chevron rotation
  // ---------------------------------------------------------------------------
  (function initPastEventsCollapse() {
    var KEY = 'churchcrm.eventDashboard.pastOpen';

    function loadOpenMonths() {
      try {
        var parsed = JSON.parse(localStorage.getItem(KEY) || '[]');
        return Array.isArray(parsed) ? parsed : [];
      } catch {
        return [];
      }
    }

    function saveOpenMonths(arr) {
      try {
        localStorage.setItem(KEY, JSON.stringify(arr));
      } catch {
        // quota exceeded or storage blocked — fail silently
      }
    }

    var open = new Set(loadOpenMonths());

    // Apply persisted expanded state (skip months already auto-expanded in PHP).
    document.querySelectorAll('[id^="past-events-"]').forEach(function (el) {
      if (open.has(el.id) && !el.classList.contains('show')) {
        el.classList.add('show');
        var btn = document.querySelector('[data-bs-target="#' + el.id + '"]');
        if (btn) btn.setAttribute('aria-expanded', 'true');
      }
    });

    // Sync chevron rotation with Bootstrap collapse state.
    function updateChevron(collapseEl, expanded) {
      var btn = document.querySelector('[data-bs-target="#' + collapseEl.id + '"]');
      if (!btn) return;
      var chevron = btn.querySelector('.past-events-chevron');
      if (chevron) {
        if (expanded) {
          chevron.classList.add('rotate-90');
        } else {
          chevron.classList.remove('rotate-90');
        }
      }
    }

    // Set initial chevron state based on current show/hide.
    document.querySelectorAll('[id^="past-events-"]').forEach(function (el) {
      updateChevron(el, el.classList.contains('show'));
    });

    // Track changes and persist to localStorage.
    document.querySelectorAll('[id^="past-events-"]').forEach(function (el) {
      el.addEventListener('shown.bs.collapse', function () {
        open.add(el.id);
        persist();
        updateChevron(el, true);
      });
      el.addEventListener('hidden.bs.collapse', function () {
        open.delete(el.id);
        persist();
        updateChevron(el, false);
      });
    });

    function persist() {
      saveOpenMonths(Array.from(open));
    }
  })();
</script>

<style>
  /* Rotate chevron when the past-events collapse is open */
  .past-events-chevron {
    display: inline-block;
    transition: transform 0.2s ease;
  }
  .past-events-chevron.rotate-90 {
    transform: rotate(90deg);
  }
  /* Bootstrap 5 sets .collapse.show { display: block } which breaks <tbody>
     layout in Firefox and Safari. Override to restore the correct table
     display value. */
  tbody.collapse.show {
    display: table-row-group !important;
  }
</style>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
