<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;

// Get Classifications for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
$rsClassifications = RunQuery($sSQL);

// Get Family Roles for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 2 ORDER BY lst_OptionSequence';
$rsFamilyRoles = RunQuery($sSQL);

// Get all the Groups
$sSQL = 'SELECT * FROM group_grp ORDER BY grp_Name';
$rsGroups = RunQuery($sSQL);

$sSQL = 'SELECT person_custom_master.* FROM person_custom_master ORDER BY custom_Order';
$rsCustomFields = RunQuery($sSQL);
$numCustomFields = mysqli_num_rows($rsCustomFields);

$sSQL = 'SELECT family_custom_master.* FROM family_custom_master ORDER BY fam_custom_Order';
$rsFamCustomFields = RunQuery($sSQL);
$numFamCustomFields = mysqli_num_rows($rsFamCustomFields);

// Get Field Security List Matrix
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 5 ORDER BY lst_OptionSequence';
$rsSecurityGrp = RunQuery($sSQL);

while ($aRow = mysqli_fetch_array($rsSecurityGrp)) {
    extract($aRow);
    $aSecurityType[$lst_OptionID] = $lst_OptionName;
}

$sPageTitle = gettext('CSV Export');
$sPageSubtitle = gettext('Export data to CSV format for external applications');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('Admin'), '/admin/'],
    [gettext('Export'), '/admin/export'],
    [gettext('CSV Export')],
]);
require_once __DIR__ . '/Include/Header.php';
?>
<form method="post" action="CSVCreateFile.php">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title"><?= gettext('Field Selection') ?></h3>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <span class="badge bg-blue-lt"><?= gettext('Last Name') ?></span>
            <span class="text-secondary small"><?= gettext('Required') ?></span>
          </div>
          <div class="form-selectgroup form-selectgroup-pills">
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Title" value="1">
              <span class="form-selectgroup-label"><?= gettext('Title') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="FirstName" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('First Name') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="MiddleName" value="1">
              <span class="form-selectgroup-label"><?= gettext('Middle Name') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Suffix" value="1">
              <span class="form-selectgroup-label"><?= gettext('Suffix') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Address1" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('Address') ?> 1</span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Address2" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('Address') ?> 2</span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="City" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('City') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="State" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('State') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Zip" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('Zip') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Envelope" value="1">
              <span class="form-selectgroup-label"><?= gettext('Envelope') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Country" value="1" checked>
              <span class="form-selectgroup-label"><?= gettext('Country') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="HomePhone" value="1">
              <span class="form-selectgroup-label"><?= gettext('Home Phone') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="WorkPhone" value="1">
              <span class="form-selectgroup-label"><?= gettext('Work Phone') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="CellPhone" value="1">
              <span class="form-selectgroup-label"><?= gettext('Mobile Phone') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Email" value="1">
              <span class="form-selectgroup-label"><?= gettext('Email') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="WorkEmail" value="1">
              <span class="form-selectgroup-label"><?= gettext('Work/Other Email') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="MembershipDate" value="1">
              <span class="form-selectgroup-label"><?= gettext('Membership Date') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="BirthdayDate" value="1">
              <span class="form-selectgroup-label">* <?= gettext('Birth Date') . ' / ' . gettext('Anniversary Date') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="Age" value="1">
              <span class="form-selectgroup-label">* <?= gettext('Age / Years Married') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="PrintMembershipStatus" value="1">
              <span class="form-selectgroup-label"><?= gettext('Classification') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="PrintFamilyRole" value="1">
              <span class="form-selectgroup-label"><?= gettext('Family Role') ?></span>
            </label>
            <label class="form-selectgroup-item">
              <input type="checkbox" class="form-selectgroup-input" name="PrintGender" value="1">
              <span class="form-selectgroup-label"><?= gettext('Gender') ?></span>
            </label>
          </div>
          <div class="mt-2">
            <span class="text-secondary small">* <?= gettext('Depends whether using person or family output method') ?></span>
          </div>

        </div>
      </div>

    </div>
  </div>
  <?php
    if ($numCustomFields > 0 || $numFamCustomFields > 0) {
        ?>
    <div class="row">
      <div class="col-lg-12">
        <div class="card">
          <div class="card-header d-flex align-items-center">
            <h3 class="card-title"><?= gettext('Custom Field Selection') ?></h3>
          </div>
          <div class="card-body">
            <?php if ($numCustomFields > 0): ?>
              <label class="form-label fw-medium"><?= gettext('Custom Person Fields') ?></label>
              <div class="form-selectgroup form-selectgroup-pills mb-3">
                <?php while ($Row = mysqli_fetch_array($rsCustomFields)) {
                    extract($Row);
                    if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) { ?>
                    <label class="form-selectgroup-item">
                      <input type="checkbox" class="form-selectgroup-input" name="<?= InputUtils::escapeAttribute($custom_Field) ?>" value="1">
                      <span class="form-selectgroup-label"><?= InputUtils::escapeHTML($custom_Name) ?></span>
                    </label>
                <?php }
                } ?>
              </div>
            <?php endif; ?>
            <?php if ($numFamCustomFields > 0): ?>
              <label class="form-label fw-medium"><?= gettext('Custom Family Fields') ?></label>
              <div class="form-selectgroup form-selectgroup-pills">
                <?php while ($Row = mysqli_fetch_array($rsFamCustomFields)) {
                    extract($Row);
                    if ($aSecurityType[$fam_custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$fam_custom_FieldSec]]) { ?>
                    <label class="form-selectgroup-item">
                      <input type="checkbox" class="form-selectgroup-input" name="<?= InputUtils::escapeAttribute($fam_custom_Field) ?>" value="1">
                      <span class="form-selectgroup-label"><?= InputUtils::escapeHTML($fam_custom_Name) ?></span>
                    </label>
                <?php }
                } ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
        <?php
    } ?>

  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title"><?= gettext('Filters') ?></h3>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Records to export') ?></label>
              <select name="Source" class="form-select">
                <option value="filters"><?= gettext('Based on filters below..') ?></option>
                <option value="cart" <?php if (array_key_exists('Source', $_GET) && $_GET['Source'] == 'cart') {
                    echo 'selected';
                } ?>><?= gettext('People in Cart (filters ignored)') ?></option>
              </select>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Classification') ?></label>
              <select name="Classification[]" size="5" multiple class="form-select">
                <?php
                while ($aRow = mysqli_fetch_array($rsClassifications)) {
                    extract($aRow); ?>
                  <option value="<?= $lst_OptionID ?>"><?= $lst_OptionName ?></option>
                <?php } ?>
              </select>
              <small class="form-hint"><?= gettext('Use Ctrl Key to select multiple') ?></small>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Family Role') ?></label>
              <select name="FamilyRole[]" size="5" multiple class="form-select">
                <?php
                while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                    extract($aRow); ?>
                  <option value="<?= $lst_OptionID ?>"><?= $lst_OptionName ?></option>
                <?php } ?>
              </select>
              <small class="form-hint"><?= gettext('Use Ctrl Key to select multiple') ?></small>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Gender') ?></label>
              <select name="Gender" class="form-select">
                <option value="0"><?= gettext("Don't Filter") ?></option>
                <option value="1"><?= gettext('Male') ?></option>
                <option value="2"><?= gettext('Female') ?></option>
              </select>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Group Membership') ?></label>
              <select name="GroupID[]" size="5" multiple class="form-select">
                <?php
                while ($aRow = mysqli_fetch_array($rsGroups)) {
                    extract($aRow);
                    echo '<option value="' . $grp_ID . '">' . $grp_Name . '</option>';
                } ?>
              </select>
              <small class="form-hint"><?= gettext('Use Ctrl Key to select multiple') ?></small>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Membership Date') ?></label>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('From') ?></label>
                  <input id="MembershipDate1" class="form-control date-picker" type="text" name="MembershipDate1" maxlength="10">
                </div>
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('To') ?></label>
                  <input id="MembershipDate2" class="form-control date-picker" type="text" name="MembershipDate2" maxlength="10" value="<?= date('Y-m-d') ?>">
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Birth Date') ?></label>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('From') ?></label>
                  <input type="text" name="BirthDate1" class="form-control date-picker" maxlength="10" id="BirthdayDate1">
                </div>
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('To') ?></label>
                  <input type="text" name="BirthDate2" class="form-control date-picker" maxlength="10" value="<?= date('Y-m-d') ?>" id="BirthdayDate2">
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Anniversary Date') ?></label>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('From') ?></label>
                  <input type="text" class="form-control date-picker" name="AnniversaryDate1" maxlength="10" id="AnniversaryDate1">
                </div>
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('To') ?></label>
                  <input type="text" class="form-control date-picker" name="AnniversaryDate2" maxlength="10" value="<?= date('Y-m-d') ?>" id="AnniversaryDate2">
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <label class="form-label"><?= gettext('Date Entered') ?></label>
              <div class="row g-2">
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('From') ?></label>
                  <input id="EnterDate1" type="text" name="EnterDate1" maxlength="10" class="form-control date-picker">
                </div>
                <div class="col-6">
                  <label class="form-label small text-secondary"><?= gettext('To') ?></label>
                  <input id="EnterDate2" type="text" name="EnterDate2" maxlength="10" value="<?= date('Y-m-d') ?>" class="form-control date-picker">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title"><?= gettext('Output Method:') ?></h3>
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label"><?= gettext('Format') ?>:</label>
            <select name="Format" class="form-select" style="max-width:300px">
              <option value="Default"><?= gettext('CSV Individual Records') ?></option>
              <option value="Rollup"><?= gettext('CSV Combine Families') ?></option>
              <option value="AddToCart"><?= gettext('Add Individuals to Cart') ?></option>
            </select>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="SkipIncompleteAddr" value="1" id="SkipIncompleteAddr">
            <label class="form-check-label" for="SkipIncompleteAddr"><?= gettext('Skip records with incomplete mail address') ?></label>
          </div>
          <input type="submit" class="btn btn-secondary" value="<?= gettext('Create File') ?>" name="Submit">
        </div>
      </div>
    </div>
  </div>

</form>

<?php
require_once __DIR__ . '/Include/Footer.php';
