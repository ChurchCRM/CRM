<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchMetaData;

final class PdfFilename
{
    public static function create(string $label): string
    {
        $label = trim(ChurchMetaData::getChurchName() . ' ' . $label);
        $name = trim((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $label), '-');

        return ($name !== '' ? $name : 'Report') . '.pdf';
    }
}
