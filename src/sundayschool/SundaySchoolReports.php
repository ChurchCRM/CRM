<?php

/*******************************************************************************
 *
 *  filename    : SundaySchoolReports.php
 *  last change : 2017-11-01
 *  description : form to invoke Sunday School reports
 *
 *  edited by S. Shaffer May/June 2006 - added capability to include multiple groups.  Group reports are printed with a page break between group selections.
 *  edited to add ORM code : Philippe Logel
 *
 ******************************************************************************/

// Include the function library
require '../Include/Config.php';
require '../Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use Propel\Runtime\ActiveQuery\Criteria;

// Get all the sunday school classes
$groups = GroupQuery::create()
                    ->orderByName(Criteria::ASC)
                    ->filterByType(4)
                    ->find();

// Set the page title and include HTML header
$sPageTitle = gettext('Sunday School Reports');
require '../Include/Header.php';

// Is this the second pass?
if (isset($_POST['SubmitPhotoBook']) || isset($_POST['SubmitClassList']) || isset($_POST['SubmitClassAttendance'])) {
    $iFYID = InputUtils::legacyFilterInput($_POST['FYID'], 'int');

    $dFirstSunday = InputUtils::legacyFilterInput($_POST['FirstSunday'], 'date');
    $dLastSunday = InputUtils::legacyFilterInput($_POST['LastSunday'], 'date');
    $dNoSchool1 = InputUtils::legacyFilterInput($_POST['NoSchool1'], 'date');
    $dNoSchool2 = InputUtils::legacyFilterInput($_POST['NoSchool2'], 'date');
    $dNoSchool3 = InputUtils::legacyFilterInput($_POST['NoSchool3'], 'date');
    $dNoSchool4 = InputUtils::legacyFilterInput($_POST['NoSchool4'], 'date');
    $dNoSchool5 = InputUtils::legacyFilterInput($_POST['NoSchool5'], 'date');
    $dNoSchool6 = InputUtils::legacyFilterInput($_POST['NoSchool6'], 'date');
    $dNoSchool7 = InputUtils::legacyFilterInput($_POST['NoSchool7'], 'date');
    $dNoSchool8 = InputUtils::legacyFilterInput($_POST['NoSchool8'], 'date');
    $iExtraStudents = InputUtils::legacyFilterInput($_POST['ExtraStudents'], 'int');
    $iExtraTeachers = InputUtils::legacyFilterInput($_POST['ExtraTeachers'], 'int');
    $_SESSION['idefaultFY'] = $iFYID;

    $bAtLeastOneGroup = false;

    if (!empty($_POST['GroupID'])) {
        $count = 0;
        foreach ($_POST['GroupID'] as $Grp) {
            $aGroups[$count++] = InputUtils::legacyFilterInput($Grp, 'int');
        }
        $aGrpID = implode(',', $aGroups);
        $bAtLeastOneGroup = true;
    }

    $allroles = InputUtils::legacyFilterInput($_POST['allroles']);
    $withPictures = InputUtils::legacyFilterInput($_POST['withPictures']);

    $currentUser = UserQuery::create()->findPk(AuthenticationManager::getCurrentUser()->getId());
    $currentUser->setCalStart($dFirstSunday);
    $currentUser->setCalEnd($dLastSunday);
    $currentUser->setCalNoSchool1($dNoSchool1);
    $currentUser->setCalNoSchool2($dNoSchool2);
    $currentUser->setCalNoSchool3($dNoSchool3);
    $currentUser->setCalNoSchool4($dNoSchool4);
    $currentUser->setCalNoSchool5($dNoSchool5);
    $currentUser->setCalNoSchool6($dNoSchool6);
    $currentUser->setCalNoSchool7($dNoSchool7);
    $currentUser->setCalNoSchool7($dNoSchool8);
    $currentUser->save();

    if ($bAtLeastOneGroup && isset($_POST['SubmitPhotoBook']) && $aGrpID != 0) {
        RedirectUtils::redirect('Reports/PhotoBook.php?GroupID=' . $aGrpID . '&FYID=' . $iFYID . '&FirstSunday=' . $dFirstSunday . '&LastSunday=' . $dLastSunday . '&AllRoles=' . $allroles . '&pictures=' . $withPictures);
    } elseif ($bAtLeastOneGroup && isset($_POST['SubmitClassList']) && $aGrpID != 0) {
        RedirectUtils::redirect('Reports/ClassList.php?GroupID=' . $aGrpID . '&FYID=' . $iFYID . '&FirstSunday=' . $dFirstSunday . '&LastSunday=' . $dLastSunday . '&AllRoles=' . $allroles . '&pictures=' . $withPictures);
    } elseif ($bAtLeastOneGroup && isset($_POST['SubmitClassAttendance']) && $aGrpID != 0) {
        $toStr = 'Reports/ClassAttendance.php?';
        //        $toStr .= "GroupID=" . $iGroupID;
        $toStr .= 'GroupID=' . $aGrpID;
        $toStr .= '&FYID=' . $iFYID;
        $toStr .= '&FirstSunday=' . $dFirstSunday;
        $toStr .= '&LastSunday=' . $dLastSunday;
        $toStr .= '&AllRoles=' . $allroles;
        $toStr .= '&withPictures=' . $withPictures;
        if ($dNoSchool1) {
            $toStr .= '&NoSchool1=' . $dNoSchool1;
        }
        if ($dNoSchool2) {
            $toStr .= '&NoSchool2=' . $dNoSchool2;
        }
        if ($dNoSchool3) {
            $toStr .= '&NoSchool3=' . $dNoSchool3;
        }
        if ($dNoSchool4) {
            $toStr .= '&NoSchool4=' . $dNoSchool4;
        }
        if ($dNoSchool5) {
            $toStr .= '&NoSchool5=' . $dNoSchool5;
        }
        if ($dNoSchool6) {
            $toStr .= '&NoSchool6=' . $dNoSchool6;
        }
        if ($dNoSchool7) {
            $toStr .= '&NoSchool7=' . $dNoSchool7;
        }
        if ($dNoSchool8) {
            $toStr .= '&NoSchool8=' . $dNoSchool8;
        }
        if ($iExtraStudents) {
            $toStr .= '&ExtraStudents=' . $iExtraStudents;
        }
        if ($iExtraTeachers) {
            $toStr .= '&ExtraTeachers=' . $iExtraTeachers;
        }
        RedirectUtils::redirect($toStr);
    } elseif (!$bAtLeastOneGroup || $aGrpID == 0) {
        echo "<p class=\"alert alert-danger\"><span class=\"fa fa-exclamation-triangle\"> " . gettext('At least one group must be selected to make class lists or attendance sheets.') . "</span></p>";
    }
} else {
    $iFYID = $_SESSION['idefaultFY'];
    $iGroupID = 0;
    $currentUser = UserQuery::create()->findPk(AuthenticationManager::getCurrentUser()->getId());

    if ($currentUser->getCalStart() != null) {
        $dFirstSunday = $currentUser->getCalStart()->format('Y-m-d');
    }
    if ($currentUser->getCalEnd() != null) {
        $dLastSunday = $currentUser->getCalEnd()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool1() != null) {
        $dNoSchool1 = $currentUser->getCalNoSchool1()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool2() != null) {
        $dNoSchool2 = $currentUser->getCalNoSchool2()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool3() != null) {
        $dNoSchool3 = $currentUser->getCalNoSchool3()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool4() != null) {
        $dNoSchool4 = $currentUser->getCalNoSchool4()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool5() != null) {
        $dNoSchool5 = $currentUser->getCalNoSchool5()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool6() != null) {
        $dNoSchool6 = $currentUser->getCalNoSchool6()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool7() != null) {
        $dNoSchool7 = $currentUser->getCalNoSchool7()->format('Y-m-d');
    }
    if ($currentUser->getCalNoSchool8() != null) {
        $dNoSchool8 = $currentUser->getCalNoSchool8()->format('Y-m-d');
    }

    $iExtraStudents = 0;
    $iExtraTeachers = 0;
}

$dFirstSunday = change_date_for_place_holder($dFirstSunday);
$dLastSunday = change_date_for_place_holder($dLastSunday);
$dNoSchool1 = change_date_for_place_holder($dNoSchool1);
$dNoSchool2 = change_date_for_place_holder($dNoSchool2);
$dNoSchool3 = change_date_for_place_holder($dNoSchool3);
$dNoSchool4 = change_date_for_place_holder($dNoSchool4);
$dNoSchool5 = change_date_for_place_holder($dNoSchool5);
$dNoSchool6 = change_date_for_place_holder($dNoSchool6);
$dNoSchool7 = change_date_for_place_holder($dNoSchool7);
$dNoSchool8 = change_date_for_place_holder($dNoSchool6);

?>
<div class="card">
  <div class="card-header with-border">
    <h3 class="card-title"><?= gettext('Report Details')?></h3>
  </div>
  <div class="card-body">
    <form method="post" action="SundaySchoolReports.php">

      <table class="table table-simple-padding" align="left">
        <tr>
          <td><?= gettext('Select Group')?>: <br/><?=gettext('To select multiple hold CTL') ?></td>
          <td>
            <?php
            // Create the group select drop-down
            echo '<select id="GroupID" name="GroupID[]" multiple size="8" onChange="UpdateRoles();"><option value="0">' . gettext('None') . '</option>';
            foreach ($groups as $group) {
                echo '<option value="' . $group->getID() . '">' . $group->getName() . '</option>';
            }
            echo '</select><br>';
            echo gettext('Multiple groups will have a Page Break between Groups<br>');
            echo '<input type="checkbox" Name="allroles" value="1" checked>';
            echo gettext('List all Roles (unchecked will list Teacher/Student roles only)') . "<br>";
            echo '<input type="checkbox" Name="withPictures" value="1" checked>';
            echo gettext('With Photos');
            ?>
          </td>
        </tr>

        <tr>
          <td><?= gettext('Fiscal Year') ?>:</td>
          <td class="TextColumnWithBottomBorder">
            <?php PrintFYIDSelect($iFYID, 'FYID') ?>
          </td>
        </tr>

        <tr>
          <td><?= gettext('First Sunday') ?>:</td>
          <td><input type="text" name="FirstSunday" value="<?= $dFirstSunday ?>" maxlength="10" id="FirstSunday" size="11"  class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('Last Sunday') ?>:</td>
          <td><input type="text" name="LastSunday" value="<?= $dLastSunday ?>" maxlength="10" id="LastSunday" size="11"  class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool1" value="<?= $dNoSchool1 ?>" maxlength="10" id="NoSchool1" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool2" value="<?= $dNoSchool2 ?>" maxlength="10" id="NoSchool2" size="11"  class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool3" value="<?= $dNoSchool3 ?>" maxlength="10" id="NoSchool3" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool4" value="<?= $dNoSchool4 ?>" maxlength="10" id="NoSchool4" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool5" value="<?= $dNoSchool5 ?>" maxlength="10" id="NoSchool5" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool6" value="<?= $dNoSchool6 ?>" maxlength="10" id="NoSchool6" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool7" value="<?= $dNoSchool7 ?>" maxlength="10" id="NoSchool7" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('No Sunday School') ?>:</td>
          <td><input type="text" name="NoSchool8" value="<?= $dNoSchool8 ?>" maxlength="10" id="NoSchool8" size="11" class="date-picker" placeholder="<?= SystemConfig::getValue("sDatePickerPlaceHolder") ?>"></td>
        </tr>

        <tr>
          <td><?= gettext('Extra Students') ?>:</td>
          <td><input type="text" name="ExtraStudents" value="<?= $iExtraStudents ?>" id="ExtraStudents" size="11">&nbsp;</td>
        </tr>
        <tr>
          <td><?= gettext('Extra Teachers') ?>:</td>
          <td><input type="text" name="ExtraTeachers" value="<?= $iExtraTeachers ?>" id="ExtraTeachers" size="11">&nbsp;</td>
        </tr>
        <tr>
          <td><br/></td>
        </tr>
        <tr>
          <td width="65%">
              <div class="col-md-4">
                  <input type="submit" class="btn btn-primary" name="SubmitClassList" value="<?= gettext('Create Class List') ?>">
              </div>
              <div class="col-md-4">
                  <input type="submit" class="btn btn-info" name="SubmitClassAttendance" value="<?= gettext('Create Attendance Sheet') ?>">
              </div>
              <div class="col-md-4">
                  <input type="submit" class="btn btn-danger" name="SubmitPhotoBook" value="<?= gettext('Create PhotoBook') ?>">
              </div>
          </td>
          <td width="35%">
            <div class="col-rd-12">
                <input type="button" style="align=right" class="btn btn-default" name="Cancel" value="<?= gettext('Cancel') ?>" onclick="javascript:document.location = 'Menu.php';">
            </div>
          </td>
        </tr>
      </table>
    </form>
  </div>
</div>

<?php
require '../Include/Footer.php';
?>
