<?php

require_once 'Include/Config.php';
require_once 'Include/Functions.php';

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\WhyCame;
use ChurchCRM\model\ChurchCRM\WhyCameQuery;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\RedirectUtils;

$logger = LoggerUtils::getAppLogger();

$linkBack = InputUtils::legacyFilterInput($_GET['linkBack']);
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
        LoggerUtils::getAppLogger()->info("person id " . $iPerson . " whycame id null" );
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
            RedirectUtils::redirect('WhyCameEditor.php?PersonID=' . $iPerson . '&WhyCameID=' . $iWhyCameID . '&linkBack=', $linkBack);
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

require_once 'Include/Header.php';

?>
<div class="card">
  <div class="card-body">

    <form method="post" action="WhyCameEditor.php?<?= 'PersonID=' . $iPerson . '&WhyCameID=' . $iWhyCameID . '&linkBack=' . $linkBack ?>" name="WhyCameEditor">
      <table class="table table-simple-padding">
        <tr>
          <td class="LabelColumn"><?= gettext('Why did you come to the church?') ?></td>
          <td><textarea name="Join" rows="3" cols="90"><?= $tJoin ?></textarea></td>
        </tr>
        <tr>
          <td class="LabelColumn"><?= gettext('Why do you keep coming?') ?></td>
          <td><textarea name="Come" rows="3" cols="90"><?= $tCome ?></textarea></td>
        </tr>
        <tr>
          <td class="LabelColumn"><?= gettext('Do you have any suggestions for us?') ?></td>
          <td><textarea name="Suggest" rows="3" cols="90"><?= $tSuggest ?></textarea></td>
        </tr>
        <tr>
          <td class="LabelColumn"><?= gettext('How did you learn of the church?') ?></td>
          <td><textarea name="HearOfUs" rows="3" cols="90"><?= $tHearOfUs ?></textarea></td>
        </tr>
        <tr>
          <td colspan="2" class="text-center">
            <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="Submit">
            <input type="button" class="btn btn-secondary" value="<?= gettext('Cancel') ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) {
                echo $linkBack;
                                                                } else {
                                                                    echo 'PersonView.php?PersonID=' . $iPerson;
                                                                } ?>';">
          </td>
        </tr>
      </table>
    </form>
  </div>
</div>
<?php
require_once 'Include/Footer.php';
