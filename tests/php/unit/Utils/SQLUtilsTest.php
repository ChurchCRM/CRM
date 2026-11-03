<?php

declare(strict_types=1);

use ChurchCRM\Utils\SQLImportException;
use ChurchCRM\Utils\SQLUtils;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

$sqlFile = tempnam(sys_get_temp_dir(), 'churchcrm-sql-import-');
if ($sqlFile === false) {
    throw new RuntimeException('Unable to create SQL import fixture.');
}
file_put_contents($sqlFile, 'SELECT missing_column;');

try {
    $connection = new class {
        public string $error = '';

        public function query(string $sql): never
        {
            throw new PDOException("Unknown column 'missing_column'");
        }
    };

    $exception = Assert::exception(
        static fn () => SQLUtils::sqlImport($sqlFile, $connection),
        SQLImportException::class,
        'Cannot execute request to the database SELECT missing_column: Unknown column \'missing_column\'',
    );
    Assert::same("Unknown column 'missing_column'", $exception->getDatabaseError());
    Assert::type(PDOException::class, $exception->getPrevious());
} finally {
    unlink($sqlFile);
}
