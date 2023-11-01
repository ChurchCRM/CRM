<?php

namespace ChurchCRM\Backup;

abstract class BackupType
{
    public const GZSQL = 0;
    public const SQL = 2;
    public const FULL_BACKUP = 3;
}
