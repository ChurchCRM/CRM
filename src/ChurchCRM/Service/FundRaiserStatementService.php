<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\DonatedItem;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\MultibuyQuery;
use ChurchCRM\model\ChurchCRM\PaddleNumQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CurrencyFormatter;

/**
 * Buyer statements for one fundraiser: what each buyer donated, what they bought and what they owe.
 * Callers authorize: the /fundraiser app sits behind ManageFundraisersRoleAuthMiddleware.
 */
class FundRaiserStatementService
{
    /**
     * The document the `fundraiser-statement` template renders.
     *
     * @param int[]|null $paddleIds paddle number ids to include; null includes every paddle of the fundraiser
     */
    public static function document(int $fundraiserId, ?array $paddleIds = null): array
    {
        $query = PaddleNumQuery::create()->filterByPnFrId($fundraiserId)->orderByPnNum();
        if ($paddleIds !== null) {
            if ($paddleIds === []) {
                return ['letterhead' => 'address', 'statements' => []];
            }
            $query->filterByPnId($paddleIds);
        }
        $paddles = iterator_to_array($query->find(), false);

        $people = [];
        foreach (PersonQuery::create()->filterById(array_map(static fn ($paddle) => $paddle->getPnPerId(), $paddles))->find() as $person) {
            $people[$person->getId()] = $person;
        }

        // A buyer who holds several paddles gets one statement listing every paddle number.
        $paddleNumbers = [];
        foreach ($paddles as $paddle) {
            $paddleNumbers[$paddle->getPnPerId()][] = $paddle->getPnNum();
        }

        $statements = [];
        foreach ($paddles as $paddle) {
            if (!isset($paddleNumbers[$paddle->getPnPerId()])) {
                continue;
            }
            $numbers = $paddleNumbers[$paddle->getPnPerId()];
            unset($paddleNumbers[$paddle->getPnPerId()]);
            $buyer = $people[$paddle->getPnPerId()] ?? null;
            $family = $buyer?->getFamily();

            $donated = [];
            foreach (DonatedItemQuery::create()->filterByFrId($fundraiserId)->filterByDonorId($paddle->getPnPerId())->find() as $item) {
                $donated[] = $item;
            }
            $purchased = [];
            foreach (DonatedItemQuery::create()->filterByFrId($fundraiserId)->filterByBuyerId($paddle->getPnPerId())->find() as $item) {
                $purchased[] = $item;
            }

            // A multibuy row counts only when its item belongs to this fundraiser.
            $multibuys = [];
            $multibuyRows = MultibuyQuery::create()->filterByMbPerId($paddle->getPnPerId())->find();
            $multibuyItems = [];
            $itemIds = [];
            foreach ($multibuyRows as $row) {
                $itemIds[] = $row->getMbItemId();
            }
            foreach (DonatedItemQuery::create()->filterById($itemIds)->filterByFrId($fundraiserId)->find() as $item) {
                $multibuyItems[$item->getId()] = $item;
            }

            $contacts = self::peopleById(array_merge(
                array_map(static fn (DonatedItem $item) => $item->getBuyerId(), $donated),
                array_map(static fn (DonatedItem $item) => $item->getDonorId(), $purchased),
                array_map(static fn (DonatedItem $item) => $item->getDonorId(), $multibuyItems)
            ));

            $totalCents = 0;
            $purchases = [];
            foreach ($purchased as $item) {
                $purchases[] = self::line($item, 1, $contacts[$item->getDonorId()] ?? null);
                $totalCents += self::cents($item->getSellprice());
            }
            foreach ($multibuyRows as $row) {
                $item = $multibuyItems[$row->getMbItemId()] ?? null;
                if ($item === null) {
                    continue;
                }
                $count = (int) $row->getMbCount();
                $purchases[] = self::line($item, $count, $contacts[$item->getDonorId()] ?? null);
                $totalCents += $count * self::cents($item->getSellprice());
            }

            $statements[] = [
                'salutation' => $family !== null ? $family->getSalutation() : gettext('Dear Friend'),
                'address' => $family !== null ? $family->getMailingAddressLines() : '',
                'paddle' => implode(', ', $numbers),
                'buyer' => $buyer !== null ? trim($buyer->getFirstName() . ' ' . $buyer->getLastName()) : '',
                'donated' => array_map(
                    static fn (DonatedItem $item): array => self::line($item, 1, $contacts[$item->getBuyerId()] ?? null),
                    $donated
                ),
                'purchases' => $purchases,
                'total' => CurrencyFormatter::format($totalCents / 100),
            ];
        }

        return ['letterhead' => 'address', 'statements' => $statements];
    }

    /**
     * @param int[] $ids
     * @return array<int, Person>
     */
    private static function peopleById(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }
        $people = [];
        foreach (PersonQuery::create()->filterById($ids)->find() as $person) {
            $people[$person->getId()] = $person;
        }

        return $people;
    }

    /**
     * One item row; `contact` is the other party (the buyer of a donated item, the donor of a purchase).
     */
    private static function line(DonatedItem $item, int $quantity, ?Person $contact): array
    {
        return [
            'item' => (string) $item->getItem(),
            'quantity' => $quantity,
            'title' => (string) $item->getTitle(),
            'name' => $contact !== null ? trim($contact->getFirstName() . ' ' . $contact->getLastName()) : '',
            'phone' => (string) ($contact?->getBestPhone() ?: ($contact?->getFamily()?->getHomePhone() ?? '')),
            'email' => (string) ($contact?->getEmail() ?? ''),
            'amount' => CurrencyFormatter::format($quantity * self::cents($item->getSellprice()) / 100),
        ];
    }

    private static function cents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
