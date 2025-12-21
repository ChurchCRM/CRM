<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\EventTypeQuery;
use ChurchCRM\Utils\InputUtils;

$eType = 'All';

if (isset($_POST['WhichType']) && $_POST['WhichType'] !== 'All') {
    $eType = InputUtils::filterInt($_POST['WhichType']);
}

// Get event type name for page title
if ($eType != 'All') {
    $eventType = EventTypeQuery::create()->findOneById((int) $eType);
    $sPageTitle = gettext('Listing Events of Type') . ' = ' . ($eventType ? $eventType->getName() : gettext('Unknown'));
} else {
    $sPageTitle = gettext('Listing All Church Events');
}

// Retrieve the year selector
if (isset($_POST['WhichYear'])) {
    $EventYear = InputUtils::legacyFilterInput($_POST['WhichYear'], 'int');
} else {
    $EventYear = date('Y');
}

require_once __DIR__ . '/Include/Header.php';

// Handle delete action
if (isset($_POST['Action']) && isset($_POST['EID']) && AuthenticationManager::getCurrentUser()->isAddEvent()) {
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

// Get event types that have events
$sSQL = 'SELECT DISTINCT event_types.*
         FROM event_types
         RIGHT JOIN events_event ON event_types.type_id=events_event.event_type
         ORDER BY type_id';
$rsOpps = RunQuery($sSQL);
$eventTypes = [];
while ($row = mysqli_fetch_assoc($rsOpps)) {
    $eventTypes[] = $row;
}

// Get available years for the selected event type
if ($eType === 'All') {
    $sSQL = 'SELECT DISTINCT YEAR(events_event.event_start) as year
           FROM events_event
           ORDER BY year DESC';
} else {
    $sSQL = "SELECT DISTINCT YEAR(events_event.event_start) as year
           FROM events_event
           WHERE events_event.event_type = '" . (int) $eType . "'
           ORDER BY year DESC";
}
$rsOpps = RunQuery($sSQL);
$availableYears = [];
while ($row = mysqli_fetch_assoc($rsOpps)) {
    $availableYears[] = $row['year'];
}

?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><?= gettext('Filter Events') ?></h3>
  </div>
  <div class="card-body">
    <form name="EventFilterForm" method="POST" action="ListEvents.php">
      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="WhichType"><?= gettext('Event Type') ?></label>
            <select name="WhichType" id="WhichType" onchange="this.form.submit()" class="form-control">
              <option value="All"><?= gettext('All Types') ?></option>
              <?php foreach ($eventTypes as $type): ?>
                <option value="<?= InputUtils::escapeAttribute($type['type_id']) ?>" <?= ($type['type_id'] == $eType) ? 'selected' : '' ?>>
                  <?= InputUtils::escapeHTML($type['type_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label for="WhichYear"><?= gettext('Year') ?></label>
            <select name="WhichYear" id="WhichYear" onchange="this.form.submit()" class="form-control">
              <?php foreach ($availableYears as $year): ?>
                <option value="<?= InputUtils::escapeAttribute($year) ?>" <?= ($year == $EventYear) ? 'selected' : '' ?>>
                  <?= InputUtils::escapeHTML($year) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
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

$canEditEvents = AuthenticationManager::getCurrentUser()->isAddEvent();

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

    // Collect events for this month
    $events = [];
    while ($row = mysqli_fetch_assoc($rsOpps)) {
        $eventId = (int) $row['event_id'];

        // Get attendance count
        $attendSQL = 'SELECT COUNT(*) as cnt FROM event_attend WHERE event_id=' . $eventId;
        $attendResult = RunQuery($attendSQL);
        $attendRow = mysqli_fetch_assoc($attendResult);
        $attendeeCount = $attendRow ? (int) $attendRow['cnt'] : 0;

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
            'title' => InputUtils::sanitizeAndEscapeText($row['event_title']),
            'desc' => InputUtils::sanitizeAndEscapeText($row['event_desc']),
            'text' => $row['event_text'],
            'start' => $row['event_start'],
            'end' => $row['event_end'],
            'inactive' => (int) $row['inactive'],
            'attendee_count' => $attendeeCount,
            'counts' => $eventCounts,
        ];
    }

    $monthName = date('F', mktime(0, 0, 0, (int) $mVal, 1, (int) $EventYear));
    ?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title">
      <?= sprintf(ngettext('%d event in %s', '%d events in %s', $numRows), $numRows, gettext($monthName)) ?>
    </h3>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr>
            <?php if ($canEditEvents): ?>
              <th style="width: 100px;"><?= gettext('Actions') ?></th>
            <?php endif; ?>
            <th><?= gettext('Description') ?></th>
            <th><?= gettext('Event Type') ?></th>
            <th style="width: 120px;" class="text-center"><?= gettext('Check-in') ?></th>
            <th><?= gettext('Head Count') ?></th>
            <th><?= gettext('Start Date/Time') ?></th>
            <th style="width: 70px;"><?= gettext('Active') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <?php $eventId = InputUtils::escapeAttribute($event['id']); ?>
            <tr>
              <?php if ($canEditEvents): ?>
                <td>
                  <a href="EventEditor.php?EID=<?= $eventId ?>" class="btn btn-link text-secondary p-1" title="<?= gettext('Edit') ?>">
                    <i class="fas fa-pen"></i>
                  </a>
                  <form method="POST" action="ListEvents.php" class="d-inline" onsubmit="return confirm('<?= gettext('Deleting an event will also delete all attendance counts. Delete this event?') ?>');">
                    <input type="hidden" name="EID" value="<?= $eventId ?>">
                    <button type="submit" name="Action" value="Delete" class="btn btn-link text-danger p-1" title="<?= gettext('Delete') ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </td>
              <?php endif; ?>
              <td>
                <strong><?= InputUtils::escapeHTML($event['title']) ?></strong>
                <?php if (!empty($event['desc'])): ?>
                  <br><small class="text-muted"><?= InputUtils::escapeHTML($event['desc']) ?></small>
                <?php endif; ?>
                <?php if (!empty($event['text'])): ?>
                  <br><a href="javascript:popUp('GetText.php?EID=<?= $eventId ?>')" class="text-primary">
                    <i class="fas fa-file-alt"></i> <?= gettext('Sermon Text') ?>
                  </a>
                <?php endif; ?>
              </td>
              <td><?= InputUtils::escapeHTML($event['type_name']) ?></td>
              <td class="text-center">
                <a href="Checkin.php?EventID=<?= $eventId ?>" class="btn btn-sm btn-outline-info" title="<?= gettext('Manage Check-ins') ?>">
                  <i class="fas fa-clipboard-check mr-1"></i><?= gettext('Check-in') ?>
                  <?php if ($event['attendee_count'] > 0): ?>
                    <span class="badge badge-info ml-1"><?= $event['attendee_count'] ?></span>
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
                      // Only show counts that have a value > 0
                      if ((int) $count['evtcnt_countcount'] > 0) {
                          $countParts[] = InputUtils::escapeHTML($count['evtcnt_countname']) . ': ' . (int) $count['evtcnt_countcount'];
                      }
                  }
                  if (!empty($countParts)) {
                      echo implode(' &nbsp;|&nbsp; ', $countParts);
                  } else {
                      echo '<span class="text-muted">—</span>';
                  }
                  ?>
                <?php endif; ?>
              </td>
              <td><?= FormatDate($event['start'], 1) ?></td>
              <td class="text-center">
                <?php if ($event['inactive']): ?>
                  <span class="badge badge-secondary"><?= gettext('No') ?></span>
                <?php else: ?>
                  <span class="badge badge-success"><?= gettext('Yes') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php
          // Calculate and display monthly averages if filtering by type
          if ($eType !== 'All' && !empty($events[0]['counts'])):
              $avgSQL = "SELECT evtcnt_countname, AVG(evtcnt_countcount) as avg_count
                        FROM eventcounts_evtcnt, events_event
                        WHERE eventcounts_evtcnt.evtcnt_eventid = events_event.event_id
                        AND events_event.event_type = " . (int) $eType . "
                        AND MONTH(events_event.event_start) = " . (int) $mVal . "
                        AND YEAR(events_event.event_start) = " . (int) $EventYear . "
                        GROUP BY evtcnt_countid ORDER BY evtcnt_countid ASC";
              $avgResult = RunQuery($avgSQL);
              $averages = [];
              while ($avgRow = mysqli_fetch_assoc($avgResult)) {
                  $averages[] = $avgRow;
              }
              if (!empty($averages)):
                  ?>
            <tr class="table-secondary">
              <?php if ($canEditEvents): ?><td></td><?php endif; ?>
              <td colspan="3"><strong><?= gettext('Monthly Averages') ?></strong></td>
              <td>
                <?php
                $avgParts = [];
                foreach ($averages as $avg) {
                    $avgParts[] = InputUtils::escapeHTML($avg['evtcnt_countname']) . ': ' . sprintf('%.1f', $avg['avg_count']);
                }
                echo implode(' &nbsp;|&nbsp; ', $avgParts);
                ?>
              </td>
              <td colspan="2"></td>
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
?>

<div class="text-center mt-4 mb-3">
  <a href="EventEditor.php" class="btn btn-primary">
    <i class="fas fa-plus mr-1"></i>
    <?= gettext('Add New') . ' ' . gettext('Event') ?>
  </a>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
