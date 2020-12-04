<?php
namespace ChurchCRM\dto;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\GroupQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery;

class Cart
{

  private static function CheckCart()
  {
    if (!isset($_SESSION['aPeopleCart']))
    {
      $_SESSION['aPeopleCart'] = [];
    }
  }
  public static function AddPerson($PersonID)
  {
    self::CheckCart();
    if (!is_numeric($PersonID))
    {
      throw new \Exception (gettext("PersonID for Cart must be numeric"),400);
    }
    if ($PersonID !== null && !in_array($PersonID, $_SESSION['aPeopleCart'], false)) {
      array_push($_SESSION['aPeopleCart'], (int)$PersonID);
    }
  }

  public static function AddPersonArray($PersonArray)
  {
    foreach($PersonArray as $PersonID)
    {
      Cart::AddPerson($PersonID);
    }

  }

  public static function AddGroup($GroupID)
  {
    if (!is_numeric($GroupID))
    {
      throw new \Exception (gettext("GroupID for Cart must be numeric"),400);
    }
    $GroupMembers = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($GroupID)
            ->find();
    foreach ($GroupMembers as $GroupMember)
    {
      Cart::AddPerson($GroupMember->getPersonId());
    }
  }

  public static function AddFamily($FamilyID)
  {
    if (!is_numeric($FamilyID))
    {
      throw new \Exception (gettext("FamilyID for Cart must be numeric"),400);
    }
    $FamilyMembers = PersonQuery::create()
            ->filterByFamId($FamilyID)
            ->find();
    foreach ($FamilyMembers as $FamilyMember)
    {
      Cart::AddPerson($FamilyMember->getId());
    }
  }

  public static function IntersectArrayWithPeopleCart($aIDs)
  {
      if (isset($_SESSION['aPeopleCart']) && is_array($aIDs)) {
          $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'], $aIDs);
      }
  }

  public static function RemovePerson($PersonID)
  {
    // make sure the cart array exists
    // we can't remove anybody if there is no cart
    if (!is_numeric($PersonID))
    {
      throw new \Exception (gettext("PersonID for Cart must be numeric"),400);
    }
    if (isset($_SESSION['aPeopleCart'])) {
      $aTempArray[] = $PersonID; // the only element in this array is the ID to be removed
      $_SESSION['aPeopleCart'] = array_values(array_diff($_SESSION['aPeopleCart'], $aTempArray));
    }
  }

  public static function RemovePersonArray($aIDs)
  {
    // make sure the cart array exists
    // we can't remove anybody if there is no cart
    if (isset($_SESSION['aPeopleCart']) && is_array($aIDs)) {
        $_SESSION['aPeopleCart'] = array_values(array_diff($_SESSION['aPeopleCart'], $aIDs));
    }
  }

  public static function RemoveGroup($GroupID)
  {
    if (!is_numeric($GroupID))
    {
      throw new \Exception (gettext("GroupID for Cart must be numeric"),400);
    }
    $GroupMembers = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($GroupID)
            ->find();
    foreach ($GroupMembers as $GroupMember)
    {
      Cart::RemovePerson($GroupMember->getPersonId());
    }
  }

  public static function HasPeople()
  {
    return array_key_exists('aPeopleCart', $_SESSION) && count($_SESSION['aPeopleCart']) != 0;
  }

  public static function CountPeople()
  {
    return count($_SESSION['aPeopleCart']);
  }

  public static function ConvertCartToString($aCartArray)
  {
      // Implode the array
    $sCartString = implode(',', $aCartArray);

    // Make sure the comma is chopped off the end
    if (mb_substr($sCartString, strlen($sCartString) - 1, 1) == ',') {
        $sCartString = mb_substr($sCartString, 0, strlen($sCartString) - 1);
    }

    // Make sure there are no duplicate commas
    $sCartString = str_replace(',,', '', $sCartString);

      return $sCartString;
  }

  public static function CountFamilies()
  {
    $persons = PersonQuery::create()
            ->distinct()
            ->select(['Person.FamId'])
            ->filterById($_SESSION['aPeopleCart'])
            ->orderByFamId()
            ->find();
    return $persons->count();
  }

  public static function EmptyToGroup($GroupID,$RoleID)
  {
    $iCount = 0;

    $group = GroupQuery::create()->findOneById($GroupID);

    if($RoleID == 0)
    {
      $RoleID = $group->getDefaultRole();
    }

    while ($element = each($_SESSION['aPeopleCart'])) {
      $personGroupRole = Person2group2roleP2g2rQuery::create()
        ->filterByGroupId($GroupID)
        ->filterByPersonId($_SESSION['aPeopleCart'][$element['key']])
        ->findOneOrCreate()
        ->setRoleId($RoleID)
        ->save();

      /*
      This part of code should be done
    	*/
    	// Check if this group has special properties
      /*      $sSQL = 'SELECT grp_hasSpecialProps FROM group_grp WHERE grp_ID = '.$iGroupID;
            $rsTemp = RunQuery($sSQL);
            $rowTemp = mysqli_fetch_row($rsTemp);
            $bHasProp = $rowTemp[0];

            if ($bHasProp == 'true') {
                $sSQL = 'INSERT INTO groupprop_'.$iGroupID." (per_ID) VALUES ('".$iPersonID."')";
                RunQuery($sSQL);
            }	*/

      $iCount += 1;
    }

    $_SESSION['aPeopleCart'] = [];
  }

  public static function getCartPeople() {

    $people = PersonQuery::create()
            ->filterById($_SESSION['aPeopleCart'])
            ->find();
    return $people;
  }

  public static function getEmailLink() {
    /* @var $cartPerson ChurchCRM\Person */
    $people = Cart::getCartPeople();
    $emailAddressArray = array();
    foreach($people as $cartPerson) {
      if (!empty($cartPerson->getEmail())) {
        array_push($emailAddressArray, $cartPerson->getEmail());
      }
    }
    $delimiter = AuthenticationManager::GetCurrentUser()->getUserConfigString("sMailtoDelimiter");
    $sEmailLink = implode($delimiter, array_unique(array_filter($emailAddressArray)));
    if (!empty(SystemConfig::getValue('sToEmailAddress')) && !stristr($sEmailLink, SystemConfig::getValue('sToEmailAddress'))) {
      $sEmailLink .= $delimiter . SystemConfig::getValue('sToEmailAddress');
    }
    return $sEmailLink;
  }

  public static function getSMSLink() {

     /* @var $cartPerson ChurchCRM\Person */
    $people = Cart::getCartPeople();
    $SMSNumberArray = array();
    foreach($people as $cartPerson)
    {
      if (!empty($cartPerson->getCellPhone())) {
        array_push($SMSNumberArray, $cartPerson->getCellPhone());
      }
    }
    $sSMSLink = implode(",", $SMSNumberArray);
    return $sSMSLink;
  }

  public static function EmptyAll() {
    Cart::RemovePersonArray($_SESSION['aPeopleCart']);
  }
}
