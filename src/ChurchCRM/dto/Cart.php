<?php

namespace ChurchCRM\dto;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\GroupService;
use Propel\Runtime\Propel;

class Cart
{
    private static function checkCart(): void
    {
        if (!isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = [];
        }
    }

    public static function addPerson($PersonID): bool
    {
        self::checkCart();
        if (!is_numeric($PersonID)) {
            throw new \Exception(gettext('PersonID for Cart must be numeric'), 400);
        }
        if (!in_array($PersonID, $_SESSION['aPeopleCart'], false)) {
            $_SESSION['aPeopleCart'][] = (int)$PersonID;
            return true; // Person was added
        }
        return false; // Person already existed
    }

    public static function addPersonArray($PersonArray): array
    {
        $result = [
            'added' => [],
            'duplicate' => []
        ];
        
        foreach ($PersonArray as $PersonID) {
            if (Cart::addPerson($PersonID)) {
                $result['added'][] = (int)$PersonID;
            } else {
                $result['duplicate'][] = (int)$PersonID;
            }
        }
        
        return $result;
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

    public static function addFamily($FamilyID): array
    {
        if (!is_numeric($FamilyID)) {
            throw new \Exception(gettext('FamilyID for Cart must be numeric'), 400);
        }
        
        $result = [
            'added' => [],
            'duplicate' => []
        ];
        
        $FamilyMembers = PersonQuery::create()
            ->filterByFamId($FamilyID)
            ->find();
        foreach ($FamilyMembers as $FamilyMember) {
            if (Cart::addPerson($FamilyMember->getId())) {
                $result['added'][] = (int)$FamilyMember->getId();
            } else {
                $result['duplicate'][] = (int)$FamilyMember->getId();
            }
        }
        
        return $result;
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

    public static function removeFamily($FamilyID): void
    {
        if (!is_numeric($FamilyID)) {
            throw new \Exception(gettext('FamilyID for Cart must be numeric'), 400);
        }
        $FamilyMembers = PersonQuery::create()
            ->filterByFamId($FamilyID)
            ->find();
        foreach ($FamilyMembers as $FamilyMember) {
            Cart::removePerson($FamilyMember->getId());
        }
    }

    public static function hasPeople(): bool
    {
        return array_key_exists('aPeopleCart', $_SESSION) && count($_SESSION['aPeopleCart']) !== 0;
    }

    public static function countPeople(): int
    {
        self::checkCart();
        return count($_SESSION['aPeopleCart']);
    }

    /**
     * Returns the current cart person IDs as a comma-separated string.
     */
    public static function getCartIdString(): string
    {
        self::checkCart();

        return implode(',', array_filter($_SESSION['aPeopleCart']));
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
        $groupService = new GroupService();

        foreach ($_SESSION['aPeopleCart'] as $element) {
            $groupService->addUserToGroup($GroupID, $element, $RoleID);
        }

        $_SESSION['aPeopleCart'] = [];
    }

    /**
     * Assign all eligible cart people (famId === 0) to a family with per-person roles.
     *
     * Wraps all writes in a Propel transaction so a partial failure cannot produce
     * an orphan family or half-assigned members (fixes B2, B3, B11 from #9229).
     *
     * @param int   $familyId      ID of the target family (must already exist)
     * @param array $roleByPersonId Map of personId => familyRoleId for eligible persons
     * @return int  Number of persons actually assigned
     * @throws \InvalidArgumentException when an eligible person has no role in $roleByPersonId
     */
    public static function emptyToFamily(int $familyId, array $roleByPersonId): int
    {
        self::checkCart();
        $assigned = 0;
        $con = Propel::getConnection();
        $con->beginTransaction();
        try {
            foreach ($_SESSION['aPeopleCart'] as $personId) {
                $person = PersonQuery::create()->findOneById((int) $personId);
                if ($person === null || $person->getFamId() !== 0) {
                    // Skip missing or already-in-a-family persons (fixes B3)
                    continue;
                }
                $roleId = (int) ($roleByPersonId[(int) $personId] ?? 0);
                if ($roleId === 0) {
                    throw new \InvalidArgumentException(
                        gettext('Missing family role for person ') . $personId
                    );
                }
                $person->setFamId($familyId)->setFmrId($roleId)->save($con);
                $assigned++;
            }
            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }
        $_SESSION['aPeopleCart'] = []; // empty cart server-side (fixes B11)
        return $assigned;
    }

    public static function getCartPeople()
    {
        return PersonQuery::create()
            ->filterById($_SESSION['aPeopleCart'])
            ->find();
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

        return implode(',', $SMSNumberArray);
    }

    public static function emptyAll(): void
    {
        Cart::removePersonArray($_SESSION['aPeopleCart']);
    }
}
