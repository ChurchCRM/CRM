<?php
/*******************************************************************************
 *
 *  filename    : WhyCameEditor.php
 *  last change : 2004-6-12
 *  website     : http://www.churchcrm.io
 *  copyright   : Copyright 2001, 2002, 2003 Deane Barker, Chris Gebhardt, Michael Wilt
 *




 *
 ******************************************************************************/

//Include the function library
require 'Include/Config.php';
require 'Include/Functions.php';

use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

$linkBack = InputUtils::LegacyFilterInput($_GET['linkBack']);
$iPerson = InputUtils::LegacyFilterInput($_GET['PersonID']);
$iWhyCameID = InputUtils::LegacyFilterInput($_GET['WhyCameID']);

//Get name
$sSQL = 'SELECT per_FirstName, per_LastName FROM person_per where per_ID = '.$iPerson;
$rsPerson = RunQuery($sSQL);
extract(mysqli_fetch_array($rsPerson));

$sPageTitle = gettext('"Why Came" notes for ').$per_FirstName.' '.$per_LastName;

//Is this the second pass?
if (isset($_POST['Submit'])) {
    $tJoin = InputUtils::LegacyFilterInput($_POST['Join']);
    $tCome = InputUtils::LegacyFilterInput($_POST['Come']);
    $tSuggest = InputUtils::LegacyFilterInput($_POST['Suggest']);
    $tHearOfUs = InputUtils::LegacyFilterInput($_POST['HearOfUs']);

    // New input (add)
    if (strlen($iWhyCameID) < 1) {
        $sSQL = 'INSERT INTO whycame_why (why_per_ID, why_join, why_come, why_suggest, why_hearOfUs)
				VALUES ('.$iPerson.', "'.$tJoin.'", "'.$tCome.'", "'.$tSuggest.'", "'.$tHearOfUs.'")';

    // Existing record (update)
    } else {
        $sSQL = 'UPDATE whycame_why SET why_join = "'.$tJoin.'", why_come = "'.$tCome.'", why_suggest = "'.$tSuggest.'", why_hearOfUs = "'.$tHearOfUs.'" WHERE why_per_ID = '.$iPerson;
    }

    //Execute the SQL
    RunQuery($sSQL);

    if (isset($_POST['Submit'])) {
        // Check for redirection to another page after saving information: (ie. PledgeEditor.php?previousPage=prev.php?a=1;b=2;c=3)
        if ($linkBack != '') {
            RedirectUtils::Redirect($linkBack);
        } else {
            //Send to the view of this pledge
            RedirectUtils::Redirect('WhyCameEditor.php?PersonID='.$iPerson.'&WhyCameID='.$iWhyCameID.'&linkBack=', $linkBack);
        }
    }
} else {
    $sSQL = 'SELECT * FROM whycame_why WHERE why_per_ID = '.$iPerson;
    $rsWhyCame = RunQuery($sSQL);
    if (mysqli_num_rows($rsWhyCame) > 0) {
        extract(mysqli_fetch_array($rsWhyCame));

        $iWhyCameID = $why_ID;
        $tJoin = $why_join;
        $tCome = $why_come;
        $tSuggest = $why_suggest;
        $tHearOfUs = $why_hearOfUs;
    } else {
    }
}

require 'Include/Header.php';

?>
<div class="box">
  <div class="box-body">

    <form method="post" action="WhyCameEditor.php?<?= 'PersonID='.$iPerson.'&WhyCameID='.$iWhyCameID.'&linkBack='.$linkBack ?>" name="WhyCameEditor">
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
          <td colspan="2" align="center">
            <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="Submit">
            <input type="button" class="btn btn-default" value="<?= gettext('Cancel') ?>" name="Cancel" onclick="javascript:document.location='<?php if (strlen($linkBack) > 0) {
    echo $linkBack;
} else {
    echo 'PersonView.php?PersonID='.$iPerson;
} ?>';">
          </td>
        </tr>
    </form>
    </table>
  </div>
</div>
<?php require 'Include/Footer.php' ?>
