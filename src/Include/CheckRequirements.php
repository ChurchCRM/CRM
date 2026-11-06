<?php

use ChurchCRM\dto\Prerequisite;
use ChurchCRM\Service\AppIntegrityService;

$phpPrerequisites = AppIntegrityService::getPhpPrerequisites();
if (array_filter($phpPrerequisites, fn (Prerequisite $check): bool => !$check->isPrerequisiteMet()) !== []) {
    http_response_code(503);
    require __DIR__ . '/../setup/templates/system-requirements.php';
    exit;
}
