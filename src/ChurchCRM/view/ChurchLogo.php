<?php

namespace ChurchCRM\view;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\InputUtils;

class ChurchLogo
{
    /**
     * The church logo <img> (the uploaded logo, else the bundled ChurchCRM
     * logo) for every page that shows it.
     *
     * @param array<string, string> $attributes extra attributes, e.g. ['class' => 'navbar-brand-image']
     */
    public static function img(array $attributes = []): string
    {
        $attributes = [
            'src' => ChurchMetaData::getChurchLogoPath(),
            'alt' => ChurchMetaData::getChurchName() ?: 'ChurchCRM',
        ] + $attributes;

        $html = '<img';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . InputUtils::escapeAttribute($value) . '"';
        }

        return $html . '>';
    }
}
