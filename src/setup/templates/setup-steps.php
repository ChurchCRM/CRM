<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

// Setup wizard - standalone, no Config.php dependency
$rootPath = $GLOBALS['CHURCHCRM_SETUP_ROOT_PATH'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '80') === '443');
$scheme = $isHttps ? 'https' : 'http';
$normalizedRootPath = rtrim($rootPath, '/');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$URL = $scheme . '://' . $host . ($normalizedRootPath === '' ? '' : $normalizedRootPath) . '/';
$sPageTitle = 'ChurchCRM – Setup';

// Get version from composer.json
$composerFile = file_get_contents(__DIR__ . '/../../composer.json');
$composerJson = json_decode($composerFile, true);
$softwareVersion = $composerJson['version'] ?? 'Unknown';

require_once __DIR__ . '/header.php';
?>

<div class="setup-container">
    <div class="setup-hero">
        <img src="<?= $rootPath ?>/Images/churchcrm-logo-ink-blue.svg" alt="ChurchCRM" class="setup-logo">
        <h1 class="setup-title"><?= gettext('Install ChurchCRM') ?></h1>
        <p class="setup-lead"><?= gettext('Have the database details from your web host ready. It takes about two minutes.') ?></p>
    </div>

    <form id="setup-form" novalidate>
        <div class="card setup-summary">
            <section id="step-prerequisites" class="setup-row">
                <div id="status-banner" class="setup-row-main status-checking" role="status" aria-live="polite">
                    <span class="status-icon"><i class="fa-solid fa-spinner fa-spin"></i></span>
                    <span class="setup-row-label"><?= gettext('Server') ?></span>
                    <span class="status-text">
                        <strong><?= gettext('Checking…') ?></strong>
                        <span class="status-detail"></span>
                    </span>
                    <button type="button" class="btn btn-link setup-row-action" data-bs-toggle="collapse" data-bs-target="#server-details" aria-expanded="false" aria-controls="server-details"><?= gettext('Details') ?></button>
                </div>

                <div id="server-details" class="collapse">
                    <div class="check-list">
                        <div class="check-row">
                            <button type="button" class="check-header" data-bs-toggle="collapse" data-bs-target="#php-extensions-collapse" aria-expanded="false">
                                <span class="check-title"><?= gettext('Server software ChurchCRM needs') ?></span>
                                <span class="check-status" id="php-extensions-status"><i class="fa-solid fa-spinner fa-spin text-secondary"></i></span>
                            </button>
                            <div id="php-extensions-collapse" class="collapse check-details">
                                <table class="table table-sm table-vcenter mb-0" id="php-extensions"></table>
                            </div>
                        </div>
                        <div class="check-row">
                            <button type="button" class="check-header" data-bs-toggle="collapse" data-bs-target="#filesystem-collapse" aria-expanded="false">
                                <span class="check-title"><?= gettext('Permission to save settings and photos') ?></span>
                                <span class="check-status" id="filesystem-status"><i class="fa-solid fa-spinner fa-spin text-secondary"></i></span>
                            </button>
                            <div id="filesystem-collapse" class="collapse check-details">
                                <table class="table table-sm table-vcenter mb-0" id="filesystem-checks"></table>
                            </div>
                        </div>
                        <div class="check-row">
                            <button type="button" class="check-header" data-bs-toggle="collapse" data-bs-target="#integrity-collapse" aria-expanded="false">
                                <span class="check-title"><?= gettext('ChurchCRM files match the release') ?></span>
                                <span class="check-status" id="integrity-status"><i class="fa-solid fa-spinner fa-spin text-secondary"></i></span>
                            </button>
                            <div id="integrity-collapse" class="collapse check-details">
                                <table class="table table-sm table-vcenter mb-0" id="integrity-checks"></table>
                            </div>
                        </div>
                        <div class="check-row d-none" id="orphaned-files-section">
                            <button type="button" class="check-header" data-bs-toggle="collapse" data-bs-target="#orphaned-collapse" aria-expanded="false">
                                <span class="check-title"><?= gettext('Files left over from an older version') ?></span>
                                <span class="check-status" id="orphaned-status"><i class="fa-solid fa-spinner fa-spin text-secondary"></i></span>
                            </button>
                            <div id="orphaned-collapse" class="collapse check-details">
                                <p class="small text-secondary mb-2 px-3 pt-2"><?= gettext('These files are not part of the ChurchCRM release. Your web host can remove them.') ?></p>
                                <table class="table table-sm table-vcenter mb-0" id="orphaned-checks"></table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="step-website" class="setup-row">
                <div class="setup-row-main">
                    <span class="status-icon text-success"><i class="fa-solid fa-circle-check"></i></span>
                    <span class="setup-row-label"><?= gettext('Web address') ?></span>
                    <span class="status-text"><strong id="setup-url-display"><?= htmlspecialchars($URL, ENT_QUOTES, 'UTF-8') ?></strong></span>
                    <button type="button" class="btn btn-link setup-row-action" data-bs-toggle="collapse" data-bs-target="#advanced-settings-collapse" aria-expanded="false" aria-controls="advanced-settings-collapse"><?= gettext('Change') ?></button>
                </div>
                <div id="advanced-settings-collapse" class="collapse">
                    <div class="setup-row-body">
                        <p class="small text-secondary"><?= gettext('This is where people will open ChurchCRM. Change it only if they will use a different address than this page.') ?></p>
                        <div class="mb-3">
                            <label class="form-label required" for="URL"><?= gettext('Web address') ?></label>
                            <input type="url" name="URL" id="URL" value="<?= $URL ?>" class="form-control" aria-describedby="URL_HELP" required>
                            <div class="invalid-feedback"></div>
                            <small id="URL_HELP" class="form-text"><?= sprintf(gettext('Starts with %1$s or %2$s and ends with %3$s.'), '<code>http://</code>', '<code>https://</code>', '<code>/</code>') ?></small>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="ROOT_PATH"><?= gettext('Folder') ?></label>
                            <input type="text" name="ROOT_PATH" id="ROOT_PATH" value="<?= $rootPath ?>" class="form-control"
                                   aria-describedby="ROOT_PATH_HELP" pattern="^(|\/[a-zA-Z0-9_\-\.\/]*)$" maxlength="64">
                            <div class="invalid-feedback"></div>
                            <small id="ROOT_PATH_HELP" class="form-text"><?= sprintf(gettext('Empty when ChurchCRM is at the top of the site. Otherwise the folder, like %s.'), '<code>/churchcrm</code>') ?></small>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section id="step-database" class="card setup-card">
            <div class="card-body">
                <h2 class="setup-card-title"><?= gettext('Connect your database') ?></h2>
                <p class="text-secondary"><?= sprintf(gettext('Create a MySQL or MariaDB database in your hosting control panel first (in cPanel: %s), then copy its details here. ChurchCRM supports MySQL 8.4+ and MariaDB 10.11+ (the LTS releases).'), '<strong>MySQL Databases</strong>') ?></p>

                <div class="row">
                    <div class="col-sm-8 mb-3">
                        <label class="form-label required" for="DB_SERVER_NAME"><?= gettext('Database host') ?></label>
                        <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="<?= $DB_SERVER_NAME ?? '' ?>"
                               class="form-control" maxlength="64" required autocomplete="off" aria-describedby="DB_SERVER_NAME_HELP">
                        <div class="invalid-feedback"></div>
                        <small id="DB_SERVER_NAME_HELP" class="form-text"><?= sprintf(gettext('Often %1$s. Some hosts give an address like %2$s.'), '<code>localhost</code>', '<code>mysql.example.com</code>') ?></small>
                    </div>
                    <div class="col-sm-4 mb-3">
                        <label class="form-label required" for="DB_SERVER_PORT"><?= gettext('Port') ?></label>
                        <input type="text" name="DB_SERVER_PORT" id="DB_SERVER_PORT" value="<?= $DB_SERVER_PORT ?? '3306' ?>"
                               class="form-control" pattern="[0-9]+" maxlength="16" inputmode="numeric" required aria-describedby="DB_SERVER_PORT_HELP">
                        <div class="invalid-feedback"></div>
                        <small id="DB_SERVER_PORT_HELP" class="form-text"><?= gettext('Usually 3306.') ?></small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="DB_NAME"><?= gettext('Database name') ?></label>
                    <input type="text" name="DB_NAME" id="DB_NAME" value="<?= $DB_NAME ?? '' ?>"
                           class="form-control" maxlength="64" required autocomplete="off" aria-describedby="DB_NAME_HELP">
                    <div class="invalid-feedback"></div>
                    <small id="DB_NAME_HELP" class="form-text"><?= sprintf(gettext('The name you gave the database. Shared hosts often add your account name in front, like %s.'), '<code>account_churchcrm</code>') ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="DB_USER"><?= gettext('Database user') ?></label>
                    <input type="text" name="DB_USER" id="DB_USER" value="<?= $DB_USER ?? '' ?>"
                           class="form-control" maxlength="64" required autocomplete="off" aria-describedby="DB_USER_HELP">
                    <div class="invalid-feedback"></div>
                    <small id="DB_USER_HELP" class="form-text"><?= gettext('The user you added to that database. Not your hosting login.') ?></small>
                </div>
                <div class="mb-3">
                    <label class="form-label required" for="DB_PASSWORD"><?= gettext('Database password') ?></label>
                    <div class="input-group">
                        <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value=""
                               class="form-control" maxlength="255" autocomplete="new-password" aria-describedby="DB_PASSWORD_HELP">
                        <button type="button" class="btn btn-outline-secondary" id="toggle-db-password" aria-controls="DB_PASSWORD" aria-pressed="false"><?= gettext('Show') ?></button>
                    </div>
                    <div class="invalid-feedback"></div>
                    <small id="DB_PASSWORD_HELP" class="form-text"><?= gettext('The password for that database user. ChurchCRM checks these details when you click Install.') ?></small>
                </div>

                <div id="db-connection-result" class="alert alert-danger d-none" role="status" aria-live="polite"></div>
            </div>
            <div class="card-footer">
                <button type="button" class="btn btn-primary btn-lg w-100" id="submit-setup" aria-describedby="db-connection-result">
                    <?= gettext('Install ChurchCRM') ?>
                </button>
            </div>
        </section>
    </form>

    <footer class="setup-help">
        <p class="mb-2"><strong><?= gettext('Need help?') ?></strong></p>
        <p class="setup-help-links mb-3">
            <a href="<?= InputUtils::escapeAttribute(SystemURLs::attributed('https://docs.churchcrm.io/installation', 'setup_installation_guide')) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-book me-1"></i><?= gettext('Installation guide') ?></a>
            <a href="<?= InputUtils::escapeAttribute(SystemURLs::attributed('https://docs.churchcrm.io/administration/troubleshooting', 'setup_troubleshooting')) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-life-ring me-1"></i><?= gettext('Troubleshooting') ?></a>
            <a href="https://discord.gg/tuWyFzj3Nj" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-discord me-1"></i><?= gettext('Ask on Discord') ?></a>
            <a href="https://github.com/ChurchCRM/CRM/issues" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-github me-1"></i><?= gettext('Report a problem') ?></a>
        </p>
        <p class="setup-help-social mb-0">
            <?php $socialLinkSeparator = ''; require __DIR__ . '/../../Include/partials/social-links.php'; ?>
        </p>
    </footer>
</div>

<!-- Setup Progress Modal -->
<div class="modal fade" id="setupModal" tabindex="-1" role="dialog" aria-labelledby="setupModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div id="setup-success">
                    <div class="success-checkmark mb-4">
                        <i class="fa-solid fa-circle-check text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-4"><?= gettext('Installation Complete!') ?></h3>
                    <div class="alert alert-danger text-start d-none" id="db-version-warning" role="alert">
                        <h4 class="alert-title"><?= gettext('Database security risk') ?></h4>
                        <p id="db-version-message"></p>
                        <p class="mb-0"><?= gettext('Administrators will see this warning on every page until the database is moved to a version that still gets security fixes. Moving now, while there is little data, is easiest.') ?></p>
                    </div>
                    <p class="text-body-secondary small"><?= gettext('Your data is yours. To count installs, ChurchCRM reports that it was installed or upgraded and whether that worked, a once-a-day check-in while it is in use, plus the version number, your country, the type of database, and a random installation ID. It never reports member, giving, or other church records, your church name, your web address or server name, or email addresses. You can turn this off at any time in Admin → Telemetry; a failed install or upgrade is still reported. Warnings, error details, and page views are only shared if you choose.') ?></p>
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <p class="text-body-secondary mb-2"><?= gettext('Sign in with your admin account') ?>:</p>
                            <div class="d-flex justify-content-center align-items-center mb-2">
                                <span class="text-body-secondary me-2"><?= gettext('Username') ?>:</span>
                                <code class="h5 mb-0">admin</code>
                            </div>
                            <div class="d-flex justify-content-center align-items-center">
                                <span class="text-body-secondary me-2"><?= gettext('Password') ?>:</span>
                                <code class="h5 mb-0">changeme</code>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <?= gettext('Change your password right after you sign in.') ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0" id="setup-footer">
                <button type="button" class="btn btn-lg btn-primary px-5" id="continue-to-login">
                    <?= gettext('Go to Login') ?> <i class="fa-solid fa-arrow-right ms-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
