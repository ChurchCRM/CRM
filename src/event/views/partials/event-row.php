<?php
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

/**
 * Partial: a single event row for the events dashboard table.
 *
 * Expected variables (from parent scope):
 *   $event        — array with keys: id, title, desc, type_name, attendee_count,
 *                   counts, start, inactive
 *   $sRootPath    — application root path
 *   $canEditEvents — bool
 *
 * Uses $_eventRowId as a local variable to avoid polluting the calling scope.
 */
$_eventRowId = (int) $event['id'];
?>
<tr>
  <td>
    <a href="<?= $sRootPath ?>/event/view/<?= $_eventRowId ?>" class="fw-medium text-reset text-decoration-none">
      <?= InputUtils::escapeHTML($event['title']) ?>
    </a>
    <?php
      // Quill leaves "<p><br /></p>" when the description is empty —
      // strip tags and trim before deciding whether to show anything.
      $descText = trim(strip_tags((string) ($event['desc'] ?? '')));
    ?>
    <?php if ($descText !== ''): ?>
      <div><small class="text-body-secondary"><?= InputUtils::escapeHTML($descText) ?></small></div>
    <?php endif; ?>
  </td>
  <td>
    <span class="badge bg-azure-lt"><?= InputUtils::escapeHTML($event['type_name']) ?></span>
  </td>
  <td class="text-center">
    <a href="<?= $sRootPath ?>/event/checkin/<?= $_eventRowId ?>" class="btn btn-sm btn-ghost-secondary" title="<?= gettext('Manage Check-ins') ?>">
      <i class="ti ti-clipboard-check me-1"></i>
      <?php if ($event['attendee_count'] > 0): ?>
        <span class="badge bg-primary text-white"><?= $event['attendee_count'] ?></span>
      <?php else: ?>
        <span class="text-body-secondary">0</span>
      <?php endif; ?>
    </a>
  </td>
  <td>
    <?php if (empty($event['counts'])): ?>
      <span class="text-body-secondary">—</span>
    <?php else: ?>
      <?php
      $countParts = [];
      foreach ($event['counts'] as $count) {
          if ($count['count'] > 0) {
              $countParts[] = '<span class="text-body-secondary small">' . InputUtils::escapeHTML($count['name']) . '</span> ' . $count['count'];
          }
      }
      echo !empty($countParts) ? implode('<br>', $countParts) : '<span class="text-body-secondary">—</span>';
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
      <div
        class="event-action-menu-placeholder"
        data-event-id="<?= $_eventRowId ?>"
        data-event-title="<?= InputUtils::escapeAttribute($event['title']) ?>"
        data-event-inactive="<?= (int) $event['inactive'] ?>"
      ></div>
    </td>
  <?php endif; ?>
</tr>
