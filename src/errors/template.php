<?php
/**
 * Standalone Tabler layout for pages that render without the application:
 * the setup wizard, the PHP requirements check and the public error pages.
 *
 * Plain PHP with no autoloader, Config.php or gettext dependency, written in
 * syntax that still parses on the unsupported PHP versions php-error.php reports.
 * Styles come from the locally built /skin/v2/error.min.css (or setup.min.css).
 */

function standaloneEscape($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/**
 * Install root path ('' or '/churchcrm') for a page served from a first-level
 * folder such as /errors, /setup or /session.
 */
function standaloneRootPath()
{
    $scriptName = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/errors/index.php');
    $rootPath = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/.');
    if ($rootPath !== '') {
        return $rootPath;
    }

    // Some hosts drop the install folder from SCRIPT_NAME; REQUEST_URI still has it.
    $folder = '/' . basename(dirname($scriptName)) . '/';
    $requestPath = (string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
    $position = strpos($requestPath . '/', $folder);

    $prefix = $folder !== '//' && $position > 0 ? preg_replace('#/+#', '/', substr($requestPath, 0, $position)) : '';

    return preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $prefix) === 1 ? $prefix : '';
}

/**
 * ChurchCRM version from composer.json (single source of truth for app version)
 */
function getChurchCRMVersion()
{
    $composerFile = __DIR__ . '/../composer.json';
    if (file_exists($composerFile)) {
        $composer = json_decode(@file_get_contents($composerFile), true);
        if (is_array($composer) && !empty($composer['version'])) {
            return $composer['version'];
        }
    }
    return 'Unknown';
}

function getServerInfo()
{
    return [
        'PHP Version' => phpversion(),
        'PHP SAPI' => php_sapi_name(),
        'Server Software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
        'OS' => function_exists('php_uname') ? php_uname() : 'Unknown',
        'Memory Limit' => ini_get('memory_limit'),
        'ChurchCRM Version' => getChurchCRMVersion(),
    ];
}

/**
 * Markdown body for a prefilled GitHub issue. Pages add their own sections
 * before the server information.
 */
function buildGitHubIssueBody($title, $customSections = '')
{
    $body = "## $title\n\n";

    if (!empty($customSections)) {
        $body .= $customSections . "\n\n";
    }

    $body .= "### System Information\n\n";
    foreach (getServerInfo() as $key => $value) {
        $body .= "- **$key:** `" . str_replace('`', '\\`', (string) $value) . "`\n";
    }

    return $body;
}

/**
 * Open the standalone document, through the opening <body>. theme.min.js
 * follows the browser's light/dark preference before first paint.
 *
 * @param string $rootPath  install root path ('' or '/churchcrm')
 * @param string $title     document title (plain text)
 * @param string $bundle    skin/v2 stylesheet: 'error' or 'setup'
 * @param string $bodyClass extra classes for <body>
 */
function renderStandaloneHeader($rootPath, $title, $bundle = 'error', $bodyClass = '')
{
    $skin = $rootPath . '/skin/v2/';
    ?>
<!DOCTYPE html>
<html lang="en" data-theme-mode="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= standaloneEscape($title) ?></title>
    <link rel="icon" href="<?= standaloneEscape($rootPath . '/favicon.ico') ?>" type="image/x-icon">
    <script src="<?= standaloneEscape($skin . 'theme.min.js') ?>"></script>
    <link rel="stylesheet" href="<?= standaloneEscape($skin . $bundle . '.min.css') ?>">
</head>
<body class="<?= standaloneEscape(trim('standalone-page ' . $bodyClass)) ?>">
    <?php
}

function renderStandaloneFooter()
{
    ?>
</body>
</html>
    <?php
}

/**
 * The ChurchCRM mark in the colours for the current theme.
 */
function renderStandaloneBrand($rootPath)
{
    ?>
            <div class="text-center mb-4">
                <img src="<?= standaloneEscape($rootPath . '/Images/churchcrm-symbol-ink-blue.svg') ?>" alt="ChurchCRM" class="standalone-brand hide-theme-dark">
                <img src="<?= standaloneEscape($rootPath . '/Images/churchcrm-symbol-paper-blue.svg') ?>" alt="ChurchCRM" class="standalone-brand hide-theme-light">
            </div>
    <?php
}

/**
 * Render a complete error page through the standalone layout.
 *
 * @param string $rootPath  install root path ('' or '/churchcrm')
 * @param string $title     page title (plain text)
 * @param string $icon      decorative icon shown above the title
 * @param string $variant   Tabler colour for the status bar: danger, warning or secondary
 * @param string $content   HTML built by the page, with request values already escaped
 * @param string $issueBody Markdown body for the prefilled GitHub issue
 */
function renderErrorPage($rootPath, $title, $icon, $variant, $content, $issueBody)
{
    $issueUrl = 'https://github.com/ChurchCRM/CRM/issues/new?title=' . rawurlencode('[Error] ' . $title)
        . '&body=' . rawurlencode($issueBody);

    renderStandaloneHeader($rootPath, $title);
    ?>
    <main id="main-content" class="page page-center">
        <div class="container container-tight py-4">
            <?php renderStandaloneBrand($rootPath); ?>
            <div class="card card-md">
                <div class="card-status-top bg-<?= standaloneEscape($variant) ?>"></div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="standalone-icon mb-2" aria-hidden="true"><?= standaloneEscape($icon) ?></div>
                        <h1 class="h2 mb-0"><?= standaloneEscape($title) ?></h1>
                    </div>
                    <?= $content ?>
                </div>
                <div class="card-footer">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= standaloneEscape($rootPath . '/') ?>" class="btn btn-primary flex-grow-1"><?= standaloneEscape(function_exists('gettext') ? gettext('Return Home') : 'Return Home') ?></a>
                        <a href="<?= standaloneEscape($issueUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary flex-grow-1"><?= standaloneEscape(function_exists('gettext') ? gettext('Report Issue') : 'Report Issue') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php
    renderStandaloneFooter();
}
