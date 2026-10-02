<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Owns the two classification flag settings: comma-separated ids of list 1, where 0 is Unassigned.
 */
class ClassificationService
{
    private const INACTIVE_KEY = 'sInactiveClassification';
    private const DIRECTORY_KEY = 'sDirClassifications';

    /** @return int[] */
    public function getInactiveIds(): array
    {
        return $this->read(self::INACTIVE_KEY);
    }

    /** @return int[] */
    public function getDirectoryIds(): array
    {
        return $this->read(self::DIRECTORY_KEY);
    }

    public function isInactive(int $classificationId): bool
    {
        return in_array($classificationId, $this->getInactiveIds(), true);
    }

    /** @return int[] the inactive ids after the toggle */
    public function toggleInactive(int $classificationId): array
    {
        return $this->toggle(self::INACTIVE_KEY, $classificationId);
    }

    /** @return int[] the directory ids after the toggle */
    public function toggleDirectory(int $classificationId): array
    {
        return $this->toggle(self::DIRECTORY_KEY, $classificationId);
    }

    /** A freed classification id can be reused by the next one added, so it must not linger in either list. */
    public function removeFromFlags(int $classificationId): void
    {
        foreach ([self::INACTIVE_KEY, self::DIRECTORY_KEY] as $key) {
            $this->write($key, array_diff($this->read($key), [$classificationId]));
        }
    }

    /** Hides people in an inactive classification. per_cls_ID is NOT NULL, so NOT IN needs no NULL guard. */
    public function excludeInactive(PersonQuery $query): PersonQuery
    {
        $inactiveIds = $this->getInactiveIds();
        if ($inactiveIds !== []) {
            $query->filterByClsId($inactiveIds, Criteria::NOT_IN);
        }

        return $query;
    }

    /** @return int[] */
    private function read(string $key): array
    {
        $parts = array_filter(array_map('trim', explode(',', (string) SystemConfig::getValue($key))), fn (string $p): bool => $p !== '');
        $digits = array_filter($parts, 'ctype_digit');
        if (count($digits) !== count($parts)) {
            LoggerUtils::getAppLogger()->warning('Encountered invalid configuration(s) for ' . $key . ', please fix this');
        }

        return array_values(array_unique(array_map('intval', $digits)));
    }

    /** @param int[] $ids */
    private function write(string $key, array $ids): void
    {
        SystemConfig::setValue($key, implode(',', array_values($ids)));
    }

    /** @return int[] */
    private function toggle(string $key, int $classificationId): array
    {
        $ids = $this->read($key);
        if (in_array($classificationId, $ids, true)) {
            $ids = array_values(array_diff($ids, [$classificationId]));
        } else {
            $ids[] = $classificationId;
        }
        $this->write($key, $ids);

        return $ids;
    }
}
