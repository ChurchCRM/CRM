<?php
/**
 * PHP Version Error Page
 * 
 * Displays when the current PHP version is incompatible with ChurchCRM requirements.
 * This page is shown by index.php before autoloader is even loaded.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Version Error - ChurchCRM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            text-align: center;
        }
        .error-code {
            font-size: 80px;
            font-weight: bold;
            color: #dc3545;
            margin: 0;
            display: none;
        }
        .error-title {
            font-size: 32px;
            color: #d32f2f;
            margin: 20px 0 30px 0;
        }
        .error-message {
            font-size: 16px;
            color: #333;
            margin: 15px 0;
            line-height: 1.8;
        }
        .version-info {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .highlight-version {
            background: #ffebee;
            color: #c62828;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 3px;
            border: 1px solid #ef5350;
        }
        .security-note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            display: none;
        }
        .action-required {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
            color: #333;
        }
        .action-required strong {
            color: #d32f2f;
            font-size: 18px;
        }
        .action-required p {
            margin: 10px 0;
            font-size: 15px;
        }
        .help-link {
            margin-top: 30px;
        }
        .help-link a {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .help-link a:hover {
            background: #764ba2;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">PHP Version Not Supported</h1>
        
        <div class="version-info">
            <p><strong>Current Version:</strong> <span class="highlight-version"><?php echo htmlspecialchars(phpversion(), ENT_QUOTES, 'UTF-8'); ?></span></p>
            <p><strong>Required Version:</strong> <span>PHP 8.2 or later</span></p>
        </div>

        <div class="error-message">
            <p>ChurchCRM requires PHP 8.2 or later with active security support.</p>
        </div>

        <div class="action-required">
            <strong>What you need to do:</strong>
            <p>Contact your hosting provider or system administrator and request an upgrade to PHP 8.2 or later.</p>
            <p>Older PHP versions no longer receive security updates, which puts your church data at risk.</p>
        </div>

        <div class="help-link">
            <a href="https://www.php.net/supported-versions.php" target="_blank">View PHP Support Timeline</a>
        </div>
    </div>
</body>
</html>
