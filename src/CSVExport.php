<?php

/*******************************************************************************
 *
 *  filename    : CSVExport.php
 *  description : options for creating csv file
 *
 *  https://churchcrm.io/
 *  Copyright 2001-2002 Phillip Hullquist, Deane Barker
 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\RedirectUtils;

// If user does not have CSV Export permission, redirect to the menu.
if (!AuthenticationManager::getCurrentUser()->isCSVExport()) {
    RedirectUtils::securityRedirect("CSVExport");
}

//Get Classifications for the drop-down
$sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
$rsClassifications = RunQuery($sSQL);

//Get Family Roles for the drop-down
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

// Set the page title and include HTML header
$sPageTitle = gettext('CSV Export');
require 'Include/Header.php';
?>
<form method="post" action="CSVCreateFile.php">
  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header with-border">
          <h3 class="card-title"><?= gettext('Field Selection') ?></h3>
        </div>
        <div class="card-body">
          <div class="col-md-4">
            <label><?= gettext('Last Name') ?>:</label>
            <?= gettext('Required') ?>
          </div>


          <div class="col-md-4">
            <label><?= gettext('Title') ?>:</label>
            <input type="checkbox" name="Title" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('First Name') ?>:</label>
            <input type="checkbox" name="FirstName" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('Middle Name') ?>:</label>
            <input type="checkbox" name="MiddleName" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Suffix') ?>:</label>
            <input type="checkbox" name="Suffix" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Address') ?> 1:</label>
            <input type="checkbox" name="Address1" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('Address') ?> 2:</label>
            <input type="checkbox" name="Address2" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('City') ?>:</label>
            <input type="checkbox" name="City" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('State') ?>:</label>
            <input type="checkbox" name="State" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('Zip') ?>:</label>
            <input type="checkbox" name="Zip" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('Envelope') ?>:</label>
            <input type="checkbox" name="Envelope" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Country') ?>:</label>
            <input type="checkbox" name="Country" value="1" checked>
          </div>

          <div class="col-md-4">
            <label><?= gettext('Home Phone') ?>:</label>
            <input type="checkbox" name="HomePhone" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Work Phone') ?>:</label>
            <input type="checkbox" name="WorkPhone" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Mobile Phone') ?>:</label>
            <input type="checkbox" name="CellPhone" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Email') ?>:</label>
            <input type="checkbox" name="Email" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Work/Other Email') ?>:</label>
            <input type="checkbox" name="WorkEmail" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Membership Date') ?>:</label>
            <input type="checkbox" name="MembershipDate" value="1">
          </div>

          <div class="col-md-4">
            <label>* <?= gettext('Birth / Anniversary Date') ?>:</label>
            <input type="checkbox" name="BirthdayDate" value="1">
          </div>

          <div class="col-md-4">
            <label>* <?= gettext('Age / Years Married') ?>:</label>
            <input type="checkbox" name="Age" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Classification') ?>:</label>
            <input type="checkbox" name="PrintMembershipStatus" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Family Role') ?>:</label>
            <input type="checkbox" name="PrintFamilyRole" value="1">
          </div>

          <div class="col-md-4">
            <label><?= gettext('Gender') ?>:</label>
            <input type="checkbox" name="PrintGender" value="1">
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
          <div class="card-header with-border">
            <h3 class="card-title"><?= gettext('Custom Field Selection') ?></h3>
          </div>
          <div class="card-body">
            <table border="0">
              <?php
                if ($numCustomFields > 0) {
                    ?>
                <tr><td width="100%" valign="top" align="left">
                    <h3><?= gettext('Custom Person Fields') ?></h3>
                    <table cellpadding="4" align="left">
                      <?php
                        // Display the custom fields
                        while ($Row = mysqli_fetch_array($rsCustomFields)) {
                            extract($Row);
                            if ($aSecurityType[$custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$custom_FieldSec]]) {
                                echo '<tr><td class="LabelColumn">' . $custom_Name . '</td>';
                                echo '<td class="TextColumn"><input type="checkbox" name=' . $custom_Field . ' value="1"></td></tr>';
                            }
                        } ?>
                    </table>
                  </td></tr>
                    <?php
                }
                if ($numFamCustomFields > 0) {
                    ?>
                <tr><td width="100%" valign="top" align="left">
                    <h3><?= gettext('Custom Family Fields') ?></h3>
                    <table cellpadding="4" align="left">
                      <?php
                              // Display the family custom fields
                        while ($Row = mysqli_fetch_array($rsFamCustomFields)) {
                            extract($Row);
                            if ($aSecurityType[$fam_custom_FieldSec] == 'bAll' || $_SESSION[$aSecurityType[$fam_custom_FieldSec]]) {
                                echo '<tr><td class="LabelColumn">' . $fam_custom_Name . '</td>';
                                echo '<td class="TextColumn"><input type="checkbox" name=' . $fam_custom_Field . ' value="1"></td></tr>';
                            }
                        } ?>
                    </table>
                  </td></tr>
                              <?php
                } ?>
            </table>
          </div>
        </div>
      </div>
    </div>
        <?php
    } ?>

  <div class="row">
    <div class="col-lg-12">
      <div class="card">
        <div class="card-header with-border">
          <h3 class="card-title"><?= gettext('Filters') ?></h3>
        </div>
        <div class="card-body">
          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Records to export') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
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
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Classification') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <select name="Classification[]" size="5" multiple>
                  <?php
                    while ($aRow = mysqli_fetch_array($rsClassifications)) {
                        extract($aRow); ?>
                    <option value="<?= $lst_OptionID ?>"><?= $lst_OptionName ?></option>
                        <?php
                    }
                    ?>
                </select>
                <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Family Role') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <select name="FamilyRole[]" size="5" multiple>
                  <?php
                    while ($aRow = mysqli_fetch_array($rsFamilyRoles)) {
                        extract($aRow); ?>
                    <option value="<?= $lst_OptionID ?>"><?= $lst_OptionName ?></option>
                        <?php
                    }
                    ?>
                </select>
                <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Gender') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <select name="Gender">
                  <option value="0"><?= gettext("Don't Filter") ?></option>
                  <option value="1"><?= gettext('Male') ?></option>
                  <option value="2"><?= gettext('Female') ?></option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Group Membership') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <div class="SmallText"><?= gettext('Use Ctrl Key to select multiple') ?></div>
                <select name="GroupID[]" size="5" multiple>
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
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Membership Date') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <?= gettext('From:') ?>&nbsp;</b></td><td><input id="MembershipDate1" class="date-picker" type="text" name="MembershipDate1" size="11" maxlength="10">
                  <?= gettext('To:') ?>&nbsp;</b></td><td><input id="MembershipDate2" class="date-picker" type="text" name="MembershipDate2" size="11" maxlength="10" value="<?php echo date('Y-m-d'); ?>">
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Birthday Date') ?>:</h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <b><?= gettext('From:') ?>&nbsp;</b><input type="text" name="BirthDate1" class="date-picker" size="11" maxlength="10" id="BirthdayDate1">
                <b><?= gettext('To:') ?>&nbsp;</b><input type="text" name="BirthDate2" class="date-picker" size="11" maxlength="10" value="<?= date('Y-m-d') ?>"  id="BirthdayDate2">
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Anniversary Date:') ?></h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <?= gettext('From:') ?>&nbsp;</b></td><td><input type="text" class="date-picker" name="AnniversaryDate1" size="11" maxlength="10" id="AnniversaryDate1">
                  <?= gettext('To:') ?>&nbsp;</b></td><td><input type="text" class="date-picker" name="AnniversaryDate2" size="11" maxlength="10" value="<?php echo date('Y-m-d'); ?>" id="AnniversaryDate2">
              </div>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="card card-danger collapsed-box">
              <div class="card-header with-border">
                <h3 class="card-title"><?= gettext('Date Entered:') ?></h3>
                <div class="card-tools pull-right">
                  <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa fa-plus"></i>
                  </button>
                </div>
              </div>
              <!-- /.box-header -->
              <div class="card-body no-padding">
                <?= gettext('From:') ?>&nbsp;</b></td><td><input id="EnterDate1" type="text" name="EnterDate1" size="11" maxlength="10" class="date-picker">
                  <?= gettext('To:') ?>&nbsp;</b></td><td><input id="EnterDate2" type="text" name="EnterDate2" size="11" maxlength="10" value="<?php echo date('Y-m-d'); ?>" class="date-picker">
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
        <div class="card-header with-border">
          <h3 class="card-title"><?= gettext('Output Method:') ?></h3>
        </div>
        <div class="card-body">
          <select name="Format">
            <option value="Default"><?= gettext('CSV Individual Records') ?></option>
            <option value="Rollup"><?= gettext('CSV Combine Families') ?></option>
            <option value="AddToCart"><?= gettext('Add Individuals to Cart') ?></option>
          </select>

          <label><?= gettext('Skip records with incomplete mail address') ?></label><input type="checkbox" name="SkipIncompleteAddr" value="1">

          <input type="submit" class="btn btn-default" value=<?= '"' . gettext('Create File') . '"' ?> name="Submit"></td>

        </div>
      </div>
    </div>
  </div>

</form>

<?php require 'Include/Footer.php' ?>
