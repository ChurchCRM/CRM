<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';

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
          <div class="col-md-4">
            <label><?= gettext('Last Name') ?>:</label>
            <?= gettext('Required') ?>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Title" value="1">
              <?= gettext('Title') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="FirstName" value="1" checked>
              <?= gettext('First Name') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="MiddleName" value="1">
              <?= gettext('Middle Name') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Suffix" value="1">
              <?= gettext('Suffix') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Address1" value="1" checked>
              <?= gettext('Address') ?> 1
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Address2" value="1" checked>
              <?= gettext('Address') ?> 2
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="City" value="1" checked>
              <?= gettext('City') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="State" value="1" checked>
              <?= gettext('State') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Zip" value="1" checked>
              <?= gettext('Zip') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Envelope" value="1">
              <?= gettext('Envelope') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Country" value="1" checked>
              <?= gettext('Country') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="HomePhone" value="1">
              <?= gettext('Home Phone') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="WorkPhone" value="1">
              <?= gettext('Work Phone') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="CellPhone" value="1">
              <?= gettext('Mobile Phone') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Email" value="1">
              <?= gettext('Email') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="WorkEmail" value="1">
              <?= gettext('Work/Other Email') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="MembershipDate" value="1">
              <?= gettext('Membership Date') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="BirthdayDate" value="1">
              * <?= gettext('Birth Date') . ' / ' . gettext('Anniversary Date') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="Age" value="1">
              * <?= gettext('Age / Years Married') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="PrintMembershipStatus" value="1">
              <?= gettext('Classification') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="PrintFamilyRole" value="1">
              <?= gettext('Family Role') ?>
            </label>
          </div>

          <div class="col-md-4">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="PrintGender" value="1">
              <?= gettext('Gender') ?>
            </label>
          </div>

          <div class="col-md-12">
            * <?= gettext('Depends whether using person or family output method') ?>
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
              <h5><?= gettext('Custom Person Fields') ?></h5>
              <div class="row g-2 mb-3">
                <?php while ($Row = mysqli_fetch_array($rsCustomFields)) {
                    extract($Row);
                    if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) { ?>
                  <div class="col-md-4">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="<?= InputUtils::escapeAttribute($custom_Field) ?>" value="1" id="cf_<?= InputUtils::escapeAttribute($custom_Field) ?>">
                      <label class="form-check-label" for="cf_<?= InputUtils::escapeAttribute($custom_Field) ?>"><?= InputUtils::escapeHTML($custom_Name) ?></label>
                    </div>
                  </div>
                <?php }
                } ?>
              </div>
            <?php endif; ?>
            <?php if ($numFamCustomFields > 0): ?>
              <h5><?= gettext('Custom Family Fields') ?></h5>
              <div class="row g-2">
                <?php while ($Row = mysqli_fetch_array($rsFamCustomFields)) {
                    extract($Row);
                    if ($aSecurityType[$fam_custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$fam_custom_FieldSec]]) { ?>
                  <div class="col-md-4">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="<?= InputUtils::escapeAttribute($fam_custom_Field) ?>" value="1" id="fcf_<?= InputUtils::escapeAttribute($fam_custom_Field) ?>">
                      <label class="form-check-label" for="fcf_<?= InputUtils::escapeAttribute($fam_custom_Field) ?>"><?= InputUtils::escapeHTML($fam_custom_Name) ?></label>
                    </div>
                  </div>
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
          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Records to export') ?>:</h3>
              </div>
              <div class="card-body p-0">
                <select name="Source">
                  <option value="filters"><?= gettext('Based on filters below..') ?></option>
                  <option value="cart" <?php if (array_key_exists('Source', $_GET) && $_GET['Source'] == 'cart') {
                        echo 'selected';
                                       } ?>><?= gettext('People in Cart (filters ignored)') ?></option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Classification') ?>:</h3>
              </div>
              <div class="card-body p-0">
                <select name="Classification[]" size="5" multiple class="form-select">
                  <?php
                    while ($aRow = mysqli_fetch_array($rsClassifications)) {
                        extract($aRow); ?>
                    <option value="<?= $lst_OptionID ?>"><?= $lst_OptionName ?></option>
                        <?php
                    }
                    ?>
                </select>
                <small class="text-muted"><?= gettext('Use Ctrl Key to select multiple') ?></small>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Family Role') ?>:</h3>
              </div>
              <div class="card-body p-0">
                <select name="FamilyRole[]" size="5" multiple class="form-select">
                  <?php
                    while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                        extract($aRow); ?>
                    <option value="<?= $lst_OptionID ?>"><?= $lst_OptionName ?></option>
                        <?php
                    }
                    ?>
                </select>
                <small class="text-muted"><?= gettext('Use Ctrl Key to select multiple') ?></small>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Gender') ?>:</h3>
              </div>
              <div class="card-body p-0">
                <select name="Gender" class="form-select">
                  <option value="0"><?= gettext("Don't Filter") ?></option>
                  <option value="1"><?= gettext('Male') ?></option>
                  <option value="2"><?= gettext('Female') ?></option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Group Membership') ?>:</h3>
              </div>
              <div class="card-body p-0">
                <small class="text-muted"><?= gettext('Use Ctrl Key to select multiple') ?></small>
                <select name="GroupID[]" size="5" multiple class="form-select">
                  <?php
                    while ($aRow = mysqli_fetch_array($rsGroups)) {
                        extract($aRow);
                        echo '<option value="' . $grp_ID . '">' . $grp_Name . '</option>';
                    }
                    ?>
                </select>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Membership Date') ?>:</h3>
              </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label"><?= gettext('From') ?>:</label>
                    <input id="MembershipDate1" class="form-control date-picker" type="text" name="MembershipDate1" maxlength="10">
                  </div>
                  <div>
                    <label class="form-label"><?= gettext('To') ?>:</label>
                    <input id="MembershipDate2" class="form-control date-picker" type="text" name="MembershipDate2" maxlength="10" value="<?= date('Y-m-d') ?>">
                  </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Birth Date') ?>:</h3>
              </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label"><?= gettext('From') ?>:</label>
                    <input type="text" name="BirthDate1" class="form-control date-picker" maxlength="10" id="BirthdayDate1">
                  </div>
                  <div>
                    <label class="form-label"><?= gettext('To') ?>:</label>
                    <input type="text" name="BirthDate2" class="form-control date-picker" maxlength="10" value="<?= date('Y-m-d') ?>" id="BirthdayDate2">
                  </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Anniversary Date') ?>:</h3>
              </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label"><?= gettext('From') ?>:</label>
                    <input type="text" class="form-control date-picker" name="AnniversaryDate1" maxlength="10" id="AnniversaryDate1">
                  </div>
                  <div>
                    <label class="form-label"><?= gettext('To') ?>:</label>
                    <input type="text" class="form-control date-picker" name="AnniversaryDate2" maxlength="10" value="<?= date('Y-m-d') ?>" id="AnniversaryDate2">
                  </div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card border-top border-danger border-3">
              <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><?= gettext('Date Entered') ?>:</h3>
              </div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label"><?= gettext('From') ?>:</label>
                    <input id="EnterDate1" type="text" name="EnterDate1" maxlength="10" class="form-control date-picker">
                  </div>
                  <div>
                    <label class="form-label"><?= gettext('To') ?>:</label>
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
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <h3 class="card-title"><?= gettext('ChMeetings Export') ?></h3>
        </div>
        <div class="card-body">
          <p><?= gettext('Export all people data in ChMeetings format for import into external systems.') ?></p>
          <button type="button" class="btn btn-primary" id="exportChMeetingsBtn">
            <i class="fa-solid fa-download"></i><?= gettext('Export to ChMeetings CSV') ?>
          </button>
        </div>
      </div>
    </div>
  </div>

</form>

<script>
document.getElementById('exportChMeetingsBtn').addEventListener('click', function() {
    var btn = this;
    var originalText = btn.innerHTML;
    
    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><?= gettext("Exporting...") ?>';
    
    var downloadUrl = window.CRM.root + '/admin/api/database/people/export/chmeetings';
    
    fetch(downloadUrl)
        .then(function(response) {
            if (!response.ok) {
                throw new Error(response.statusText);
            }
            return response.blob();
        })
        .then(function(blob) {
            // Trigger file download
            var blobUrl = window.URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = blobUrl;
            link.download = 'ChMeetings-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(blobUrl);
            
            // Restore button state
            btn.disabled = false;
            btn.innerHTML = originalText;
            
            // Show success notification
            window.CRM.notify(i18next.t('ChMeetings export completed successfully'), {
                type: 'success',
                delay: 3000
            });
        })
        .catch(function(error) {
            console.error('Export failed:', error);
            
            // Restore button state
            btn.disabled = false;
            btn.innerHTML = originalText;
            
            // Show error notification
            window.CRM.notify(i18next.t('Failed to export ChMeetings CSV'), {
                type: 'error',
                delay: 3000
            });
        });
});
</script>

<?php
require_once __DIR__ . '/Include/Footer.php';
