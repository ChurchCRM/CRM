<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="card">
  <div class="card-body">
    <form method="post" action="<?= SystemURLs::getRootPath() ?>/account/settings" id="userSettingsForm">
      <?= CSRFUtils::getTokenInputField('user_settings') ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th><?= gettext('Variable name') ?></th>
              <th><?= gettext('Current Value') ?></th>
              <th><?= gettext('Notes') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($settings as $setting):
              $id = (int) $setting->getId();
              $name = $setting->getName();
              $value = $setting->getValue();
              $type = $setting->getType();
              ?>
            <tr>
              <td><?= InputUtils::escapeHTML($name) ?></td>
              <td>
                <?php if ($type === 'text'): ?>
                <input type="text" class="form-control" maxlength="255" name="newValue[<?= $id ?>]" value="<?= InputUtils::escapeAttribute($value) ?>" aria-label="<?= InputUtils::escapeAttribute($name) ?>">
                <?php elseif ($type === 'textarea'): ?>
                <textarea class="form-control" rows="4" name="newValue[<?= $id ?>]" aria-label="<?= InputUtils::escapeAttribute($name) ?>"><?= InputUtils::escapeHTML($value) ?></textarea>
                <?php elseif ($type === 'number' || $type === 'date'): ?>
                <input type="text" class="form-control" maxlength="15" name="newValue[<?= $id ?>]" value="<?= InputUtils::escapeAttribute($value) ?>" aria-label="<?= InputUtils::escapeAttribute($name) ?>">
                <?php elseif ($type === 'boolean'): ?>
                <select class="form-select" name="newValue[<?= $id ?>]" aria-label="<?= InputUtils::escapeAttribute($name) ?>">
                  <option value=""<?= $value ? '' : ' selected' ?>><?= gettext('False') ?></option>
                  <option value="1"<?= $value ? ' selected' : '' ?>><?= gettext('True') ?></option>
                </select>
                <?php endif; ?>
              </td>
              <td><?= $setting->getTooltip() !== '' ? InputUtils::escapeHTML(gettext($setting->getTooltip())) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><?= gettext('Save Settings') ?></button>
        <a href="<?= SystemURLs::getRootPath() ?>/account" class="btn btn-secondary"><?= gettext('Cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
