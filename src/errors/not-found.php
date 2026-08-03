<?php
// The requested page does not exist, or was moved/removed in a later version.
http_response_code(404);

require_once __DIR__ . '/template.php';

$requestedPath = $_GET['path'] ?? ($_SERVER['REQUEST_URI'] ?? 'unknown');
if (!is_string($requestedPath)) {
    $requestedPath = 'unknown';
}

$customSections = "### Requested Path\n\n";
$customSections .= "```\n" . htmlspecialchars($requestedPath) . "\n```";

$issueBody = buildGitHubIssueBody('Page Not Found (404)', $customSections);

$content = '<p class="text-muted mb-2">The page you requested could not be found. It may have been moved, renamed, or no longer exists.</p>'
    . '<div class="alert alert-secondary border-2 mt-3">'
    . '<strong>Requested:</strong> <code class="small text-break">' . htmlspecialchars($requestedPath) . '</code>'
    . '</div>';

renderErrorPage('Page Not Found', '🔍', 'secondary', $content, $issueBody);
