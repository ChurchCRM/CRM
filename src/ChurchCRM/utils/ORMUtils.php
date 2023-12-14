<?php

namespace ChurchCRM\Utils;

class ORMUtils
{
    public static function getValidationErrors($failures)
    {
        $validationErrors = [];
        foreach ($failures as $failure) {
            $validationErrors[] = 'Property ' . $failure->getPropertyPath() . ': ' . $failure->getMessage();
        }

        return $validationErrors;
    }
}
