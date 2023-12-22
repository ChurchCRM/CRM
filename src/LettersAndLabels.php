<?php

/*******************************************************************************
 *
 *  filename    : LettersAndLabels.php
 *  website     : https://churchcrm.io
 *
 *  Contributors:
 *  2006 Ed Davis
 *
 *
 *  Copyright 2006 Contributors
  *

 *
 ******************************************************************************/

// Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';
require 'Include/LabelFunctions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Set the page title and include HTML header
$sPageTitle = gettext('Letters and Mailing Labels');
require 'Include/Header.php';

// Is this the second pass?
if (isset($_POST['SubmitNewsLetter']) || isset($_POST['SubmitConfirmReport']) || isset($_POST['SubmitConfirmLabels']) || isset($_POST['SubmitConfirmReportEmail'])) {
    $sLabelFormat = InputUtils::legacyFilterInput($_POST['labeltype']);
    $sFontInfo = $_POST['labelfont'];
    $sFontSize = $_POST['labelfontsize'];
    $bRecipientNamingMethod = $_POST['recipientnamingmethod'];
    $sLabelInfo = '&labelfont=' . urlencode($sFontInfo) . '&labelfontsize=' . $sFontSize . "&recipientnamingmethod=" . $bRecipientNamingMethod;

    if (isset($_POST['SubmitNewsLetter'])) {
        RedirectUtils::redirect('Reports/NewsLetterLabels.php?labeltype=' . $sLabelFormat . $sLabelInfo);
    } elseif (isset($_POST['SubmitConfirmLabels'])) {
        RedirectUtils::redirect('Reports/ConfirmLabels.php?labeltype=' . $sLabelFormat . $sLabelInfo);
    }
} else {
    $sLabelFormat = 'Tractor';
}
?>
<div class="row">
  <div class="col-lg-12">
    <div class="card">
      <div class="card-header with-border">
        <h3 class="card-title"><?= gettext('People Reports')?></h3>
      </div>
      <div class="card-body">
        <form method="post" action="LettersAndLabels.php">
            <div class="table-responsive">

          <table class="table" cellpadding="3" align="left">
<?php
LabelSelect('labeltype');
FontSelect('labelfont');
FontSizeSelect('labelfontsize');
?>
            <tr>
              <td class="LabelColumn"><?= gettext("Recipient Naming Method")?>:</td>
              <td class="TextColumn">
                <select name="recipientnamingmethod">
                  <option value="salutationutility"><?= gettext("Salutation Utility") ?></option>
                  <option value="familyname"><?= gettext("Family Name") ?></option>
                </select>
              </td>
            </tr>

          </table>
            </div>
            <div>
              <input type="submit" class="btn btn-default" name="SubmitNewsLetter" value="<?= gettext('Newsletter labels') ?>">
              <input type="submit" class="btn btn-default" name="SubmitConfirmLabels" value="<?= gettext('Confirm data labels') ?>">
              <input type="button" class="btn btn-warning" name="Cancel" value="<?= gettext('Cancel') ?>" onclick="javascript:document.location = 'Menu.php';">
            </div>

        </form>
      </div>
    </div>
  </div>
</div>

<?php require 'Include/Footer.php' ?>
