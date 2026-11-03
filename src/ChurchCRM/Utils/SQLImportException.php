<?php

namespace ChurchCRM\Utils;

class SQLImportException extends \RuntimeException
{
    public function __construct(string $sql, string $databaseError)
    {
        parent::__construct("Cannot execute request to the database {$sql}: " . $databaseError);
        $this->databaseError = $databaseError;
    }

    private string $databaseError;

    public function getDatabaseError(): string
    {
        return $this->databaseError;
    }
}
