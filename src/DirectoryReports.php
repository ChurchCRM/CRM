<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\view\PageHeader;

$sPageTitle = gettext('Directory reports');
$sPageSubtitle = gettext('Generate directory listings and printed materials');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Data & Reports'), '/QueryList.php'],
    [gettext('Directory Reports')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
  <div class="card-body">
    <form method="POST" action="Reports/DirectoryReport.php">
<?php

// Get classifications for the selects
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
$rsClassifications = RunQuery($sSQL);

//Get Family Roles for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
$rsFamilyRoles = RunQuery($sSQL);

// Get all the Groups
$sSQL = 'SELECT * FROM group_grp ORDER BY grp_Name';
$rsGroups = RunQuery($sSQL);

// Get the list of custom person fields
$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

$aDefaultClasses = explode(',', SystemConfig::getValue('sDirClassifications'));
$aDirRoleHead = explode(',', SystemConfig::getValue('sDirRoleHead'));
$aDirRoleSpouse = explode(',', SystemConfig::getValue('sDirRoleSpouse'));
$aDirRoleChild = explode(',', SystemConfig::getValue('sDirRoleChild'));

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

?>
<?php if (!array_key_exists('cartdir', $_GET)) : ?>
      <div class="mb-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="bExcludeInactive" value="1" id="bExcludeInactive" checked>
          <label class="form-check-label" for="bExcludeInactive"><?= gettext('Exclude Inactive Families') ?></label>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Select classifications to include') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirClassifications[]" size="5" multiple>
          <option value="0"><?= gettext('Unassigned') ?></option>
          <?php while ($aRow = mysqli_fetch_array($rsClassifications)) {
              extract($aRow);
              echo '<option value="' . $lst_OptionID . '"';
              if (in_array($lst_OptionID, $aDefaultClasses)) {
                  echo ' selected';
              }
              echo '>' . gettext($lst_OptionName) . '</option>';
          } ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Group Membership') ?>:</label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="GroupID[]" size="5" multiple>
          <?php while ($aRow = mysqli_fetch_array($rsGroups)) {
              extract($aRow);
              echo '<option value="' . $grp_ID . '">' . $grp_Name . '</option>';
          } ?>
        </select>
      </div>
<?php endif; ?>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Which role is the head of household?') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirRoleHead[]" size="5" multiple>
          <?php while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
              extract($aRow);
              echo '<option value="' . $lst_OptionID . '"';
              if (in_array($lst_OptionID, $aDirRoleHead)) {
                  echo ' selected';
              }
              echo '>' . gettext($lst_OptionName) . '</option>';
          } ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Which role is the spouse?') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirRoleSpouse[]" size="5" multiple>
          <?php
          mysqli_data_seek($rsFamilyRoles, 0);
          while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
              extract($aRow);
              echo '<option value="' . $lst_OptionID . '"';
              if (in_array($lst_OptionID, $aDirRoleSpouse)) {
                  echo ' selected';
              }
              echo '>' . gettext($lst_OptionName) . '</option>';
          } ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Which role is a child?') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirRoleChild[]" size="5" multiple>
          <?php
          mysqli_data_seek($rsFamilyRoles, 0);
          while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
              extract($aRow);
              echo '<option value="' . $lst_OptionID . '"';
              if (in_array($lst_OptionID, $aDirRoleChild)) {
                  echo ' selected';
              }
              echo '>' . gettext($lst_OptionName) . '</option>';
          } ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Information to Include') ?>:</label>
        <div class="row row-cols-2 row-cols-md-3 g-1">
          <?php
          // 'checked' is the default state of the option; 'indent' marks a sub-option
          // of the entry above it. The options flow through a 2/3-column grid, so an
          // indented entry reads as belonging to its neighbour rather than sitting
          // underneath it.
          $checkFields = [
              'bDirAddress'        => ['label' => gettext('Primary Address'), 'checked' => true],
              'bDirMailingAddress' => ['label' => gettext('Mailing Address if Different'), 'checked' => false, 'indent' => true],
              'bDirWedding'        => ['label' => gettext('Wedding Date'), 'checked' => true],
              'bDirBirthday'       => ['label' => gettext('Birthday'), 'checked' => true],
              'bDirFamilyPhone'    => ['label' => gettext('Family Home Phone'), 'checked' => true],
              'bDirFamilyWork'     => ['label' => gettext('Family Work Phone'), 'checked' => true],
              'bDirFamilyCell'     => ['label' => gettext('Family Cell Phone'), 'checked' => true],
              'bDirFamilyEmail'    => ['label' => gettext('Family Email'), 'checked' => true],
              'bDirPersonalPhone'  => ['label' => gettext('Personal Home Phone'), 'checked' => true],
              'bDirPersonalWork'   => ['label' => gettext('Personal Work Phone'), 'checked' => true],
              'bDirPersonalCell'   => ['label' => gettext('Personal Cell Phone'), 'checked' => true],
              'bDirPersonalEmail'  => ['label' => gettext('Personal Email'), 'checked' => true],
              'bDirPersonalWorkEmail' => ['label' => gettext('Personal Work/Other Email'), 'checked' => true],
              'bDirPhoto'          => ['label' => gettext('Photos'), 'checked' => true],
          ];
          foreach ($checkFields as $name => $field) : ?>
            <div class="col">
              <div class="form-check<?= !empty($field['indent']) ? ' ms-4' : '' ?>">
                <input class="form-check-input" type="checkbox" name="<?= $name ?>" value="1" id="<?= $name ?>" <?= $field['checked'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $name ?>"><?= $field['label'] ?></label>
              </div>
            </div>
          <?php endforeach;
          if ($numCustomFields > 0) {
              while ($rowCustomField = mysqli_fetch_array($rsCustomFields, MYSQLI_ASSOC)) {
                  if (($aSecurityType[$rowCustomField['custom_FieldSec']] == 'bAll') || ($_SESSION[$aSecurityType[$rowCustomField['custom_FieldSec']]])) {
                      $customName = 'bCustom' . $rowCustomField['custom_Order']; ?>
                <div class="col">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= $customName ?>" value="1" id="<?= $customName ?>" checked>
                    <label class="form-check-label" for="<?= $customName ?>"><?= $rowCustomField['custom_Name'] ?></label>
                  </div>
                </div>
              <?php }
              }
          } ?>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label"><?= gettext('Number of Columns') ?>:</label>
          <div class="d-flex gap-3">
            <?php foreach ([1 => '1 col', 2 => '2 cols', 3 => '3 cols'] as $val => $label) : ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="NumCols" value="<?= $val ?>" id="NumCols<?= $val ?>" <?= $val === 2 ? 'checked' : '' ?>>
                <label class="form-check-label" for="NumCols<?= $val ?>"><?= $label ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label"><?= gettext('Paper Size') ?>:</label>
          <div class="d-flex gap-3">
            <?php foreach (['letter' => 'Letter (8.5x11)', 'legal' => 'Legal (8.5x14)', 'a4' => 'A4'] as $val => $label) : ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="PageSize" value="<?= $val ?>" id="PageSize<?= $val ?>" <?= $val === 'letter' ? 'checked' : '' ?>>
                <label class="form-check-label" for="PageSize<?= $val ?>"><?= $label ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="col-md-4">
          <label class="form-label"><?= gettext('Font Size') ?>:</label>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ([6, 8, 10, 12, 14, 16] as $fsize) : ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="FSize" value="<?= $fsize ?>" id="FSize<?= $fsize ?>" <?= $fsize === 10 ? 'checked' : '' ?>>
                <label class="form-check-label" for="FSize<?= $fsize ?>"><?= $fsize ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-bold"><?= gettext('Title page') ?>:</label>
        <div class="row g-3">
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="bDirUseTitlePage" value="1" id="bDirUseTitlePage">
              <label class="form-check-label" for="bDirUseTitlePage"><?= gettext('Use Title Page') ?></label>
            </div>
          </div>
          <?php
          $titleFields = [
              'sChurchName'    => gettext('Church Name'),
              'sChurchAddress' => gettext('Address'),
              'sChurchCity'    => gettext('City'),
              'sChurchState'   => gettext('State'),
              'sChurchZip'     => gettext('Zip'),
              'sChurchPhone'   => gettext('Phone'),
          ];
          foreach ($titleFields as $key => $label) : ?>
            <div class="col-md-6">
              <label class="form-label" for="<?= $key ?>"><?= $label ?></label>
              <input type="text" class="form-control" name="<?= $key ?>" id="<?= $key ?>" value="<?= SystemConfig::getValueForAttr($key) ?>">
            </div>
          <?php endforeach; ?>
          <div class="col-12">
            <label class="form-label" for="sDirectoryDisclaimer"><?= gettext('Disclaimer') ?></label>
            <textarea class="form-control" name="sDirectoryDisclaimer" id="sDirectoryDisclaimer" rows="4"><?= SystemConfig::getValueForHtml('sDirectoryDisclaimer1') . ' ' . SystemConfig::getValueForHtml('sDirectoryDisclaimer2') ?></textarea>
          </div>
        </div>
      </div>

<?php if (array_key_exists('cartdir', $_GET)) {
    echo '<input type="hidden" name="cartdir" value="M">';
} ?>

      <div class="d-flex gap-2 mt-3">
        <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Create Directory') ?>">
        <input type="button" class="btn btn-secondary" name="Cancel" value="<?= gettext('Cancel') ?>" onclick="javascript:document.location='v2/dashboard';">
      </div>
    </form>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
