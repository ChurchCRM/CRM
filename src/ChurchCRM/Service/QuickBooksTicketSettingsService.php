<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;

/**
 * Millimetre positions of the preprinted QuickBooks deposit ticket, stored as the sQBDTSettings JSON.
 */
class QuickBooksTicketSettingsService
{
    private const MAX_MM = 1000;

    /** Field name => path inside the stored JSON. */
    public const FIELDS = [
        'date1X' => ['date1', 'x'],
        'date1Y' => ['date1', 'y'],
        'date2X' => ['date2X'],
        'titleX' => ['titleX'],
        'leftX' => ['leftX'],
        'topY' => ['topY'],
        'perforationY' => ['perforationY'],
        'amountOffsetX' => ['amountOffsetX'],
        'lineItemIntervalX' => ['lineItemInterval', 'x'],
        'lineItemIntervalY' => ['lineItemInterval', 'y'],
        'maxX' => ['max', 'x'],
        'maxY' => ['max', 'y'],
        'numberOfItemsX' => ['numberOfItems', 'x'],
        'numberOfItemsY' => ['numberOfItems', 'y'],
        'subTotalX' => ['subTotal', 'x'],
        'subTotalY' => ['subTotal', 'y'],
        'topTotalX' => ['topTotal', 'x'],
        'topTotalY' => ['topTotal', 'y'],
    ];

    /** @return array<string, string> */
    public function read(): array
    {
        $stored = json_decode((string) SystemConfig::getValue('sQBDTSettings'), true);
        $values = [];
        foreach (self::FIELDS as $name => $path) {
            $value = is_array($stored) ? $stored : null;
            foreach ($path as $key) {
                $value = is_array($value) ? ($value[$key] ?? null) : null;
            }
            $values[$name] = is_numeric($value) ? (string) $value : '';
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws \InvalidArgumentException when a position is missing, not a number, or outside 0 to 1000 mm
     */
    public function save(array $input): void
    {
        $tree = [];
        foreach (self::FIELDS as $name => $path) {
            $value = $input[$name] ?? null;
            if (!is_numeric($value) || $value < 0 || $value > self::MAX_MM) {
                throw new \InvalidArgumentException(gettext('Every position must be a number from 0 to 1000 millimetres.'));
            }
            $node = &$tree;
            foreach ($path as $key) {
                $node = &$node[$key];
            }
            $node = (string) $value;
            unset($node);
        }

        SystemConfig::setValue('sQBDTSettings', json_encode($tree, JSON_THROW_ON_ERROR));
    }
}
