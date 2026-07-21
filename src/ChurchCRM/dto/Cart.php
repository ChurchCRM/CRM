<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2r;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\GroupService;
use Propel\Runtime\Connection\ConnectionInterface;
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
     * When $con is provided the method participates in the caller's transaction
     * (no nested begin/commit) and does NOT clear the session cart — the caller
     * is responsible for clearing it after a successful commit.
     *
     * When $con is null the method manages its own transaction and clears the
     * session cart on success.
     *
     * @param int                    $familyId       ID of the target family (must already exist)
     * @param array                  $roleByPersonId Map of personId => familyRoleId for eligible persons
     * @param ConnectionInterface|null $con          Optional connection; omit to manage own transaction
     * @return int  Number of persons actually assigned
     */
    public static function emptyToFamily(int $familyId, array $roleByPersonId, ?ConnectionInterface $con = null): int
    {
        self::checkCart();
        $assigned = 0;
        $ownTransaction = ($con === null);
        if ($ownTransaction) {
            $con = Propel::getConnection();
            $con->beginTransaction();
        }
        try {
            // Iterate $roleByPersonId rather than the raw session array (fixes F5).
            // This eliminates the race where a person added in another tab between the
            // route's DB snapshot (T1) and this loop (T2) would be missing from
            // $roleByPersonId and previously caused an InvalidArgumentException.
            // All values in $roleByPersonId are guaranteed non-zero by the route.
            foreach ($roleByPersonId as $personId => $roleId) {
                $person = PersonQuery::create()->findOneById((int) $personId);
                if ($person === null || $person->getFamId() !== 0) {
                    // Skip missing or already-in-a-family persons (fixes B3)
                    continue;
                }
                $person->setFamId($familyId)->setFmrId((int) $roleId)->save($con);
                $assigned++;
            }
            if ($ownTransaction) {
                $con->commit();
                $_SESSION['aPeopleCart'] = []; // empty cart server-side (fixes B11)
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $con->rollBack();
            }
            throw $e;
        }
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
        $delimiter = ','; // RFC 6068: comma is the standard email-list delimiter
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
