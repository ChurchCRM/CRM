<?php

namespace ChurchCRM\Service;

final class DepositReportRenderer
{
    public static function create(): MpdfRenderer
    {
        return new MpdfRenderer();
    }
}
