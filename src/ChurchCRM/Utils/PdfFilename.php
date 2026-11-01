<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;

final class PdfFilename
{
    /** A report name followed by today's date in the church's filename date format. */
    public static function dated(string $name): string
    {
        return $name . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
    }

    public static function create(string $label): string
    {
        $label = trim(ChurchMetaData::getChurchName() . ' ' . $label);
        $name = trim((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $label), '-');

        return ($name !== '' ? $name : 'Report') . '.pdf';
    }
}
