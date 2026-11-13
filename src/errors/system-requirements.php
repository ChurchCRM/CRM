<?php

require_once __DIR__ . '/template.php';

// English remains available when gettext itself is missing.
$hasGettext = function_exists('gettext');
$failedRequirements = [];
$rows = '';
foreach ($phpPrerequisites as $check) {
    $satisfied = $check->isPrerequisiteMet();
    if (!$satisfied) {
        $failedRequirements[] = $check->getName();
    }
    $rows .= '<tr><th scope="row" class="text-wrap">' . standaloneEscape($check->getName()) . '</th>'
        . '<td class="' . ($satisfied ? 'text-success' : 'text-danger') . '">' . standaloneEscape($satisfied ? ($hasGettext ? gettext('Passed') : 'Passed') : ($hasGettext ? gettext('Failed') : 'Failed')) . '</td></tr>';
}

$content = '<p role="alert" class="text-danger">' . standaloneEscape(($hasGettext ? gettext('Some PHP requirements are missing. ChurchCRM needs these before it can continue.') : 'Some PHP requirements are missing. ChurchCRM needs these before it can continue.')) . '</p>'
    . '<p class="text-secondary">' . standaloneEscape(($hasGettext ? gettext('Enable the missing PHP extensions in your web server configuration, restart the web server, then reload this page. If you use a hosting provider, ask them to enable the listed extensions.') : 'Enable the missing PHP extensions in your web server configuration, restart the web server, then reload this page. If you use a hosting provider, ask them to enable the listed extensions.')) . '</p>'
    . '<div class="table-responsive">'
    . '<table class="table table-vcenter mb-0">'
    . '<thead><tr><th scope="col">' . standaloneEscape(($hasGettext ? gettext('Requirement') : 'Requirement')) . '</th><th scope="col">' . standaloneEscape(($hasGettext ? gettext('Status') : 'Status')) . '</th></tr></thead>'
    . '<tbody>' . $rows . '</tbody>'
    . '</table>'
    . '</div>';

$issueBody = buildGitHubIssueBody('ChurchCRM System Check', "### Missing PHP Requirements\n\n- " . implode("\n- ", $failedRequirements));

renderErrorPage($requirementsRootPath, ($hasGettext ? gettext('ChurchCRM System Check') : 'ChurchCRM System Check'), '⚠️', 'danger', $content, $issueBody);
