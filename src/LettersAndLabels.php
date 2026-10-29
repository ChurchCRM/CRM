<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/PageInit.php';
require_once __DIR__ . '/Include/LabelFunctions.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;
use ChurchCRM\view\PageHeader;

$sPageTitle = gettext('Letters and Mailing Labels');
$sPageSubtitle = gettext('Generate mailing labels and form letters');
$aBreadcrumbs = PageHeader::breadcrumbs([
    [gettext('People Reports'), '/people/reports'],
    [gettext('Letters and Labels')],
]);
require_once __DIR__ . '/Include/Header.php';

// Is this the second pass?
if (isset($_POST['SubmitNewsLetter']) || isset($_POST['SubmitConfirmLabels'])) {
    $sLabelQuery = http_build_query([
        'labeltype' => InputUtils::legacyFilterInput($_POST['labeltype']),
        'labelfont' => $_POST['labelfont'] ?? '',
        'labelfontsize' => $_POST['labelfontsize'] ?? '',
        'recipientnamingmethod' => $_POST['recipientnamingmethod'] ?? '',
    ]);

    if (isset($_POST['SubmitNewsLetter'])) {
        RedirectUtils::redirect('people/labels/newsletter?' . $sLabelQuery);
    } elseif (isset($_POST['SubmitConfirmLabels'])) {
        RedirectUtils::redirect('people/labels/confirm?' . $sLabelQuery);
    }
} else {
    $sLabelFormat = 'Tractor';
}
?>
<div class="row">
  <div class="col-lg-12">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><?= gettext('People Reports')?></h3>
      </div>
      <div class="card-body">
        <form method="post" action="LettersAndLabels.php">
            <div class="table-responsive">

          <table class="table">
<?php
LabelSelect('labeltype');
FontSelect('labelfont');
FontSizeSelect('labelfontsize');
?>
            <tr>
              <td class="text-secondary"><?= gettext("Recipient Naming Method")?>:</td>
              <td>
                <select name="recipientnamingmethod" class="form-select">
                  <option value="salutationutility"><?= gettext("Salutation Utility") ?></option>
                  <option value="familyname"><?= gettext("Family Name") ?></option>
                </select>
              </td>
            </tr>

          </table>
            </div>
            <div>
              <input type="submit" class="btn btn-secondary" name="SubmitNewsLetter" value="<?= gettext('Newsletter labels') ?>">
              <input type="submit" class="btn btn-secondary" name="SubmitConfirmLabels" value="<?= gettext('Confirm data labels') ?>">
              <a class="btn btn-warning" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/v2/dashboard') ?>"><?= gettext('Cancel') ?></a>
            </div>

        </form>
      </div>
    </div>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
