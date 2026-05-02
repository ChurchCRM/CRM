<?php
/**
 * Configuration Error Page
 *
 * Displays when Config.php validation fails or configuration is invalid.
 */

$errorMessage = $_GET['error'] ?? 'Configuration error';
$logPath = sys_get_temp_dir() . '/churchcrm-' . date('Y-m-d') . '-config-error.log';
$logContents = '';
if (file_exists($logPath) && is_readable($logPath)) {
    $logContents = file_get_contents($logPath);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Error - ChurchCRM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .error-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 500px;
            text-align: center;
        }
        .error-icon {
            font-size: 3rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        h1 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .error-details {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .error-message {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: left;
            font-family: monospace;
            font-size: 0.85rem;
            color: #333;
            word-break: break-word;
        }
        .btn-primary {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Configuration Error</h1>
        <div class="error-details">
            <p>ChurchCRM encountered an error reading or validating your configuration.</p>
            <p>Review your <code>Include/Config.php</code> file and try again.</p>
        </div>
        <div class="error-message">
            <strong>Error:</strong><br>
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php if ($logContents): ?>
            <div class="error-message" style="max-height: 300px; overflow-y: auto; background: #f0f0f0;">
                <strong>Log Details:</strong><br>
                <small style="color: #555;"><?= nl2br(htmlspecialchars($logContents, ENT_QUOTES, 'UTF-8')) ?></small>
            </div>
        <?php endif; ?>
        <a href="/" class="btn btn-primary">Return to Home</a>
    </div>
</body>
</html>
