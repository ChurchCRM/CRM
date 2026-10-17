<?php

/**
 * Admin → Ministry Settings — the one home of the Volunteer Management settings
 * (product-owner decision, 2026-09-18; the Member Portal admin page is the
 * precedent). Two cards: the settings panel and the health of the background
 * jobs — the daily schedule top-up (D31) and the notification outbox.
 *
 * Markup only: the settings panel (`window.CRM.settingsPanel`, U8) renders and
 * saves the five ConfigItems through POST /admin/api/system/config/{name}.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/** @var string $sRootPath */
/** @var int $iLeadHours */
/** @var int $iHorizonWeeks */
/** @var int $iMinHorizonWeeks */
/** @var int $iMaxHorizonWeeks */
/** @var array<int, array{value: string, label: string}> $aEventTypeChoices */
/** @var array{ranAt: string, schedules: int, created: int, failed: int, assigned: int, skipped: int, unqualified: int}|null $aLastTopUp */
/** @var int $iFailedCount */
/** @var int $iPendingCount */
/** @var array<int, array{type: string, person: string, lastAttempt: string, error: string}> $aRecentFailures */
/** @var string $sLastTimerJobsRun */
/** @var string $sTimerJobsHint */

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="ministry-settings">
  <div class="row">
    <div class="col-12 col-xl-7">
      <!-- The Settings Panel component renders and saves these five ConfigItems. -->
      <div id="ministrySettingsPanel"></div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card mb-3" id="ministry-delivery-card">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-envelope-circle-check me-2"></i><?= gettext('Background jobs and delivery') ?>
          </h3>
        </div>
        <div class="card-body">
          <div class="datagrid mb-4">
            <div class="datagrid-item">
              <div class="datagrid-title"><?= gettext('Delivery') ?></div>
              <div class="datagrid-content">
                <span class="badge <?= $iFailedCount > 0 ? 'bg-red-lt text-red' : 'bg-green-lt text-green' ?> me-1" id="ministry-failed-count"><?= (int) $iFailedCount ?></span>
                <?= $iFailedCount > 0
                    ? gettext('messages could not be delivered after five attempts')
                    : gettext('every message has been delivered or is still queued') ?>
              </div>
            </div>
            <div class="datagrid-item">
              <div class="datagrid-title"><?= gettext('Queue') ?></div>
              <div class="datagrid-content">
                <span class="badge bg-secondary-lt me-1" id="ministry-pending-count"><?= (int) $iPendingCount ?></span>
                <?= gettext('messages waiting to be sent') ?>
              </div>
            </div>
            <div class="datagrid-item">
              <div class="datagrid-title"><i class="fa-solid fa-clock-rotate-left me-1"></i><?= gettext('Background jobs last ran') ?></div>
              <div class="datagrid-content" id="ministry-last-run"><?php if ($sLastTimerJobsRun === ''): ?><?= gettext('never') ?><?php else: ?><time datetime="<?= InputUtils::escapeAttribute($sLastTimerJobsRun) ?>" data-format="datetime"><?= InputUtils::escapeHTML($sLastTimerJobsRun) ?></time><?php endif; ?></div>
            </div>
            <div class="datagrid-item" id="ministry-topup">
              <div class="datagrid-title"><i class="fa-solid fa-calendar-plus me-1"></i><?= gettext('Schedules last topped up') ?></div>
              <div class="datagrid-content">
                <span id="ministry-topup-last-run"><?php if ($aLastTopUp === null): ?><?= gettext('never') ?><?php else: ?><time datetime="<?= InputUtils::escapeAttribute($aLastTopUp['ranAt']) ?>" data-format="datetime"><?= InputUtils::escapeHTML($aLastTopUp['ranAt']) ?></time><?php endif; ?></span>
                <?php if ($aLastTopUp !== null): ?>
                  <span class="text-body-secondary" id="ministry-topup-created">— <?= InputUtils::escapeHTML(sprintf(
                      ngettext('%d new occurrence', '%d new occurrences', $aLastTopUp['created']),
                      $aLastTopUp['created']
                  )) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php if ($aLastTopUp !== null && $aLastTopUp['assigned'] + $aLastTopUp['unqualified'] + $aLastTopUp['skipped'] > 0): ?>
            <ul class="list-unstyled small mb-4" id="ministry-topup-defaults">
              <li id="ministry-topup-assigned"><?= InputUtils::escapeHTML(sprintf(
                  ngettext('%d default volunteer assigned', '%d default volunteers assigned', $aLastTopUp['assigned']),
                  $aLastTopUp['assigned']
              )) ?></li>
              <?php if ($aLastTopUp['unqualified'] > 0): ?>
                <li class="text-warning" id="ministry-topup-unqualified">
                  <i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i><?= InputUtils::escapeHTML(sprintf(
                      ngettext('%d default skipped: qualification revoked', '%d defaults skipped: qualification revoked', $aLastTopUp['unqualified']),
                      $aLastTopUp['unqualified']
                  )) ?>
                </li>
              <?php endif; ?>
              <?php if ($aLastTopUp['skipped'] > 0): ?>
                <li class="text-body-secondary" id="ministry-topup-skipped"><?= InputUtils::escapeHTML(sprintf(
                    ngettext('%d default skipped for another reason (the occurrence is over, cancelled or full)', '%d defaults skipped for another reason (the occurrence is over, cancelled or full)', $aLastTopUp['skipped']),
                    $aLastTopUp['skipped']
                )) ?></li>
              <?php endif; ?>
            </ul>
          <?php endif; ?>
          <div class="border-top pt-3 mb-3">
            <p class="text-body-secondary mb-3" id="ministry-topup-hint">
              <?= InputUtils::escapeHTML(sprintf(
                  ngettext(
                      'Once a day every active schedule gets occurrences for the events it follows, up to %d week ahead, and each position\'s default volunteer is assigned on the new ones while they stay qualified.',
                      'Once a day every active schedule gets occurrences for the events it follows, up to %d weeks ahead, and each position\'s default volunteer is assigned on the new ones while they stay qualified.',
                      $iHorizonWeeks
                  ),
                  $iHorizonWeeks
              )) ?>
            </p>
            <button type="button" class="btn btn-outline-primary" id="ministry-run-jobs-btn">
              <i class="fa-solid fa-play me-1"></i><?= gettext('Run background jobs now') ?>
            </button>
            <div class="form-text mt-2"><?= gettext('Tops up every schedule, sends whatever is queued and closes out finished occurrences, without waiting for the next scheduled run.') ?></div>
          </div>
          <div class="border-top pt-3 text-body-secondary" id="ministry-cron-hint">
            <i class="fa-solid fa-clock me-1"></i>
            <?= gettext('Reminders and alerts are only delivered when background jobs run. For on-time delivery, run the scheduled-task runner from cron every 15 minutes') ?> —
            <code><?= InputUtils::escapeHTML($sTimerJobsHint) ?></code>
          </div>
          <?php if ($aRecentFailures !== []): ?>
            <div class="table-responsive mt-4">
              <table class="table table-sm table-vcenter mb-0" id="ministry-failed-table">
                <thead>
                  <tr>
                    <th><?= gettext('Message') ?></th>
                    <th><?= gettext('Recipient') ?></th>
                    <th><?= gettext('Last attempt') ?></th>
                    <th><?= gettext('Error') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($aRecentFailures as $failure): ?>
                    <tr>
                      <td><?= InputUtils::escapeHTML($failure['type']) ?></td>
                      <td><?= InputUtils::escapeHTML($failure['person']) ?></td>
                      <td><time datetime="<?= InputUtils::escapeAttribute($failure['lastAttempt']) ?>" data-format="datetime"><?= InputUtils::escapeHTML($failure['lastAttempt']) ?></time></td>
                      <td class="text-body-secondary small"><?= InputUtils::escapeHTML($failure['error']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/ministries-settings.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    window.CRM.settingsPanel.init({
        container: '#ministrySettingsPanel',
        title: <?= InputUtils::jsonEncodeForScript(gettext('Settings')) ?>,
        icon: 'fa-solid fa-sliders',
        headerClass: 'bg-info-lt',
        // These items deliberately carry no System Settings category, so a link
        // to that page would be a dead end.
        showAllSettingsLink: false,
        columns: 2,
        settings: [
            {
                name: 'iVolunteerReminderLeadHours',
                type: 'number',
                min: 0,
                max: 720,
                label: <?= InputUtils::jsonEncodeForScript(gettext('Reminder lead time (hours)')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('How many hours before an occurrence the reminder email is sent. Set to 0 to send no reminders at all.')) ?>
            },
            {
                name: 'iVolunteerGapAlertLeadHours',
                type: 'number',
                min: 0,
                max: 720,
                label: <?= InputUtils::jsonEncodeForScript(gettext('Unfilled-position alert lead time (hours)')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('How many hours before an occurrence its coordinators are told about positions still unfilled. Set to 0 to send no such alerts; reminders are not affected.')) ?>
            },
            {
                name: 'sVolunteerGapAlertBatchTime',
                type: 'time',
                label: <?= InputUtils::jsonEncodeForScript(gettext('Daily unfilled-position email time')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('Once a day at this time (church time zone), coordinators get one email listing the unfilled positions that start within the alert lead time plus 24 hours. A gap that opens later in the day is sent on its own.')) ?>
            },
            {
                name: 'iVolunteerSchedulingHorizonWeeks',
                type: 'number',
                min: <?= (int) $iMinHorizonWeeks ?>,
                max: <?= (int) $iMaxHorizonWeeks ?>,
                label: <?= InputUtils::jsonEncodeForScript(gettext('Scheduling horizon (weeks)')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('How far ahead occurrences are created for the events each schedule follows. A daily background job keeps every schedule filled up to this point.')) ?>
            },
            {
                name: 'iVolunteerDefaultEventTypeId',
                type: 'choice',
                label: <?= InputUtils::jsonEncodeForScript(gettext('Default event type for ministry events')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('The type a new event on a ministry\'s Calendar tab starts with. It can still be changed for each event, for example for a class that needs headcount categories.')) ?>,
                choices: <?= InputUtils::jsonEncodeForScript($aEventTypeChoices) ?>
            }
        ],
        onSave: function () {
            // The sidebar, the header button and the person record all follow the
            // rollout state server-side, so a saved change needs a fresh page.
            setTimeout(function () { window.location.reload(); }, 1500);
        }
    });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
