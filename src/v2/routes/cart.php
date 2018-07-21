<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\PhpRenderer;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;


$app->group('/cart', function () {
  $this->get('/', 'getCartView');
  $this->get('', 'getCartView');
});

function getCartView(Request $request, Response $response, array $args) {
  $renderer = new PhpRenderer('templates/cart/');

  $pageArgs = [
      'sRootPath' => SystemURLs::getRootPath(),
      'sPageTitle' => gettext('View Your Cart'),
      'PageJSVars' => []
  ];

  if (!Cart::HasPeople()) {
    return $renderer->render($response, 'cartempty.php', $pageArgs);
  } else {
    array_merge($pageArgs, array(
        'sEmailLink' => getEmailLinks(),
        '$sPhoneLink' => getSMSCartLink(),
        'iNumPersons' => Cart::CountPeople(),
        'iNumFamilies' => Cart::CountFamilies(),
        'cartPeople' => Cart::getCartPeople()
    ));
    return $renderer->render($response, 'cartview.php', $pageArgs);
  }
}

function getEmailLinks() {
  // Note: This will email entire group, even if a specific role is currently selected.
  $sSQL = "SELECT per_Email, fam_Email
                    FROM person_per
                    LEFT JOIN person2group2role_p2g2r ON per_ID = p2g2r_per_ID
                    LEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID
                    LEFT JOIN family_fam ON per_fam_ID = family_fam.fam_ID
                WHERE per_ID NOT IN (SELECT per_ID FROM person_per INNER JOIN record2property_r2p ON r2p_record_ID = per_ID INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not Email') AND per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ')';
  $rsEmailList = RunQuery($sSQL);
  $sEmailLink = '';
  while (list($per_Email, $fam_Email) = mysqli_fetch_row($rsEmailList)) {
    $sEmail = SelectWhichInfo($per_Email, $fam_Email, false);
    if ($sEmail) {
      /* if ($sEmailLink) // Don't put delimiter before first email
        $sEmailLink .= $sMailtoDelimiter; */
      // Add email only if email address is not already in string
      if (!stristr($sEmailLink, $sEmail)) {
        $sEmailLink .= $sEmail .= $sMailtoDelimiter;
      }
    }
  }
  if ($sEmailLink) {
    // Add default email if default email has been set and is not already in string
    if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
      $sEmailLink .= $sMailtoDelimiter . SystemConfig::getValue('sToEmailAddress');
    }
    $sEmailLink = urlencode($sEmailLink);  // Mailto should comply with RFC 2368
    return $sEmailLink;
  }
}

function getSMSCartLink() {
  //Text Cart Link
  $sSQL = "SELECT per_CellPhone, fam_CellPhone FROM person_per LEFT JOIN family_fam ON person_per.per_fam_ID = family_fam.fam_ID WHERE per_ID NOT IN (SELECT per_ID FROM person_per INNER JOIN record2property_r2p ON r2p_record_ID = per_ID INNER JOIN property_pro ON r2p_pro_ID = pro_ID AND pro_Name = 'Do Not SMS') AND per_ID IN (" . ConvertCartToString($_SESSION['aPeopleCart']) . ')';
  $rsPhoneList = RunQuery($sSQL);
  $sPhoneLink = '';
  $sCommaDelimiter = ', ';

  while (list($per_CellPhone, $fam_CellPhone) = mysqli_fetch_row($rsPhoneList)) {
    $sPhone = SelectWhichInfo($per_CellPhone, $fam_CellPhone, false);
    if ($sPhone) {
      /* if ($sPhoneLink) // Don't put delimiter before first phone
        $sPhoneLink .= $sCommaDelimiter; */
      // Add phone only if phone is not already in string
      if (!stristr($sPhoneLink, $sPhone)) {
        $sPhoneLink .= $sPhone .= $sCommaDelimiter;
      }
    }
  }
}
