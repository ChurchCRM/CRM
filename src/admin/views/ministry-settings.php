<?php

/**
 * Admin → Ministry Settings — the one home of the Volunteer Management settings
 * (product-owner decision, 2026-09-18; the Member Portal admin page is the
 * precedent). Three cards: the settings panel, an explanation of what the
 * rollout choices mean, and delivery health for the notification outbox.
 *
 * Markup only: the settings panel (`window.CRM.settingsPanel`, U8) renders and
 * saves the two ConfigItems through POST /admin/api/system/config/{name}.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/** @var string $sRootPath */
/** @var string $sVersion */
/** @var bool $bV2Enabled */
/** @var int $iLeadHours */
/** @var int $iFailedCount */
/** @var int $iPendingCount */
/** @var array<int, array{type: string, person: string, lastAttempt: string, error: string}> $aRecentFailures */
/** @var string $sLastTimerJobsRun */
/** @var string $sTimerJobsHint */

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div id="ministry-settings">
  <div class="row">
    <div class="col-12 col-xl-6">

      <!-- The Settings Panel component renders and saves these two ConfigItems. -->
      <div id="ministrySettingsPanel"></div>

      <div class="card mb-3" id="ministry-delivery-card">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-envelope-circle-check me-2"></i><?= gettext('Notification delivery') ?>
          </h3>
        </div>
        <div class="card-body">
          <p class="mb-2">
            <span class="badge <?= $iFailedCount > 0 ? 'bg-red-lt text-red' : 'bg-green-lt text-green' ?> me-1" id="ministry-failed-count"><?= (int) $iFailedCount ?></span>
            <?= $iFailedCount > 0
                ? gettext('messages could not be delivered after five attempts')
                : gettext('every message has been delivered or is still queued') ?>
          </p>
          <p class="mb-2">
            <span class="badge bg-secondary-lt me-1" id="ministry-pending-count"><?= (int) $iPendingCount ?></span>
            <?= gettext('messages waiting to be sent') ?>
          </p>
          <p class="mb-2">
            <i class="fa-solid fa-clock-rotate-left me-1"></i>
            <?= gettext('Background jobs last ran') ?>:
            <strong id="ministry-last-run"><?= $sLastTimerJobsRun === '' ? gettext('never') : InputUtils::escapeHTML($sLastTimerJobsRun) ?></strong>
          </p>
          <p class="mb-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="ministry-run-jobs-btn">
              <i class="fa-solid fa-play me-1"></i><?= gettext('Run background jobs now') ?>
            </button>
            <span class="text-body-secondary small ms-2"><?= gettext('Sends whatever is queued and closes out finished occurrences, without waiting for the next scheduled run.') ?></span>
          </p>
          <p class="text-body-secondary small mb-0" id="ministry-cron-hint">
            <i class="fa-solid fa-clock me-1"></i>
            <?= gettext('Reminders and alerts are only delivered when background jobs run. For on-time delivery, run the scheduled-task runner from cron every 15 minutes') ?> —
            <code><?= InputUtils::escapeHTML($sTimerJobsHint) ?></code>
          </p>
          <?php if ($aRecentFailures !== []): ?>
            <div class="table-responsive mt-3">
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
                      <td><?= InputUtils::escapeHTML($failure['lastAttempt']) ?></td>
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

    <div class="col-12 col-xl-6">
      <div class="card mb-3" id="ministry-experience-card">
        <div class="card-header">
          <h3 class="card-title mb-0">
            <i class="fa-solid fa-circle-info me-2"></i><?= gettext('What the volunteer experiences are') ?>
          </h3>
        </div>
        <div class="card-body">
          <p class="mb-2">
            <?= gettext('ChurchCRM has two ways of managing volunteers. Only one is meant to be in use at a time; the setting on the left decides which one this church sees. Switching hides or shows pages and menu entries — it never deletes data, so you can switch back.') ?>
          </p>
          <h4 class="mt-3"><?= gettext('V1 — Volunteer Opportunities (legacy)') ?></h4>
          <p class="mb-2">
            <?= gettext('A single list of opportunities, such as "Nursery" or "Greeter", kept under People → Admin. Each person is tagged with the opportunities they are willing to help with, on the Volunteer tab of their record. That is all it records: there are no teams, no schedules, no assignments to a date, and no reminder emails. Reports and the person record are where the tags are used.') ?>
          </p>
          <h4 class="mt-3"><?= gettext('V2 — Ministries') ?></h4>
          <p class="mb-2">
            <?= gettext('Volunteering organised the way a church runs it. Each ministry has teams and positions; people are qualified for positions and belong to the ministry\'s pool group; schedules follow the events on the calendar and produce dated occurrences with staffing requirements; coordinators assign people, who accept, decline or propose a substitute; reminders and alerts go out by email. Administrators and users with the Manage Ministries permission see every ministry; a ministry coordinator or team leader sees only theirs, under the Ministries heading in the sidebar. Members use the Member Portal to see their schedule, respond, sign up and offer help.') ?>
          </p>
          <h4 class="mt-3"><?= gettext('Both') ?></h4>
          <p class="mb-0">
            <?= gettext('A transition setting. The legacy Volunteer Opportunities pages and the new Ministries pages are both available, and a person record shows both Volunteer tabs, clearly labelled. Use it while you move a church from V1 to V2, then switch to V2 only.') ?>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    var runBtn = document.getElementById('ministry-run-jobs-btn');
    if (runBtn) {
        runBtn.addEventListener('click', function () {
            runBtn.disabled = true;
            window.CRM.APIRequest({
                method: 'POST',
                path: 'background/timerjobs',
                data: JSON.stringify({ force: true })
            }).done(function () {
                window.CRM.notify(<?= InputUtils::jsonEncodeForScript(gettext('Background jobs ran')) ?>, { type: 'success' });
                setTimeout(function () { window.location.reload(); }, 1200);
            }).fail(function () {
                runBtn.disabled = false;
            });
        });
    }

    window.CRM.settingsPanel.init({
        container: '#ministrySettingsPanel',
        title: <?= InputUtils::jsonEncodeForScript(gettext('Settings')) ?>,
        icon: 'fa-solid fa-sliders',
        headerClass: 'bg-info-lt',
        // These two items deliberately carry no System Settings category, so a
        // link to that page would be a dead end.
        showAllSettingsLink: false,
        settings: [
            {
                name: 'sVolunteerVersion',
                type: 'choice',
                label: <?= InputUtils::jsonEncodeForScript(gettext('Volunteer experience')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('Which volunteer experience this church uses. The card on the right explains each choice.')) ?>,
                choices: [
                    { value: 'v1', label: <?= InputUtils::jsonEncodeForScript(gettext('V1 — Volunteer Opportunities (legacy)')) ?> },
                    { value: 'v2', label: <?= InputUtils::jsonEncodeForScript(gettext('V2 — Ministries')) ?> },
                    { value: 'both', label: <?= InputUtils::jsonEncodeForScript(gettext('Both (transition)')) ?> }
                ]
            },
            {
                name: 'iVolunteerReminderLeadHours',
                type: 'number',
                min: 0,
                max: 720,
                label: <?= InputUtils::jsonEncodeForScript(gettext('Reminder lead time (hours)')) ?>,
                tooltip: <?= InputUtils::jsonEncodeForScript(gettext('How many hours before an occurrence the reminder email is sent. Set to 0 to send no reminders at all.')) ?>
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
