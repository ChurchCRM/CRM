<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\WhyCame;
use ChurchCRM\model\ChurchCRM\WhyCameQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

$logger = LoggerUtils::getAppLogger();

$linkBack = RedirectUtils::getLinkBackFromRequest('');
$iPerson = InputUtils::filterInt($_GET['PersonID']);
$iWhyCameID = InputUtils::filterInt($_GET['WhyCameID']);

$person = PersonQuery::create()->findOneById($iPerson);
$per_FirstName = $person->getFirstName();
$per_LastName = $person->getLastName();

$sPageTitle = gettext('"Why Came" notes for ') . $per_FirstName . ' ' . $per_LastName;

// Is this the second pass?
if (isset($_POST['Submit'])) {
    $tJoin = InputUtils::legacyFilterInput($_POST['Join']);
    $tCome = InputUtils::legacyFilterInput($_POST['Come']);
    $tSuggest = InputUtils::legacyFilterInput($_POST['Suggest']);
    $tHearOfUs = InputUtils::legacyFilterInput($_POST['HearOfUs']);

    $whyCame = WhyCameQuery::create()->findOneByPerId($iPerson);
    if ($whyCame === null) {
        LoggerUtils::getAppLogger()->debug("person id" . $iPerson ." whycame id null");
        $whyCame = new WhyCame();
        $whyCame->setPerId($iPerson);
    }

    $whyCame
        ->setJoin($tJoin)
        ->setCome($tCome)
        ->setSuggest($tSuggest)
        ->setHearOfUs($tHearOfUs);
        
    $whyCame->save();

    if (isset($_POST['Submit'])) {
        // Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
        if ($linkBack != '') {
            RedirectUtils::redirect($linkBack);
        } else {
            //Send to the view of this pledge
            RedirectUtils::redirect('WhyCameEditor.php?PersonID=' . $iPerson . '&WhyCameID=' . $iWhyCameID . '&linkBack=' . $linkBack);
        }
    }
} else {
    $whyCames = WhyCameQuery::create()->findByPerId($iPerson);
    if (count($whyCames) > 0) {
        if (count($whyCames) > 1) {
            $logger->warning('multiple why came records found for person', ['personId' => $iPerson]);
        }
        $whyCame = $whyCames[0];

        $iWhyCameID = $whyCame->getId();
        $tJoin = $whyCame->getJoin();
        $tCome = $whyCame->getCome();
        $tSuggest = $whyCame->getSuggest();
        $tHearOfUs = $whyCame->getHearOfUs();
    }
}

require_once __DIR__ . '/Include/Header.php';

?>
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">
      <i class="fa-solid fa-comment me-2"></i>
      <?= gettext('"Why Came" Notes') ?>
    </h5>
  </div>
  <div class="card-body">
    <form method="post" action="WhyCameEditor.php?<?= 'PersonID=' . $iPerson . '&WhyCameID=' . $iWhyCameID . '&linkBack=' . $linkBack ?>" name="WhyCameEditor">
      <div class="mb-3">
        <label class="form-label"><?= gettext('Why did you come to the church?') ?></label>
        <textarea name="Join" class="form-control" rows="3"><?= InputUtils::escapeHTML($tJoin) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Why do you keep coming?') ?></label>
        <textarea name="Come" class="form-control" rows="3"><?= InputUtils::escapeHTML($tCome) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= gettext('Do you have any suggestions for us?') ?></label>
        <textarea name="Suggest" class="form-control" rows="3"><?= InputUtils::escapeHTML($tSuggest) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label"><?= gettext('How did you learn of the church?') ?></label>
        <textarea name="HearOfUs" class="form-control" rows="3"><?= InputUtils::escapeHTML($tHearOfUs) ?></textarea>
      </div>
      <div class="d-flex justify-content-between mt-4">
        <a href="<?= RedirectUtils::escapeRedirectUrl($linkBack, 'PersonView.php?PersonID=' . $iPerson) ?>" class="btn btn-secondary">
          <i class="fa-solid fa-ban me-1"></i>
          <?= gettext('Cancel') ?>
        </a>
        <button type="submit" class="btn btn-primary" name="Submit">
          <i class="fa-solid fa-floppy-disk me-1"></i>
          <?= gettext('Save') ?>
        </button>
      </div>
    </form>
  </div>
</div>
<?php
require_once __DIR__ . '/Include/Footer.php';
