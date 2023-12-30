<?php

namespace ChurchCRM\dto;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;

class Cart
{
    private static function checkCart(): void
    {
        if (!isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = [];
        }
    }

    public static function addPerson($PersonID): void
    {
        self::checkCart();
        if (!is_numeric($PersonID)) {
            throw new \Exception(gettext('PersonID for Cart must be numeric'), 400);
        }
        if (!in_array($PersonID, $_SESSION['aPeopleCart'], false)) {
            $_SESSION['aPeopleCart'][] = (int)$PersonID;
        }
    }

    public static function addPersonArray($PersonArray): void
    {
        foreach ($PersonArray as $PersonID) {
            Cart::addPerson($PersonID);
        }
    }

    public static function addGroup($GroupID): void
    {
        if (!is_numeric($GroupID)) {
            throw new \Exception(gettext('GroupID for Cart must be numeric'), 400);
        }
        $GroupMembers = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($GroupID)
            ->find();
        foreach ($GroupMembers as $GroupMember) {
            Cart::addPerson($GroupMember->getPersonId());
        }
    }

    public static function addFamily($FamilyID): void
    {
        if (!is_numeric($FamilyID)) {
            throw new \Exception(gettext('FamilyID for Cart must be numeric'), 400);
        }
        $FamilyMembers = PersonQuery::create()
            ->filterByFamId($FamilyID)
            ->find();
        foreach ($FamilyMembers as $FamilyMember) {
            Cart::addPerson($FamilyMember->getId());
        }
    }

    public static function intersectArrayWithPeopleCart($aIDs): void
    {
        if (isset($_SESSION['aPeopleCart']) && is_array($aIDs)) {
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'], $aIDs);
        }
    }

    public static function removePerson($PersonID): void
    {
        // make sure the cart array exists
        // we can't remove anybody if there is no cart
        if (!is_numeric($PersonID)) {
            throw new \Exception(gettext('PersonID for Cart must be numeric'), 400);
        }
        if (isset($_SESSION['aPeopleCart'])) {
            $aTempArray[] = $PersonID; // the only element in this array is the ID to be removed
            $_SESSION['aPeopleCart'] = array_values(array_diff($_SESSION['aPeopleCart'], $aTempArray));
        }
    }

    public static function removePersonArray($aIDs): void
    {
        // make sure the cart array exists
        // we can't remove anybody if there is no cart
        if (isset($_SESSION['aPeopleCart']) && is_array($aIDs)) {
            $_SESSION['aPeopleCart'] = array_values(array_diff($_SESSION['aPeopleCart'], $aIDs));
        }
    }

    public static function removeGroup($GroupID): void
    {
        if (!is_numeric($GroupID)) {
            throw new \Exception(gettext('GroupID for Cart must be numeric'), 400);
        }
        $GroupMembers = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($GroupID)
            ->find();
        foreach ($GroupMembers as $GroupMember) {
            Cart::removePerson($GroupMember->getPersonId());
        }
    }

    public static function hasPeople(): bool
    {
        return array_key_exists('aPeopleCart', $_SESSION) && count($_SESSION['aPeopleCart']) != 0;
    }

    public static function countPeople(): int
    {
        return count($_SESSION['aPeopleCart']);
    }

    public static function convertCartToString($aCartArray)
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

    public static function countFamilies()
    {
        $persons = PersonQuery::create()
            ->distinct()
            ->select(['Person.FamId'])
            ->filterById($_SESSION['aPeopleCart'])
            ->orderByFamId()
            ->find();

        return $persons->count();
    }

    public static function emptyToGroup($GroupID, $RoleID): void
    {
        $iCount = 0;

        $group = GroupQuery::create()->findOneById($GroupID);

        if ($RoleID == 0) {
            $RoleID = $group->getDefaultRole();
        }

        foreach ($_SESSION['aPeopleCart'] as $element) {
            $personGroupRole = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($GroupID)
            ->filterByPersonId($element)
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
              }   */

            $iCount += 1;
        }

        $_SESSION['aPeopleCart'] = [];
    }

    public static function getCartPeople()
    {
        $people = PersonQuery::create()
            ->filterById($_SESSION['aPeopleCart'])
            ->find();

        return $people;
    }

    public static function getEmailLink(): string
    {
        /* @var $cartPerson ChurchCRM\Person */
        $people = Cart::getCartPeople();
        $emailAddressArray = [];
        foreach ($people as $cartPerson) {
            if (!empty($cartPerson->getEmail())) {
                $emailAddressArray[] = $cartPerson->getEmail();
            }
        }
        $delimiter = AuthenticationManager::getCurrentUser()->getUserConfigString('sMailtoDelimiter');
        $sEmailLink = implode($delimiter, array_unique(array_filter($emailAddressArray)));
        if (!empty(SystemConfig::getValue('sToEmailAddress')) && !stristr($sEmailLink, (string) SystemConfig::getValue('sToEmailAddress'))) {
            $sEmailLink .= $delimiter . SystemConfig::getValue('sToEmailAddress');
        }

        return $sEmailLink;
    }

    public static function getSMSLink(): string
    {
        /* @var $cartPerson ChurchCRM\Person */
        $people = Cart::getCartPeople();
        $SMSNumberArray = [];
        foreach ($people as $cartPerson) {
            if (!empty($cartPerson->getCellPhone())) {
                $SMSNumberArray[] = $cartPerson->getCellPhone();
            }
        }
        $sSMSLink = implode(',', $SMSNumberArray);

        return $sSMSLink;
    }

    public static function emptyAll(): void
    {
        Cart::removePersonArray($_SESSION['aPeopleCart']);
    }
}
