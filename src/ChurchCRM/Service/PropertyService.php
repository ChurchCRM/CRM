<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\Property;
use ChurchCRM\model\ChurchCRM\PropertyQuery;
use ChurchCRM\model\ChurchCRM\PropertyTypeQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use Propel\Runtime\Collection\ObjectCollection;

/**
 * Central access point for the property_pro / record2property_r2p tables.
 * Assignment lookups hide the pro_Class letter ('f' for Family, 'p' for
 * Person) so callers only pass an entity.
 */
class PropertyService
{
    public const CLASS_FAMILY = 'f';
    public const CLASS_PERSON = 'p';
    public const CLASS_GROUP = 'g';
    public const NAME_MAX_LENGTH = 200;
    public const PROMPT_MAX_LENGTH = 255;

    /**
     * RecordProperty rows assigned to the entity. Related Property and
     * PropertyType objects are fetched lazily when accessed via
     * $rp->getProperty() / $prop->getPropertyType().
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
     * Property types a property of the given class can be filed under.
     */
    public static function getTypes(string $class): ObjectCollection
    {
        return PropertyTypeQuery::create()
            ->filterByPrtClass($class)
            ->orderByPrtName()
            ->find();
    }

    /**
     * Saves a property definition when the input is valid. The property keeps its class;
     * its type must belong to the same class.
     *
     * @return array<string, string> error message per field (type, name, prompt); empty when saved
     */
    public static function saveDefinition(Property $property, int $propertyTypeId, string $name, string $description, string $prompt): array
    {
        $errors = [];
        $typeMatchesClass = PropertyTypeQuery::create()
            ->filterByPrtId($propertyTypeId)
            ->filterByPrtClass($property->getProClass())
            ->count() > 0;
        if (!$typeMatchesClass) {
            $errors['type'] = gettext('You must select a type');
        }
        if ($name === '') {
            $errors['name'] = gettext('You must enter a name');
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors['name'] = sprintf(gettext('The name can be at most %d characters'), self::NAME_MAX_LENGTH);
        }
        if (mb_strlen($prompt) > self::PROMPT_MAX_LENGTH) {
            $errors['prompt'] = sprintf(gettext('The prompt can be at most %d characters'), self::PROMPT_MAX_LENGTH);
        }
        if ($errors !== []) {
            return $errors;
        }

        $property
            ->setProPrtId($propertyTypeId)
            ->setProName($name)
            ->setProDescription($description)
            ->setProPrompt($prompt)
            ->save();

        return [];
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
