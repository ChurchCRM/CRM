<?php
/**
 * Shared template for public error pages
 * Provides helper functions to gather system information and generate error content
 * CSS/JS bundled via webpack/error.scss and webpack/error.js
 */

/**
 * Get ChurchCRM version from composer.json (single source of truth for app version)
 * Path from /src/errors/template.php → /src/composer.json
 */
function getChurchCRMVersion() {
    $composerFile = __DIR__ . '/../composer.json';
    if (file_exists($composerFile)) {
        $composer = json_decode(@file_get_contents($composerFile), true);
        if (is_array($composer) && !empty($composer['version'])) {
            return $composer['version'];
        }
    }
    return 'Unknown';
}

/**
 * Collect comprehensive server information for error reporting
 */
function getServerInfo() {
    $info = [];
    $info['PHP Version'] = phpversion();
    $info['PHP SAPI'] = php_sapi_name();
    $info['Server Software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
    $info['OS'] = php_uname();
    $info['Memory Limit'] = ini_get('memory_limit');
    $info['ChurchCRM Version'] = getChurchCRMVersion();
    return $info;
}

/**
 * Build GitHub issue body with Markdown formatting
 * Allows subpages to add custom sections before server info
 */
function buildGitHubIssueBody($title, $customSections = '') {
    $body = "## $title\n\n";
    
    if (!empty($customSections)) {
        $body .= $customSections . "\n\n";
    }
    
    $body .= "### System Information\n\n";
    $info = getServerInfo();
    foreach ($info as $key => $value) {
        $escapedValue = is_array($value) ? implode(', ', $value) : $value;
        $body .= "- **$key:** `" . str_replace('`', '\\`', $escapedValue) . "`\n";
    }
    
    return $body;
}

/**
 * Generate common HTML structure for error pages using Tabler/Bootstrap classes
 * All CSS is in webpack/error.scss
 */
function renderErrorPage($title, $icon, $gradientClass, $content, $issueBody) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/skin/v2/error.min.css">
</head>
<body class="bg-gradient-primary-purple d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="error-wrapper w-100" style="max-width: 500px;">
        <div class="card shadow-lg border-0 overflow-hidden">
            <div class="card-header bg-gradient-<?php echo htmlspecialchars($gradientClass, ENT_QUOTES, 'UTF-8'); ?> text-white p-4 text-center">
                <div class="fs-1 mb-3"><?php echo $icon; ?></div>
                <h1 class="card-title mb-0"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="card-body p-4">
                <?php echo $content; ?>
                
                <div class="d-flex gap-3 mt-4 flex-wrap">
                    <a href="/" class="btn btn-primary flex-grow-1">Return Home</a>
                    <a href="https://github.com/ChurchCRM/CRM/issues/new?title=<?php echo rawurlencode('[Error] ' . $title); ?>&body=<?php echo rawurlencode($issueBody); ?>" target="_blank" class="btn btn-outline-secondary flex-grow-1">Report Issue</a>
                </div>
            </div>
        </div>
    </div>
    <script src="/skin/v2/error.min.js"></script>
</body>
</html>
    <?php
}
