<?php
require_once __DIR__ . '/../Include/Config.php';

$errorMessage = $_GET['error'] ?? 'Configuration error';
$logPath = sys_get_temp_dir() . '/churchcrm-' . date('Y-m-d') . '-config-error.log';
$logContents = '';
if (file_exists($logPath) && is_readable($logPath)) {
    $logContents = file_get_contents($logPath);
}

$pageTitle = 'Configuration Error';
$pageBodyHtml = '<div class="error-details"><p>ChurchCRM encountered an error reading or validating your configuration.</p><p>Review your <code>Include/Config.php</code> file and try again.</p></div>';
$pageBodyHtml .= '<div class="error-message"><strong>Error:</strong><br>' . nl2br(htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8')) . '</div>';
if ($logContents) {
    $pageBodyHtml .= '<div class="error-message" style="max-height:300px;overflow-y:auto;background:#f0f0f0;"><strong>Log Details:</strong><br><small style="color:#555;">' . nl2br(htmlspecialchars($logContents, ENT_QUOTES, 'UTF-8')) . '</small></div>';
}

require __DIR__ . '/template.php';
