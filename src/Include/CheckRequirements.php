<?php

use ChurchCRM\dto\Prerequisite;
use ChurchCRM\Service\AppIntegrityService;
use ChurchCRM\Slim\SlimUtils;

$phpPrerequisites = AppIntegrityService::getPhpPrerequisites();
$failedRequirements = array_values(array_filter($phpPrerequisites, fn (Prerequisite $check): bool => !$check->isPrerequisiteMet()));
if ($failedRequirements !== []) {
    http_response_code(503);
    header('Cache-Control: no-store');
    $requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (str_contains($requestPath, '/api/') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(SlimUtils::buildErrorPayload(
            function_exists('gettext')
                ? gettext('Some PHP requirements are missing. ChurchCRM needs these before it can continue.')
                : 'Some PHP requirements are missing. ChurchCRM needs these before it can continue.',
            503,
            ['failedRequirements' => array_map(fn (Prerequisite $check): string => $check->getName(), $failedRequirements)]
        ));
        exit;
    }
    require __DIR__ . '/../setup/templates/system-requirements.php';
    exit;
}
