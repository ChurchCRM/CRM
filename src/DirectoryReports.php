<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Service\ClassificationService;
use ChurchCRM\Service\CustomFieldService;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

// The directory lists contact details of the whole congregation: staff users only.
if (AuthenticationManager::getCurrentUser()->isEditSelfExclusive()) {
    RedirectUtils::securityRedirect('Staff');
}

$sPageTitle = gettext('Directory reports');
$sPageSubtitle = gettext('Generate directory listings and printed materials');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People Reports'), '/people/reports'],
    [gettext('Directory Reports')],
]);
require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
  <div class="card-body">
    <form method="POST" action="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/people/reports/directory') ?>">
<?php

$classifications = ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find();
$familyRoles = ListOptionQuery::create()->filterById(2)->orderByOptionSequence()->find();
$groups = GroupQuery::create()->orderByName()->find();
// Only the person custom fields this user may see are offered.
$customFields = (new CustomFieldService())->getVisiblePersonFields();

$aDefaultClasses = (new ClassificationService())->getDirectoryIds();
$aDirRoleHead = explode(',', SystemConfig::getValue('sDirRoleHead'));
$aDirRoleSpouse = explode(',', SystemConfig::getValue('sDirRoleSpouse'));
$aDirRoleChild = explode(',', SystemConfig::getValue('sDirRoleChild'));

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
        <select class="form-select" name="sDirClassifications[]" size="5" multiple required>
          <option value="0"><?= gettext('Unassigned') ?></option>
          <?php foreach ($classifications as $classification) : ?>
            <option value="<?= (int) $classification->getOptionId() ?>"<?= in_array($classification->getOptionId(), $aDefaultClasses) ? ' selected' : '' ?>><?= InputUtils::escapeHTML(gettext($classification->getOptionName())) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Group Membership') ?>:</label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="GroupID[]" size="5" multiple>
          <?php foreach ($groups as $group) : ?>
            <option value="<?= (int) $group->getId() ?>"><?= InputUtils::escapeHTML($group->getName()) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
<?php endif; ?>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Which role is the head of household?') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirRoleHead[]" size="5" multiple>
          <?php foreach ($familyRoles as $role) : ?>
            <option value="<?= (int) $role->getOptionId() ?>"<?= in_array($role->getOptionId(), $aDirRoleHead) ? ' selected' : '' ?>><?= InputUtils::escapeHTML(gettext($role->getOptionName())) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Which role is the spouse?') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirRoleSpouse[]" size="5" multiple>
          <?php foreach ($familyRoles as $role) : ?>
            <option value="<?= (int) $role->getOptionId() ?>"<?= in_array($role->getOptionId(), $aDirRoleSpouse) ? ' selected' : '' ?>><?= InputUtils::escapeHTML(gettext($role->getOptionName())) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Which role is a child?') ?></label>
        <small class="text-secondary d-block mb-1"><?= gettext('Use Ctrl Key to select multiple') ?></small>
        <select class="form-select" name="sDirRoleChild[]" size="5" multiple>
          <?php foreach ($familyRoles as $role) : ?>
            <option value="<?= (int) $role->getOptionId() ?>"<?= in_array($role->getOptionId(), $aDirRoleChild) ? ' selected' : '' ?>><?= InputUtils::escapeHTML(gettext($role->getOptionName())) ?></option>
          <?php endforeach; ?>
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
          <?php endforeach; ?>
          <?php foreach ($customFields as $customField) :
              $customName = 'bCustom' . (int) $customField->getOrder(); ?>
            <div class="col">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="<?= $customName ?>" value="1" id="<?= $customName ?>" checked>
                <label class="form-check-label" for="<?= $customName ?>"><?= InputUtils::escapeHTML($customField->getName()) ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label"><?= gettext('Page Layout') ?>:</label>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="sDirLayout" value="pages" id="sDirLayoutPages" checked>
          <label class="form-check-label" for="sDirLayoutPages"><?= gettext('Single Pages') ?></label>
          <div class="form-text"><?= gettext('One directory page per sheet, portrait.') ?></div>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="sDirLayout" value="booklet" id="sDirLayoutBooklet">
          <label class="form-check-label" for="sDirLayoutBooklet"><?= gettext('Folded Booklet') ?></label>
          <div class="form-text"><?= gettext('Two half-size pages side by side on each landscape sheet, in booklet order. Print double-sided with "flip on short edge", then fold the stack in half. Columns are counted per half page.') ?></div>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label"><?= gettext('Columns per Page') ?>:</label>
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
        <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/v2/dashboard') ?>"><?= gettext('Cancel') ?></a>
      </div>
    </form>
  </div>
</div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  // "Mailing Address if Different" prints beneath the primary address, so it only
  // means something while "Primary Address" is on. Keep the two in step: turning
  // the primary address off disables and clears the sub-option instead of
  // silently ignoring it when the report is built.
  (function () {
    var primary = document.getElementById("bDirAddress");
    var mailing = document.getElementById("bDirMailingAddress");
    if (!primary || !mailing) {
      return;
    }
    var sync = function () {
      mailing.disabled = !primary.checked;
      if (!primary.checked) {
        mailing.checked = false;
      }
    };
    primary.addEventListener("change", sync);
    sync();
  })();
</script>
<?php
require_once __DIR__ . '/Include/Footer.php';
