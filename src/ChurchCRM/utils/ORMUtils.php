<?php

namespace ChurchCRM\Utils;

class ORMUtils
{
    /**
     * @return non-falsy-string[]
     */
    public static function getValidationErrors($failures): array
    {
        $validationErrors = [];
        foreach ($failures as $failure) {
            $validationErrors[] = 'Property ' . $failure->getPropertyPath() . ': ' . $failure->getMessage();
        }

        return $validationErrors;
    }
}
