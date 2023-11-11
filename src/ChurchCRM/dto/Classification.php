<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\ListOptionQuery;

class Classification
{
    public static function getAll()
    {
        return ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find();
    }

    public static function getName($clsId)
    {
        $classification = ListOptionQuery::create()->filterById(1)->filterByOptionId($clsId)->findOne();
        if (!empty($classification)) {
            return $classification->getOptionName();
        }

        return '';
    }
}
