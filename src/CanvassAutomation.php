<?php

/*******************************************************************************
 *
 *  filename    : CanvassAutomation.php
 *  last change : 2005-02-21
 *  website     : https://churchcrm.io
 *  copyright   : Copyright 2001-2005 Deane Barker, Chris Gebhardt, Michael Wilt, Tim Dearborn
  *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

require 'Include/CanvassUtilities.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

//Set the page title
$sPageTitle = gettext('Canvass Automation');

// Security: User must have canvasser permission to use this form
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isCanvasserEnabled());

$iFYID = CurrentFY();
if (array_key_exists('idefaultFY', $_SESSION)) {
    $iFYID = $_SESSION['idefaultFY'];
}
if (array_key_exists('FYID', $_POST)) {
    $iFYID = InputUtils::legacyFilterInput($_POST['FYID'], 'int');
} // Use FY from the form if it was set

$_SESSION['idefaultFY'] = $iFYID; // Remember default fiscal year

$processNews = '';

// Service the action buttons
if (isset($_POST['SetDefaultFY'])) {
    if (isset($_POST['SetDefaultFYConfirm'])) {
        $processNews = CanvassSetDefaultFY($iFYID);
    } else {
        $processNews = gettext('Not confirmed.');
    }
}
if (isset($_POST['AssignCanvassers'])) {
    if (isset($_POST['AssignCanvassersConfirm'])) {
        $processNews = CanvassAssignCanvassers(gettext('Canvassers'));
    } else {
        $processNews = gettext('Not confirmed.');
    }
}
if (isset($_POST['AssignNonPledging'])) {
    if (isset($_POST['AssignNonPledgingConfirm'])) {
        $processNews = CanvassAssignNonPledging(gettext('BraveCanvassers'), $iFYID);
    } else {
        $processNews = gettext('Not confirmed.');
    }
}
if (isset($_POST['ClearCanvasserAssignments'])) {
    if (isset($_POST['ClearCanvasserAssignmentsConfirm'])) {
        CanvassClearCanvasserAssignments();
        $processNews = gettext('Cleared all canvasser assignments.');
    } else {
        $processNews = gettext('Not confirmed.');
    }
}
if (isset($_POST['SetAllOkToCanvass'])) {
    if (isset($_POST['SetAllOkToCanvassConfirm'])) {
        CanvassSetAllOkToCanvass();
        $processNews = gettext('Set Ok To Canvass for all families.');
    } else {
        $processNews = gettext('Not confirmed.');
    }
}
if (isset($_POST['ClearAllOkToCanvass'])) {
    if (isset($_POST['ClearAllOkToCanvassConfirm'])) {
        CanvassClearAllOkToCanvass();
        $processNews = gettext('Disabled Ok To Canvass for all families.');
    } else {
        $processNews = gettext('ClearAllOkToCanvass button not confirmed.');
    }
}
if (isset($_POST['BriefingSheets'])) {
    RedirectUtils::redirect('Reports/CanvassReports.php?FYID=' . $iFYID . '&WhichReport=Briefing');
}
if (isset($_POST['ProgressReport'])) {
    RedirectUtils::redirect('Reports/CanvassReports.php?FYID=' . $iFYID . '&WhichReport=Progress');
}
if (isset($_POST['SummaryReport'])) {
    RedirectUtils::redirect('Reports/CanvassReports.php?FYID=' . $iFYID . '&WhichReport=Summary');
}
if (isset($_POST['NotInterestedReport'])) {
    RedirectUtils::redirect('Reports/CanvassReports.php?FYID=' . $iFYID . '&WhichReport=NotInterested');
}

require 'Include/Header.php';

if ($processNews != '') {
    ?>
  <div class="alert alert-warning alert-dismissable">
    <i class="fa fa-info"></i>
    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
    <strong><span style="color: red;"><?= $processNews ?></span></strong>
  </div>
    <?php
}
?>

<div class="row">
  <div class="col-lg-12">
    <div class="card">
      <div class="card-header with-border">
        <h3 class="card-title"><?= gettext('Report Details') ?></h3>
      </div>
      <div class="card-body">
        <form method="post" action="CanvassAutomation.php" name="CanvassAutomation">

          <p><?= gettext('Fiscal Year:') ?>
            <?php PrintFYIDSelect($iFYID, 'FYID') ?>
          </p>

          <table border width="100%" align="left">
            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Set default fiscal year') ?>"
                       name="SetDefaultFY">
              </td>
              <td align="left" width="75%">
                <p><input type="checkbox" name="SetDefaultFYConfirm"><?= gettext('Check to confirm') ?></p>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Assign Canvassers') ?>"
                       name="AssignCanvassers">
              </td>
              <td align="left" width="75%">
                <?= gettext('Randomly assign canvassers to all Families.  The Canvassers are taken from the &quot;Canvassers&quot; Group.') ?>
                <p><input type="checkbox" name="AssignCanvassersConfirm"><?= gettext('Check to confirm') ?></p>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Assign To Non Pledging') ?>"
                       name="AssignNonPledging">
              </td>
              <td align="left" width="75%">
                <?= gettext('Randomly assign canvassers to non-pledging Families.  The Canvassers are taken from the &quot;BraveCanvassers&quot; Group.') ?>
                <p><input type="checkbox" name="AssignNonPledgingConfirm"><?= gettext('Check to confirm') ?></p>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Clear Canvasser Assignments') ?>"
                       name="ClearCanvasserAssignments">
              </td>
              <td align="left" width="75%">
                <?= gettext('Clear all the canvasser assignments for all families.') ?>  <p><?= gettext('Important note: this will lose any canvasser assignments that have been made by hand.') ?></p>
                <input type="checkbox" name="ClearCanvasserAssignmentsConfirm"><?= gettext('Check to confirm') ?>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Enable Canvass for All Families') ?>"
                       name="SetAllOkToCanvass">
              </td>
              <td align="left" width="75%">
                <?= gettext('Turn on the &quot;Ok To Canvass&quot; field for all Families.') ?>  <p><?= gettext('Important note: this will lose any &quot;Ok To Canvass&quot; fields that have been set by hand.'); ?></p>
                <input type="checkbox" name="SetAllOkToCanvassConfirm"><?= gettext('Check to confirm') ?>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Disable Canvass for All Families') ?>"
                       name="ClearAllOkToCanvass">
              </td>
              <td align="left" width="75%">
                <?= gettext('Turn off the &quot;Ok To Canvass&quot; field for all Families') ?>  <p><?= gettext('Important note: this will lose any &quot;Ok To Canvass&quot; fields that have been set by hand.'); ?></p>
                <input type="checkbox" name="ClearAllOkToCanvassConfirm"><?= gettext('Check to confirm') ?>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Briefing Sheets') ?>"
                       name="BriefingSheets">
              </td>
              <td align="left" width="75%">
                <?= gettext('Generate a PDF containing briefing sheets for all Families, sorted by canvasser.') ?>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Progress Report') ?>"
                       name="ProgressReport">
              </td>
              <td align="left" width="75%">
                <?= gettext('Generate a PDF containing a progress report.  The progress report includes information on the overall progress of the canvass, and the progress of individual canvassers.') ?>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Summary Report') ?>"
                       name="SummaryReport">
              </td>
              <td align="left" width="75%">
                <?= gettext('Generate a PDF containing a summary report.  The summary report includes comments extracted from the canvass data.') ?>
              </td>
            </tr>

            <tr>
              <td align="center" width="25%">
                <input type="submit" class="btn btn-default" value="<?= gettext('Not Interested Report') ?>"
                       name="NotInterestedReport">
              </td>
              <td align="left" width="75%">
                <?= gettext('Generate a PDF containing a report of the families marked &quot;Not Interested&quot; by the canvasser.') ?>
              </td>
            </tr>
          </table>
        </form>
      </div>
    </div>

  </div>
</div>
<?php require 'Include/Footer.php' ?>
