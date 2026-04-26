<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
$eventId = (int) $event->getId();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$dateFormat = SystemConfig::getValue('sDateTimeFormat');
$startStr = $event->getStart() ? date_format($event->getStart(), $dateFormat) : '';
$endStr = $event->getEnd() ? date_format($event->getEnd(), $dateFormat) : '';
$descText = trim(strip_tags((string) $event->getDesc()));
$bodyText = trim(strip_tags((string) $event->getText()));
$inactive = (int) $event->getInActive() === 1;
?>

<div class="row">
  <div class="col-lg-8">
    <!-- Event details card -->
    <div class="card mb-3">
      <div class="card-status-top <?= $inactive ? 'bg-secondary' : 'bg-green' ?>"></div>
      <div class="card-header">
        <h3 class="card-title"><?= InputUtils::escapeHTML($event->getTitle()) ?></h3>
        <span class="ms-auto">
          <?php if ($inactive): ?>
            <span class="badge bg-secondary-lt"><?= gettext('Inactive') ?></span>
          <?php else: ?>
            <span class="badge bg-green-lt text-green"><?= gettext('Active') ?></span>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-3"><?= gettext('Type') ?></dt>
          <dd class="col-sm-9">
            <?php if ($event->getEventType()): ?>
              <span class="badge bg-azure-lt"><?= InputUtils::escapeHTML($event->getEventType()->getName()) ?></span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </dd>

          <dt class="col-sm-3"><?= gettext('Starts') ?></dt>
          <dd class="col-sm-9"><?= InputUtils::escapeHTML($startStr) ?></dd>

          <dt class="col-sm-3"><?= gettext('Ends') ?></dt>
          <dd class="col-sm-9"><?= InputUtils::escapeHTML($endStr) ?></dd>

          <?php if ($descText !== ''): ?>
            <dt class="col-sm-3"><?= gettext('Description') ?></dt>
            <dd class="col-sm-9"><?= InputUtils::escapeHTML($descText) ?></dd>
          <?php endif; ?>

          <?php if ($bodyText !== ''): ?>
            <dt class="col-sm-3"><?= gettext('Notes') ?></dt>
            <dd class="col-sm-9"><?= InputUtils::escapeHTML($bodyText) ?></dd>
          <?php endif; ?>

          <?php if (!empty($linkedGroups)): ?>
            <dt class="col-sm-3"><?= gettext('Linked Groups') ?></dt>
            <dd class="col-sm-9">
              <?php foreach ($linkedGroups as $g): ?>
                <a href="<?= $sRootPath ?>/groups/view/<?= (int) $g['id'] ?>" class="badge bg-blue-lt text-blue me-1">
                  <?= InputUtils::escapeHTML($g['name']) ?>
                </a>
              <?php endforeach; ?>
            </dd>
          <?php endif; ?>
        </dl>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <a href="<?= $sRootPath ?>/event/dashboard" class="btn btn-outline-secondary">
          <i class="ti ti-chevron-left me-1"></i><?= gettext('Back to Events') ?>
        </a>
        <div class="d-flex gap-2">
          <?php if (!$inactive && !$eventEnded): ?>
            <a href="<?= $sRootPath ?>/event/checkin/<?= $eventId ?>" class="btn btn-outline-primary">
              <i class="ti ti-clipboard-check me-1"></i><?= gettext('Check-in') ?>
            </a>
          <?php endif; ?>
          <?php if ($canEditEvents): ?>
            <a href="<?= $sRootPath ?>/event/editor/<?= $eventId ?>" class="btn btn-primary">
              <i class="ti ti-pencil me-1"></i><?= gettext('Edit') ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Attendance roster -->
    <div class="card <?= $eventEnded && !empty($linkedGroups) ? 'mb-3' : '' ?>">
      <div class="card-header">
        <h3 class="card-title">
          <?= gettext('Attendance') ?>
          <span class="badge bg-primary text-white ms-2"><?= count($attendees) ?></span>
        </h3>
        <?php if ($eventEnded && !empty($linkedGroups)): ?>
          <div class="card-options">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
              <i class="ti ti-printer me-1"></i><?= gettext('Print') ?>
            </button>
          </div>
        <?php endif; ?>
      </div>
      <?php if (empty($attendees)): ?>
        <div class="card-body text-center text-muted py-4">
          <i class="ti ti-users-off fs-3 d-block mb-2"></i>
          <?= gettext('No one has been checked in to this event yet.') ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-vcenter table-hover mb-0">
            <thead>
              <tr>
                <th><?= gettext('Name') ?></th>
                <th><?= gettext('Checked In') ?></th>
                <th><?= gettext('By') ?></th>
                <th><?= gettext('Checked Out') ?></th>
                <th><?= gettext('By') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendees as $att): ?>
                <tr>
                  <td>
                    <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $att['personId'] ?>">
                      <?= InputUtils::escapeHTML($att['fullName']) ?>
                    </a>
                  </td>
                  <td><?= $att['checkinDate'] ? InputUtils::escapeHTML($att['checkinDate']) : '<span class="text-muted">—</span>' ?></td>
                  <td><?= $att['checkinBy'] !== '' ? InputUtils::escapeHTML($att['checkinBy']) : '<span class="text-muted">—</span>' ?></td>
                  <td><?= $att['checkoutDate'] ? InputUtils::escapeHTML($att['checkoutDate']) : '<span class="text-muted">—</span>' ?></td>
                  <td><?= $att['checkoutBy'] !== '' ? InputUtils::escapeHTML($att['checkoutBy']) : '<span class="text-muted">—</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Did Not Attend — shown only after event ends, only when groups are linked -->
    <?php if ($eventEnded && !empty($linkedGroups)): ?>
      <?php
        $nonAttendeeEmails = implode(',', array_filter(array_column($nonAttendees, 'email')));
        $eventTitle = InputUtils::escapeHTML($event->getTitle());
      ?>
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">
            <?= gettext('Did Not Attend') ?>
            <?php if (empty($nonAttendees)): ?>
              <span class="badge bg-green-lt text-green ms-2">0</span>
            <?php else: ?>
              <span class="badge bg-warning text-dark ms-2"><?= count($nonAttendees) ?></span>
            <?php endif; ?>
          </h3>
          <?php if (!empty($nonAttendees)): ?>
            <div class="card-options gap-1">
              <?php if ($emailEnabled && $nonAttendeeEmails !== ''): ?>
                <a href="mailto:<?= htmlspecialchars($nonAttendeeEmails, ENT_QUOTES) ?>?subject=<?= rawurlencode($eventTitle) ?>"
                   class="btn btn-sm btn-outline-primary">
                  <i class="ti ti-mail me-1"></i><?= gettext('Email All') ?>
                </a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php if (empty($nonAttendees)): ?>
          <div class="card-body text-center text-muted py-4">
            <i class="ti ti-circle-check fs-3 d-block mb-2 text-green"></i>
            <?= gettext('Everyone from linked groups checked in!') ?>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-vcenter table-hover mb-0">
              <thead>
                <tr>
                  <th><?= gettext('Name') ?></th>
                  <th><?= gettext('Email') ?></th>
                  <th><?= gettext('Phone') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($nonAttendees as $na): ?>
                  <tr>
                    <td>
                      <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= (int) $na['personId'] ?>">
                        <?= InputUtils::escapeHTML($na['fullName']) ?>
                      </a>
                    </td>
                    <td>
                      <?php if (!empty($na['email'])): ?>
                        <a href="mailto:<?= InputUtils::escapeHTML($na['email']) ?>">
                          <?= InputUtils::escapeHTML($na['email']) ?>
                        </a>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $phone = !empty($na['cellPhone']) ? $na['cellPhone'] : ($na['homePhone'] ?? '');
                      ?>
                      <?= $phone !== '' ? InputUtils::escapeHTML($phone) : '<span class="text-muted">—</span>' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <!-- Attendance counts -->
    <?php if (!empty($counts)): ?>
      <div class="card mb-3">
        <div class="card-header">
          <h3 class="card-title"><?= gettext('Attendance Counts') ?></h3>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-vcenter mb-0">
            <tbody>
              <?php foreach ($counts as $c): ?>
                <tr>
                  <td><?= InputUtils::escapeHTML($c['name']) ?></td>
                  <td class="text-end fw-bold"><?= $c['count'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
