<?php
namespace ChurchCRM\dto;

use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonQuery;

class Cart
{
  public static function AddPerson($PersonID)
  {
    // make sure the cart array exists
    if (isset($_SESSION['aPeopleCart'])) {
        if (!in_array($PersonID, $_SESSION['aPeopleCart'], false)) {
            $_SESSION['aPeopleCart'][] = $PersonID;
        }
    } else {
        $_SESSION['aPeopleCart'][] = $PersonID;
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
      $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'], $aTempArray);
    }
  }
  
  public static function RemoveArray($aIDs)
  {
    // make sure the cart array exists
    // we can't remove anybody if there is no cart
    if (isset($_SESSION['aPeopleCart']) && is_array($aIDs)) {
        $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'], $aIDs);
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

}