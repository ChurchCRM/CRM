<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\EventAttendQuery;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Propel\Runtime\ActiveQuery\Criteria;

$canEditEvents = AuthenticationManager::getCurrentUser()->isAddEvent();

$eType = 'All';
if (isset($_POST['WhichType']) && $_POST['WhichType'] !== 'All') {
    $eType = InputUtils::filterInt($_POST['WhichType']);
}

// Retrieve the year selector
if (isset($_POST['WhichYear'])) {
    $EventYear = InputUtils::legacyFilterInput($_POST['WhichYear'], 'int');
} else {
    $EventYear = DateTimeUtils::getCurrentYear();
}

// Page header setup
$sPageTitle = gettext('Events Dashboard');
$sPageSubtitle = gettext('Overview of church events, attendance, and activity');

$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Events')],
]);

// Admin header button for Event Types
if ($canEditEvents) {
    $sPageHeaderButtons = PageHeader::buttons([
        [
            'label' => gettext('Manage Event Types'),
            'url' => '/EventNames.php',
            'icon' => 'fa-tags',
            'adminOnly' => false,
        ],
    ]);
}

require_once __DIR__ . '/Include/Header.php';

// Handle delete action
if (isset($_POST['Action']) && isset($_POST['EID']) && $canEditEvents) {
    $eID = InputUtils::legacyFilterInput($_POST['EID'], 'int');
    $action = InputUtils::legacyFilterInput($_POST['Action']);
    if ($action == 'Delete' && $eID) {
        $event = EventQuery::create()->findOneById((int) $eID);
        if ($event !== null) {
            $event->delete();
        }
    } elseif ($action == 'Activate' && $eID) {
        $event = EventQuery::create()->findOneById((int) $eID);
        if ($event !== null) {
            $event->setInActive(0);
            $event->save();
        }
    }
}

// --- Dashboard Stats ---
$yearMin = $EventYear . '-01-01 00:00:00';
$yearMax = $EventYear . '-12-31 23:59:59';

$totalEventsThisYear = EventQuery::create()
    ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
    ->count();

$totalCheckInsThisYear = EventAttendQuery::create()
    ->useEventQuery()
        ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
    ->endUse()
    ->filterByCheckinDate(null, Criteria::ISNOTNULL)
    ->count();

$activeEventsThisYear = EventQuery::create()
    ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
    ->filterByInActive(0)
    ->count();

$totalEventTypes = EventTypeQuery::create()
    ->useEventTypeQuery()
        ->filterByStart(['min' => $yearMin, 'max' => $yearMax])
    ->endUse()
    ->distinct()
    ->count();

// Get event types that have events (using ORM)
$eventTypesWithEvents = EventTypeQuery::create()
    ->useEventTypeQuery()
    ->endUse()
    ->distinct()
    ->orderById()
    ->find();

// Get available years using ORM
$yearQuery = EventQuery::create()
    ->withColumn('YEAR(event_start)', 'EventYear')
    ->select(['EventYear']);
if ($eType !== 'All') {
    $yearQuery->filterByType((int) $eType);
}
$availableYears = $yearQuery
    ->groupBy('EventYear')
    ->orderBy('EventYear', Criteria::DESC)
    ->find()
    ->toArray();

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
            <a href="EventEditor.php" class="btn btn-primary btn-sm">
              <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Event') ?>
            </a>
          <?php endif; ?>
          <a href="event/checkin" class="btn btn-outline-info btn-sm">
            <i class="fa-solid fa-user-check me-1"></i><?= gettext('Check-in') ?>
          </a>
          <a href="v2/calendar" class="btn btn-outline-secondary btn-sm">
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
    <form name="EventFilterForm" method="POST" action="ListEvents.php">
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
            <a href="ListEvents.php" class="btn btn-sm btn-ghost-secondary">
              <i class="ti ti-x me-1"></i><?= gettext('Clear Filter') ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
</div>

<?php

// Get all events for the selected year and type
$allMonths = ['12', '11', '10', '9', '8', '7', '6', '5', '4', '3', '2', '1'];

if ($eType === 'All') {
    $eTypeSQL = '';
} else {
    $eTypeSQL = ' AND t1.event_type=' . (int) $eType;
}

$hasEvents = false;

foreach ($allMonths as $mVal) {
    $sSQL = 'SELECT t1.*, t2.type_name FROM events_event as t1, event_types as t2
             WHERE t1.event_type = t2.type_id' . $eTypeSQL . '
             AND MONTH(t1.event_start) = ' . (int) $mVal . '
             AND YEAR(t1.event_start) = ' . (int) $EventYear . '
             ORDER BY t1.event_start';

    $rsOpps = RunQuery($sSQL);
    $numRows = mysqli_num_rows($rsOpps);

    if ($numRows === 0) {
        continue;
    }

    $hasEvents = true;

    // Collect events for this month
    $events = [];
    while ($row = mysqli_fetch_assoc($rsOpps)) {
        $eventId = (int) $row['event_id'];

        // Get attendance count
        $attendSQL = 'SELECT COUNT(*) as cnt FROM event_attend WHERE event_id=' . $eventId;
        $attendResult = RunQuery($attendSQL);
        $attendRow = mysqli_fetch_assoc($attendResult);
        $attendeeCount = $attendRow ? (int) $attendRow['cnt'] : 0;

        // Get checked in count (people who checked in but haven't checked out)
        $checkedInSQL = 'SELECT COUNT(*) as cnt FROM event_attend WHERE event_id=' . $eventId . ' AND checkout_date IS NULL';
        $checkedInResult = RunQuery($checkedInSQL);
        $checkedInRow = mysqli_fetch_assoc($checkedInResult);
        $checkedInCount = $checkedInRow ? (int) $checkedInRow['cnt'] : 0;

        // Get checked out count (people who have checked out)
        $checkedOutSQL = 'SELECT COUNT(*) as cnt FROM event_attend WHERE event_id=' . $eventId . ' AND checkout_date IS NOT NULL';
        $checkedOutResult = RunQuery($checkedOutSQL);
        $checkedOutRow = mysqli_fetch_assoc($checkedOutResult);
        $checkedOutCount = $checkedOutRow ? (int) $checkedOutRow['cnt'] : 0;

        // Get event counts
        $countSQL = 'SELECT * FROM eventcounts_evtcnt WHERE evtcnt_eventid=' . $eventId . ' ORDER BY evtcnt_countid ASC';
        $countResult = RunQuery($countSQL);
        $eventCounts = [];
        while ($countRow = mysqli_fetch_assoc($countResult)) {
            $eventCounts[] = $countRow;
        }

        $events[] = [
            'id' => $eventId,
            'type_name' => $row['type_name'],
            'title' => InputUtils::sanitizeText($row['event_title']),
            'desc' => InputUtils::sanitizeText($row['event_desc']),
            'text' => $row['event_text'],
            'start' => $row['event_start'],
            'end' => $row['event_end'],
            'inactive' => (int) $row['inactive'],
            'attendee_count' => $attendeeCount,
            'checked_in_count' => $checkedInCount,
            'checked_out_count' => $checkedOutCount,
            'counts' => $eventCounts,
        ];
    }

    $monthName = date('F', mktime(0, 0, 0, (int) $mVal, 1, (int) $EventYear));
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
            <?php $eventId = InputUtils::escapeAttribute($event['id']); ?>
            <tr>
              <td>
                <div class="fw-medium"><?= InputUtils::escapeHTML($event['title']) ?></div>
                <?php if (!empty($event['desc'])): ?>
                  <small class="text-muted"><?= InputUtils::escapeHTML($event['desc']) ?></small>
                <?php endif; ?>
                <?php if (!empty($event['text'])): ?>
                  <br><a href="javascript:popUp('GetText.php?EID=<?= $eventId ?>')" class="text-primary small">
                    <i class="fa-solid fa-file-lines me-1"></i><?= gettext('Sermon Text') ?>
                  </a>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-azure-lt"><?= InputUtils::escapeHTML($event['type_name']) ?></span>
              </td>
              <td class="text-center">
                <a href="event/checkin/<?= $eventId ?>" class="btn btn-sm btn-ghost-secondary" title="<?= gettext('Manage Check-ins') ?>">
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
                      if ((int) $count['evtcnt_countcount'] > 0) {
                          $countParts[] = '<span class="text-muted small">' . InputUtils::escapeHTML($count['evtcnt_countname']) . '</span> ' . (int) $count['evtcnt_countcount'];
                      }
                  }
                  if (!empty($countParts)) {
                      echo implode('<br>', $countParts);
                  } else {
                      echo '<span class="text-muted">—</span>';
                  }
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
                      <a class="dropdown-item" href="event/checkin/<?= $eventId ?>">
                        <i class="ti ti-clipboard-check me-2"></i><?= gettext('Check-in') ?>
                      </a>
                      <a class="dropdown-item" href="EventEditor.php?EID=<?= $eventId ?>">
                        <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                      </a>
                      <?php if ($event['inactive']): ?>
                        <form method="POST" action="ListEvents.php" class="d-inline">
                          <input type="hidden" name="EID" value="<?= $eventId ?>">
                          <input type="hidden" name="WhichType" value="<?= InputUtils::escapeAttribute($eType) ?>">
                          <input type="hidden" name="WhichYear" value="<?= InputUtils::escapeAttribute($EventYear) ?>">
                          <button type="submit" name="Action" value="Activate" class="dropdown-item">
                            <i class="ti ti-circle-check me-2"></i><?= gettext('Activate') ?>
                          </button>
                        </form>
                      <?php endif; ?>
                      <div class="dropdown-divider"></div>
                      <form method="POST" action="ListEvents.php" class="d-inline" onsubmit="return confirm('<?= gettext('Deleting an event will also delete all attendance counts. Delete this event?') ?>')">
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

          <?php
          // Monthly averages when filtering by type
          if ($eType !== 'All' && !empty($events[0]['counts'])):
              $avgSQL = "SELECT evtcnt_countname, AVG(evtcnt_countcount) as avg_count
                        FROM eventcounts_evtcnt, events_event
                        WHERE eventcounts_evtcnt.evtcnt_eventid = events_event.event_id
                        AND events_event.event_type =" . (int) $eType . "
                        AND MONTH(events_event.event_start) =" . (int) $mVal . "
                        AND YEAR(events_event.event_start) =" . (int) $EventYear . "
                        GROUP BY evtcnt_countid ORDER BY evtcnt_countid ASC";
              $avgResult = RunQuery($avgSQL);
              $averages = [];
              while ($avgRow = mysqli_fetch_assoc($avgResult)) {
                  $averages[] = $avgRow;
              }
              if (!empty($averages)):
                  ?>
            <tr class="table-light">
              <td><strong><?= gettext('Monthly Averages') ?></strong></td>
              <td></td>
              <td></td>
              <td>
                <?php
                $avgParts = [];
                foreach ($averages as $avg) {
                    $avgParts[] = '<span class="text-muted small">' . InputUtils::escapeHTML($avg['evtcnt_countname']) . '</span> ' . sprintf('%.1f', $avg['avg_count']);
                }
                echo implode('<br>', $avgParts);
                ?>
              </td>
              <td colspan="2"></td>
              <?php if ($canEditEvents): ?><td></td><?php endif; ?>
            </tr>
              <?php endif; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
    <?php
}

// Empty state when no events found
if (!$hasEvents): ?>
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
      <a href="EventEditor.php" class="btn btn-primary me-2">
        <i class="fa-solid fa-plus me-1"></i><?= gettext('Create First Event') ?>
      </a>
      <a href="RepeatEventEditor.php" class="btn btn-outline-primary">
        <i class="ti ti-repeat me-1"></i><?= gettext('Create Repeat Events') ?>
      </a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/Include/Footer.php';
