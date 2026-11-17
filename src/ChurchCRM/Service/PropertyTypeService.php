<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\PropertyType;

/**
 * Property types group the person, family and group property definitions.
 * A type's class is 'p' (person), 'f' (family) or 'g' (group).
 */
class PropertyTypeService
{
    public const NAME_MAX_LENGTH = 50;

    /**
     * @return array<string, string> class letter => localized label
     */
    public static function classLabels(): array
    {
        return [
            'p' => gettext('Person'),
            'f' => gettext('Family'),
            'g' => gettext('Group'),
        ];
    }

    /**
     * Saves the property type when the input is valid.
     *
     * @return array<string, string> error message per field (class, name); empty when saved
     */
    public static function save(PropertyType $propertyType, string $class, string $name, string $description): array
    {
        $errors = [];
        if (!array_key_exists($class, self::classLabels())) {
            $errors['class'] = gettext('You must select a class');
        }
        if ($name === '') {
            $errors['name'] = gettext('You must enter a name');
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors['name'] = sprintf(gettext('The name can be at most %d characters'), self::NAME_MAX_LENGTH);
        }
        if ($errors !== []) {
            return $errors;
        }

        $propertyType
            ->setPrtClass($class)
            ->setPrtName($name)
            ->setPrtDescription($description)
            ->save();

        return [];
    }
}
