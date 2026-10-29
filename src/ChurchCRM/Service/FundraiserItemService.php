<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\DonatedItem;
use ChurchCRM\model\ChurchCRM\DonatedItemQuery;
use ChurchCRM\model\ChurchCRM\FundRaiser;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\CurrencyFormatter;

/**
 * The donated items of a fundraiser as the printed reports (bid sheets, catalog,
 * certificates) show them, in item-code order.
 */
class FundraiserItemService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * @return array<int, array{code: string, title: string, description: string, minimum: float, minimum_text: string, estimate: float, estimate_text: string, donor_first: string, donor_last: string, picture: string}>
     */
    public static function items(FundRaiser $fundraiser): array
    {
        $donatedItems = DonatedItemQuery::create()->filterByFrId($fundraiser->getId())->find()->getArrayCopy();
        usort($donatedItems, fn (DonatedItem $a, DonatedItem $b): int => self::sortKey((string) $a->getItem()) <=> self::sortKey((string) $b->getItem()));

        $donorIds = array_filter(array_map(fn (DonatedItem $item) => $item->getDonorId(), $donatedItems));
        $donors = [];
        foreach (PersonQuery::create()->filterById($donorIds)->find() as $donor) {
            $donors[$donor->getId()] = [(string) $donor->getFirstName(), (string) $donor->getLastName()];
        }

        $items = [];
        foreach ($donatedItems as $item) {
            $minimum = (float) $item->getMinimum();
            $estimate = (float) $item->getEstprice();
            $items[] = [
                'code' => (string) $item->getItem(),
                'title' => stripslashes((string) $item->getTitle()),
                'description' => stripslashes((string) $item->getDescription()),
                'minimum' => $minimum,
                'minimum_text' => CurrencyFormatter::format($minimum),
                'estimate' => $estimate,
                'estimate_text' => CurrencyFormatter::format($estimate),
                'donor_first' => $donors[$item->getDonorId()][0] ?? '',
                'donor_last' => $donors[$item->getDonorId()][1] ?? '',
                'picture' => self::localPicture((string) $item->getPicture()),
            ];
        }

        return $items;
    }

    /** Item codes look like "A1", "B23a": leading letter, then the number, then any suffix. */
    private static function sortKey(string $code): array
    {
        preg_match('/^(.?)(\d*)(.*)$/su', $code, $parts);

        return [$parts[1], (int) $parts[2], $parts[3]];
    }

    /**
     * Only an image file inside the installation is embedded. A picture URL is
     * never fetched while the PDF is built.
     */
    private static function localPicture(string $picture): string
    {
        if ($picture === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $picture) === 1) {
            return '';
        }

        $root = realpath(SystemURLs::getDocumentRoot());
        $path = $root === false ? false : realpath($root . '/' . ltrim($picture, '/'));
        if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return '';
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true) ? $path : '';
    }
}
