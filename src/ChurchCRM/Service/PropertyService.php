<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use Propel\Runtime\Collection\ObjectCollection;

/**
 * Central access point for the property_pro / record2property_r2p tables.
 * Hides the pro_Class letter ('f' for Family, 'p' for Person) so callers
 * only pass an entity.
 */
class PropertyService
{
    public const CLASS_FAMILY = 'f';
    public const CLASS_PERSON = 'p';

    /**
     * RecordProperty rows assigned to the entity, pre-joined with Property + PropertyType.
     *
     * @param Family|Person $entity
     */
    public static function getAssigned($entity): ObjectCollection
    {
        return RecordPropertyQuery::create()
            ->filterByRecordId($entity->getId())
            ->usePropertyQuery()
                ->filterByProClass(self::classFor($entity))
                ->orderByProName()
            ->endUse()
            ->find();
    }

    /**
     * Every defined property of the same class as the entity (master list for the picker).
     *
     * @param Family|Person $entity
     */
    public static function getAll($entity): ObjectCollection
    {
        return PropertyQuery::create()
            ->filterByProClass(self::classFor($entity))
            ->orderByProName()
            ->find();
    }

    /**
     * @param Family|Person $entity
     */
    private static function classFor($entity): string
    {
        if ($entity instanceof Family) {
            return self::CLASS_FAMILY;
        }
        if ($entity instanceof Person) {
            return self::CLASS_PERSON;
        }
        throw new \InvalidArgumentException('Properties are only supported for Family and Person entities');
    }
}
