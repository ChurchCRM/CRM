<?php
require_once __DIR__ . '/../Include/Config.php';

// Read required PHP version from composer.json (fallback kept for safety)
$requiredPhpVersion = '8.4';
$composerFile = __DIR__ . '/../composer.json';
if (file_exists($composerFile)) {
    $composerJson = @file_get_contents($composerFile);
    if ($composerJson !== false) {
        $composer = json_decode($composerJson, true);
        if (is_array($composer) && !empty($composer['config']['platform']['php'])) {
            $requiredPhpVersion = $composer['config']['platform']['php'];
        }
    }
}

$pageTitle = 'PHP Version Not Supported';
$pageBodyHtml = '';
$pageBodyHtml .= '<div class="version-info">';
$pageBodyHtml .= '<p><strong>Current Version:</strong> <span class="highlight-version">' . htmlspecialchars(phpversion(), ENT_QUOTES, 'UTF-8') . '</span></p>';
$pageBodyHtml .= '<p><strong>Required Version:</strong> PHP ' . htmlspecialchars($requiredPhpVersion, ENT_QUOTES, 'UTF-8') . ' or later</p>';
$pageBodyHtml .= '</div>';
$pageBodyHtml .= '<div class="error-message"><p>ChurchCRM requires PHP ' . htmlspecialchars($requiredPhpVersion, ENT_QUOTES, 'UTF-8') . ' or later with active security support.</p></div>';
$pageBodyHtml .= '<div class="action-required"><strong>What you need to do:</strong><p>Contact your hosting provider or system administrator and request an upgrade to PHP ' . htmlspecialchars($requiredPhpVersion, ENT_QUOTES, 'UTF-8') . ' or later.</p></div>';

require __DIR__ . '/template.php';
