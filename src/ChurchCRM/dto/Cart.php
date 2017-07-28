<?php
namespace ChurchCRM\dto;

use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\GroupQuery;

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
    if ($PersonID !== null && !in_array($PersonID, $_SESSION['aPeopleCart'], false)) {
      array_push($_SESSION['aPeopleCart'], $PersonID);
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
    $Group = GroupQuery::create()->findOneById($GroupID);
    while ($element = each($_SESSION['aPeopleCart'])) {
      $personGroupRole = Person2group2roleP2g2rQuery::create()
        ->filterByGroupId($GroupID)
        ->filterByPersonId($_SESSION['aPeopleCart'][$element['key']])
        ->filterByRoleId($RoleID)
        ->findOneOrCreate()
        ->setPersonId($_SESSION['aPeopleCart'][$element['key']])
        ->setRoleId($RoleID)
        ->setGroupId($GroupID)
        ->save();
      $iCount += 1;
    }
    $_SESSION['aPeopleCart'] = [];
  }
  
}