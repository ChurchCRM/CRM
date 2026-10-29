<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Classification;
use ChurchCRM\model\ChurchCRM\DepositQuery;
use ChurchCRM\model\ChurchCRM\DonationFundQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\Utils\MiscUtils;

/**
 * The choices a finance report's option page offers: who, which funds, which deposit.
 */
class FinanceReportOptions
{
    /**
     * @return array<int, array{id: int, name: string}>
     */
    public static function classifications(): array
    {
        $classifications = [];
        foreach (Classification::getAll() as $option) {
            $classifications[] = ['id' => (int) $option->getOptionId(), 'name' => (string) $option->getOptionName()];
        }

        return $classifications;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public static function families(): array
    {
        $families = [];
        foreach (FamilyQuery::create()->orderByName()->find() as $family) {
            $families[] = [
                'id' => (int) $family->getId(),
                'label' => $family->getName() . MiscUtils::formatAddressLine((string) $family->getAddress1(), (string) $family->getCity(), (string) $family->getState()),
            ];
        }

        return $families;
    }

    /**
     * Funds grouped by category, each as id/name/inactive, in the order the fund list keeps.
     *
     * @return array<string, array<int, array{id: int, name: string, inactive: bool}>>
     */
    public static function fundGroups(): array
    {
        $funds = DonationFundQuery::create()->orderByActive()->orderByName()->find();

        $groups = [];
        foreach ((new DonationFundService())->groupByCategory($funds) as $category => $categoryFunds) {
            foreach ($categoryFunds as $fund) {
                $groups[$category][] = [
                    'id' => (int) $fund->getId(),
                    'name' => (string) $fund->getName(),
                    'inactive' => $fund->getActive() === 'false',
                ];
            }
        }

        return $groups;
    }

    /**
     * The most recent deposits, newest first.
     *
     * @return array<int, array{id: int, label: string}>
     */
    public static function deposits(int $limit = 200): array
    {
        $deposits = [];
        foreach (DepositQuery::create()->orderById('DESC')->limit($limit)->find() as $deposit) {
            $deposits[] = [
                'id' => (int) $deposit->getId(),
                'label' => sprintf('%d — %s — %s', $deposit->getId(), $deposit->getDate('Y-m-d'), $deposit->getType()),
            ];
        }

        return $deposits;
    }
}
