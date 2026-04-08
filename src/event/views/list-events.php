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
            <div class="text-muted"><?= gettext('Events This Year') ?></div>
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
            <div class="text-muted"><?= gettext('Total Check-ins') ?></div>
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
            <div class="fw-medium"><?= (int) $activeEventsThisYear ?></div>
            <div class="text-muted"><?= gettext('Active Events') ?></div>
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
            <div class="text-muted"><?= gettext('Event Types') ?></div>
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
            <a href="<?= $sRootPath ?>/EventEditor.php" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Event') ?>
            </a>
          <?php endif; ?>
          <a href="<?= $sRootPath ?>/event/checkin" class="btn btn-outline-info btn-sm">
            <i class="fa-solid fa-user-check me-1"></i><?= gettext('Check-in') ?>
          </a>
          <a href="<?= $sRootPath ?>/event/calendars" class="btn btn-outline-secondary btn-sm">
            <i class="fa-regular fa-calendar me-1"></i><?= gettext('Calendar') ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form name="EventFilterForm" method="GET" action="<?= $sRootPath ?>/event/dashboard">
      <div class="row align-items-end">
        <div class="col-md-5">
          <label for="WhichType" class="form-label mb-1"><?= gettext('Event Type') ?></label>
          <select name="WhichType" id="WhichType" onchange="this.form.submit()" class="form-select form-select-sm">
            <option value="All"><?= gettext('All Types') ?></option>
            <?php foreach ($eventTypesWithEvents as $type): ?>
              <option value="<?= InputUtils::escapeAttribute($type->getId()) ?>" <?= ($type->getId() == $eType) ? 'selected' : '' ?>>
                <?= InputUtils::escapeHTML($type->getName()) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-5">
          <label for="WhichYear" class="form-label mb-1"><?= gettext('Year') ?></label>
          <select name="WhichYear" id="WhichYear" onchange="this.form.submit()" class="form-select form-select-sm">
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
    $events = $monthData['events'];
    $numRows = $monthData['count'];
    $monthName = $monthData['monthName'];
    $averages = $monthData['averages'];
?>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title mb-0">
      <i class="fa-regular fa-calendar-days me-2 text-muted"></i>
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
            <th style="width: 130px;"><?= gettext('Type') ?></th>
            <th style="width: 120px;" class="text-center"><?= gettext('Attendance') ?></th>
            <th style="width: 100px;"><?= gettext('Head Count') ?></th>
            <th style="width: 140px;"><?= gettext('Date') ?></th>
            <th style="width: 70px;" class="text-center"><?= gettext('Status') ?></th>
            <?php if ($canEditEvents): ?>
              <th style="width: 80px;" class="text-center no-export"><?= gettext('Actions') ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <?php $eventId = (int) $event['id']; ?>
            <tr>
              <td>
                <div class="fw-medium"><?= InputUtils::escapeHTML($event['title']) ?></div>
                <?php if (!empty($event['desc'])): ?>
                  <small class="text-muted"><?= InputUtils::escapeHTML($event['desc']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-azure-lt"><?= InputUtils::escapeHTML($event['type_name']) ?></span>
              </td>
              <td class="text-center">
                <a href="<?= $sRootPath ?>/event/checkin/<?= $eventId ?>" class="btn btn-sm btn-ghost-secondary" title="<?= gettext('Manage Check-ins') ?>">
                  <i class="fa-solid fa-clipboard-check me-1"></i>
                  <?php if ($event['attendee_count'] > 0): ?>
                    <span class="badge bg-info"><?= $event['attendee_count'] ?></span>
                  <?php else: ?>
                    <span class="text-muted">0</span>
                  <?php endif; ?>
                </a>
              </td>
              <td>
                <?php if (empty($event['counts'])): ?>
                  <span class="text-muted">—</span>
                <?php else: ?>
                  <?php
                  $countParts = [];
                  foreach ($event['counts'] as $count) {
                      if ($count['count'] > 0) {
                          $countParts[] = '<span class="text-muted small">' . InputUtils::escapeHTML($count['name']) . '</span> ' . $count['count'];
                      }
                  }
                  echo !empty($countParts) ? implode('<br>', $countParts) : '<span class="text-muted">—</span>';
                  ?>
                <?php endif; ?>
              </td>
              <td>
                <span class="small"><?= DateTimeUtils::formatDate($event['start'], 1) ?></span>
              </td>
              <td class="text-center">
                <?php if ($event['inactive']): ?>
                  <span class="badge bg-secondary-lt"><?= gettext('Inactive') ?></span>
                <?php else: ?>
                  <span class="badge bg-green-lt text-green"><?= gettext('Active') ?></span>
                <?php endif; ?>
              </td>
              <?php if ($canEditEvents): ?>
                <td class="text-center">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                      <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                      <a class="dropdown-item" href="<?= $sRootPath ?>/event/checkin/<?= $eventId ?>">
                        <i class="ti ti-clipboard-check me-2"></i><?= gettext('Check-in') ?>
                      </a>
                      <a class="dropdown-item" href="<?= $sRootPath ?>/EventEditor.php?EID=<?= $eventId ?>">
                        <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                      </a>
                      <?php if ($event['inactive']): ?>
                        <form method="POST" action="<?= $sRootPath ?>/event/dashboard" class="d-inline">
                          <input type="hidden" name="EID" value="<?= $eventId ?>">
                          <input type="hidden" name="WhichType" value="<?= InputUtils::escapeAttribute($eType) ?>">
                          <input type="hidden" name="WhichYear" value="<?= InputUtils::escapeAttribute($EventYear) ?>">
                          <button type="submit" name="Action" value="Activate" class="dropdown-item">
                            <i class="ti ti-circle-check me-2"></i><?= gettext('Activate') ?>
                          </button>
                        </form>
                      <?php endif; ?>
                      <div class="dropdown-divider"></div>
                      <form method="POST" action="<?= $sRootPath ?>/event/dashboard" class="d-inline" onsubmit="return confirm('<?= gettext('Deleting an event will also delete all attendance counts. Delete this event?') ?>')">
                        <input type="hidden" name="EID" value="<?= $eventId ?>">
                        <input type="hidden" name="WhichType" value="<?= InputUtils::escapeAttribute($eType) ?>">
                        <input type="hidden" name="WhichYear" value="<?= InputUtils::escapeAttribute($EventYear) ?>">
                        <button type="submit" name="Action" value="Delete" class="dropdown-item text-danger">
                          <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>

          <?php if (!empty($averages)): ?>
            <tr class="table-light">
              <td><strong><?= gettext('Monthly Averages') ?></strong></td>
              <td></td>
              <td></td>
              <td>
                <?php
                $avgParts = [];
                foreach ($averages as $avg) {
                    $avgParts[] = '<span class="text-muted small">' . InputUtils::escapeHTML($avg['name']) . '</span> ' . sprintf('%.1f', $avg['avg_count']);
                }
                echo implode('<br>', $avgParts);
                ?>
              </td>
              <td colspan="2"></td>
              <?php if ($canEditEvents): ?><td></td><?php endif; ?>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endforeach; ?>

<?php if (!$hasEvents): ?>
<div class="card">
  <div class="card-body text-center py-5">
    <div class="mb-3">
      <i class="fa-solid fa-calendar-xmark fa-3x text-muted"></i>
    </div>
    <h3 class="text-muted"><?= gettext('No Events Found') ?></h3>
    <p class="text-muted mb-3">
      <?= sprintf(gettext('No events found for %s.'), (int) $EventYear) ?>
      <?php if ($eType !== 'All'): ?>
        <?= gettext('Try selecting a different event type or year.') ?>
      <?php endif; ?>
    </p>
    <?php if ($canEditEvents): ?>
      <a href="<?= $sRootPath ?>/EventEditor.php" class="btn btn-primary me-2">
        <i class="fa-solid fa-plus me-1"></i><?= gettext('Create First Event') ?>
      </a>
      <a href="<?= $sRootPath ?>/event/repeat-editor" class="btn btn-outline-primary">
        <i class="ti ti-repeat me-1"></i><?= gettext('Create Repeat Events') ?>
      </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
