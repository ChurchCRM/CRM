<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="card-body">
    <?php if (count($rows) > 0): ?>
      <div style="overflow: visible;">
        <table id="eventTypesTable" class="table table-hover table-vcenter">
          <thead>
            <tr>
              <th><?= gettext('Name') ?></th>
              <th><?= gettext('Recurrence') ?></th>
              <th><?= gettext('Start Time') ?></th>
              <th><?= gettext('Attendance Counts') ?></th>
              <th class="text-center"><?= gettext('Status') ?></th>
              <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <a href="<?= $sRootPath ?>/event/types/<?= $r['id'] ?>" class="fw-bold">
                    <?= InputUtils::escapeHTML($r['name']) ?>
                  </a>
                </td>
                <td><?= InputUtils::escapeHTML($r['recurText']) ?></td>
                <td><?= !empty($r['startTime']) ? InputUtils::escapeHTML($r['startTime']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                  <?php if (!empty($r['countList'])): ?>
                    <?= InputUtils::escapeHTML($r['countList']) ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <?php if ($r['active']): ?>
                    <span class="badge bg-green-lt text-green"><?= gettext('Active') ?></span>
                  <?php else: ?>
                    <span class="badge bg-secondary-lt"><?= gettext('Inactive') ?></span>
                  <?php endif; ?>
                </td>
                <td class="w-1">
                  <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                      <i class="ti ti-dots-vertical"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                      <a class="dropdown-item" href="<?= $sRootPath ?>/event/types/<?= $r['id'] ?>">
                        <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                      </a>
                      <a class="dropdown-item" href="<?= $sRootPath ?>/event/repeat-editor/<?= $r['id'] ?>">
                        <i class="ti ti-repeat me-2"></i><?= gettext('Create Repeat Events') ?>
                      </a>
                      <button type="button" class="dropdown-item create-event-btn" data-type-id="<?= $r['id'] ?>">
                        <i class="ti ti-plus me-2"></i><?= gettext('Create Event') ?>
                      </button>
                      <div class="dropdown-divider"></div>
                      <button type="button" class="dropdown-item text-danger delete-type-btn"
                              data-type-id="<?= $r['id'] ?>"
                              data-type-name="<?= InputUtils::escapeAttribute($r['name']) ?>">
                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="text-center text-muted py-5">
        <i class="fa-solid fa-calendar-days fa-3x mb-3"></i>
        <p><?= gettext('No event types defined yet.') ?></p>
        <a href="<?= $sRootPath ?>/event/types/new" class="btn btn-primary">
          <i class="fa-solid fa-plus me-1"></i><?= gettext('Add Event Type') ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function() {
  if ($("#eventTypesTable tbody tr").length > 0) {
    $('#eventTypesTable').DataTable(window.CRM.plugin.dataTable);
  }

  // Create event from type
  $(document).on('click', '.create-event-btn', function() {
    const typeId = $(this).data('type-id');
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = '<?= $sRootPath ?>/EventEditor.php';
    const i = document.createElement('input');
    i.type = 'hidden'; i.name = 'EN_tyid'; i.value = typeId;
    f.appendChild(i);
    document.body.appendChild(f);
    f.submit();
  });

  // Delete event type with confirmation
  $(document).on('click', '.delete-type-btn', function() {
    const typeId = $(this).data('type-id');
    const typeName = $(this).data('type-name');
    bootbox.confirm({
      title: i18next.t('Delete Event Type'),
      message: i18next.t('Deleting this event type will NOT delete existing events. Are you sure?'),
      buttons: {
        confirm: { label: i18next.t('Yes'), className: 'btn-danger' },
        cancel:  { label: i18next.t('No'),  className: 'btn-default' }
      },
      callback: function(result) {
        if (result) {
          const f = document.createElement('form');
          f.method = 'POST';
          f.action = '<?= $sRootPath ?>/event/types/' + typeId + '/delete';
          document.body.appendChild(f);
          f.submit();
        }
      }
    });
  });
});
</script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
