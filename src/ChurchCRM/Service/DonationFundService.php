<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DonationFund;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\PledgeQuery;
use ChurchCRM\Service\AuthService;
use ChurchCRM\Utils\InputUtils;
use Propel\Runtime\Collection\ObjectCollection;

class DonationFundService
{
    /**
     * Get active funds as a legacy stdClass array.
     *
     * @deprecated Use getAll() for ORM-based access.
     *
     * @return \stdClass[]
     */
    public function getActiveFunds(): array
    {
        AuthService::requireUserGroupMembership('bFinance');
        $funds = [];
        $activeFunds = DonationFundQuery::create()
            ->filterByActive('true')
            ->orderByOrder()
            ->find();
        foreach ($activeFunds as $donationFund) {
            $fund = new \stdClass();
            $fund->ID = $donationFund->getId();
            $fund->Name = $donationFund->getName();
            $fund->Description = $donationFund->getDescription();
            $funds[] = $fund;
        }

        return $funds;
    }

    /**
     * Get all active donation funds ordered by fund order.
     *
     * @return ObjectCollection Collection of DonationFund objects
     */
    public function getAll(): ObjectCollection
    {
        return DonationFundQuery::create()
            ->filterByActive('true')
            ->orderByOrder()
            ->find();
    }

    /**
     * Get all donation funds (including inactive) ordered by fund order.
     *
     * @return ObjectCollection Collection of DonationFund objects
     */
    public function getAllFunds(): ObjectCollection
    {
        return DonationFundQuery::create()
            ->orderByOrder()
            ->find();
    }

    /**
     * Get total count of all donation funds.
     *
     * @return int
     */
    public function getCount(): int
    {
        return DonationFundQuery::create()->count();
    }

    /**
     * Create a new donation fund.
     *
     * @param string $name Fund name (max 30 chars)
     * @param string $desc Fund description (max 100 chars)
     * @return DonationFund The newly created fund
     * @throws \InvalidArgumentException if name is empty or already exists
     */
    public function createFund(string $name, string $desc): DonationFund
    {
        $name = InputUtils::sanitizeText($name);
        if (strlen($name) === 0) {
            throw new \InvalidArgumentException('Fund name cannot be empty.');
        }

        $existing = DonationFundQuery::create()->findOneByName($name);
        if ($existing !== null) {
            throw new \InvalidArgumentException("A fund named '{$name}' already exists.");
        }

        $maxOrderFund = DonationFundQuery::create()
            ->orderByOrder('desc')
            ->findOne();
        $nextOrder = $maxOrderFund !== null ? $maxOrderFund->getOrder() + 1 : 1;

        $fund = new DonationFund();
        $fund->setName($name);
        $fund->setDescription(InputUtils::sanitizeText($desc));
        $fund->setOrder($nextOrder);
        $fund->save();

        return $fund;
    }

    /**
     * Update an existing donation fund's editable fields.
     *
     * Supported keys in $data: 'name', 'description', 'active'.
     *
     * @param int   $id   Fund ID
     * @param array $data Associative array of fields to update
     * @return DonationFund The updated fund
     * @throws \InvalidArgumentException if fund not found or name is invalid
     */
    public function updateFund(int $id, array $data): DonationFund
    {
        $fund = DonationFundQuery::create()->findOneById($id);
        if ($fund === null) {
            throw new \InvalidArgumentException("Donation fund with ID {$id} not found.");
        }

        if (array_key_exists('name', $data)) {
            $name = InputUtils::sanitizeText((string) $data['name']);
            if (strlen($name) === 0) {
                throw new \InvalidArgumentException('Fund name cannot be empty.');
            }
            $fund->setName($name);
        }

        if (array_key_exists('description', $data)) {
            $fund->setDescription(InputUtils::sanitizeText((string) $data['description']));
        }

        if (array_key_exists('active', $data)) {
            $fund->setActive($data['active'] ? 'true' : 'false');
        }

        $fund->save();

        return $fund;
    }

    /**
     * Delete a donation fund.
     *
     * @param int $id Fund ID
     * @throws \InvalidArgumentException if fund not found
     * @throws \RuntimeException if fund has associated pledges
     */
    public function deleteFund(int $id): void
    {
        $fund = DonationFundQuery::create()->findOneById($id);
        if ($fund === null) {
            throw new \InvalidArgumentException("Donation fund with ID {$id} not found.");
        }

        $pledgeCount = PledgeQuery::create()->filterByFundId($id)->count();
        if ($pledgeCount > 0) {
            throw new \RuntimeException("Cannot delete fund '{$fund->getName()}': it has {$pledgeCount} associated pledge(s).");
        }

        $fund->delete();

        // Renumber the remaining funds to keep order contiguous
        $remainingFunds = DonationFundQuery::create()
            ->orderByOrder()
            ->find();

        $currentOrder = 1;
        foreach ($remainingFunds as $remainingFund) {
            $remainingFund->setOrder($currentOrder++);
            $remainingFund->save();
        }
    }

    /**
     * Move a donation fund up or down in the display order.
     *
     * @param int    $id        Fund ID
     * @param string $direction Either 'up' or 'down'
     * @throws \InvalidArgumentException if fund not found or direction is invalid
     */
    public function reorderFund(int $id, string $direction): void
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new \InvalidArgumentException("Direction must be 'up' or 'down'.");
        }

        $fund = DonationFundQuery::create()->findOneById($id);
        if ($fund === null) {
            throw new \InvalidArgumentException("Donation fund with ID {$id} not found.");
        }

        $currentOrder = $fund->getOrder();

        if ($direction === 'up' && $currentOrder > 1) {
            $previousFund = DonationFundQuery::create()
                ->filterByOrder($currentOrder - 1)
                ->findOne();

            if ($previousFund !== null) {
                $fund->setOrder($currentOrder - 1);
                $previousFund->setOrder($currentOrder);
                $fund->save();
                $previousFund->save();
            }
        } elseif ($direction === 'down') {
            $nextFund = DonationFundQuery::create()
                ->filterByOrder($currentOrder + 1)
                ->findOne();

            if ($nextFund !== null) {
                $fund->setOrder($currentOrder + 1);
                $nextFund->setOrder($currentOrder);
                $fund->save();
                $nextFund->save();
            }
        }
    }
}
