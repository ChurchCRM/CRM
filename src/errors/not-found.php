<?php
// The requested page does not exist, or was moved/removed in a later version.
http_response_code(404);

require_once __DIR__ . '/template.php';

// $_GET['path'] is set by index.php's redirect: errors/not-found.php?path=<shortName>.
// Fall back to REQUEST_URI only when this page is visited directly (no ?path= query param);
// in that case show a sanitised path (leading slash + no query string) so it is readable.
$_rawPath = $_GET['path'] ?? null;
if (!is_string($_rawPath) || $_rawPath === '') {
    $_uri = $_SERVER['REQUEST_URI'] ?? '';
    $_rawPath = '/' . ltrim(strtok($_uri, '?'), '/');
    if ($_rawPath === '/') {
        $_rawPath = 'unknown';
    }
}
$requestedPath = $_rawPath;
unset($_rawPath, $_uri);

$customSections = "### Requested Path\n\n";
$customSections .= "```\n" . str_replace(["\r", "\n", '`'], [' ', ' ', "'"], $requestedPath) . "\n```";

$issueBody = buildGitHubIssueBody('Page Not Found (404)', $customSections);

$content = '<p class="text-muted mb-2">The page you requested could not be found. It may have been moved, renamed, or no longer exists.</p>'
    . '<div class="alert alert-secondary border-2 mt-3">'
    . '<strong>Requested:</strong> <code class="small text-break">' . htmlspecialchars($requestedPath) . '</code>'
    . '</div>';

renderErrorPage('Page Not Found', '🔍', 'secondary', $content, $issueBody);
