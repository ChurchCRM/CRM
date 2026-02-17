<?php
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

// Server environment values
$phpVersion = PHP_VERSION;
$memoryLimit = ini_get('memory_limit');
$uploadMax = ini_get('upload_max_filesize');
$postMax = ini_get('post_max_size');
$maxExecution = ini_get('max_execution_time');

require_once __DIR__ . '/header.php';
?>

<div class="setup-container">
    <!-- Hero Section -->
    <div class="setup-hero">
        <img src="<?= $rootPath ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" class="setup-logo">
        <div class="setup-version">Version <?= htmlspecialchars($softwareVersion, ENT_QUOTES, 'UTF-8') ?></div>
        <p class="setup-tagline">Let's get your church management system up and running.</p>
    </div>

    <!-- Stepper -->
    <div id="setup-stepper" class="bs-stepper">
        <div class="bs-stepper-header" role="tablist">
            <div class="step active" data-target="#step-prerequisites">
                <button type="button" class="step-trigger" role="tab" aria-controls="step-prerequisites" id="step-prerequisites-trigger">
                    <span class="bs-stepper-circle">1</span>
                    <span class="bs-stepper-label">System Check</span>
                </button>
            </div>
            <div class="line"></div>
            <div class="step" data-target="#step-database">
                <button type="button" class="step-trigger" role="tab" aria-controls="step-database" id="step-database-trigger">
                    <span class="bs-stepper-circle">2</span>
                    <span class="bs-stepper-label">Configure</span>
                </button>
            </div>
        </div>

        <div class="bs-stepper-content">
            <form id="setup-form" novalidate>
                
                <!-- STEP 1: System Check -->
                <div id="step-prerequisites" class="content active" role="tabpanel" aria-labelledby="step-prerequisites-trigger">
                    
                    <!-- Status Banner -->
                    <div id="status-banner" class="status-banner status-checking">
                        <div class="status-icon">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        </div>
                        <div class="status-text">
                            <strong>Checking system requirements...</strong>
                            <span class="status-detail">Please wait while we verify your server configuration.</span>
                        </div>
                    </div>

                    <!-- Server Environment (Always visible) -->
                    <div class="check-card">
                        <div class="check-header" data-toggle="collapse" data-target="#server-env-details">
                            <div class="check-title">
                                <i class="fa-solid fa-server check-icon"></i>
                                <span>Server Environment</span>
                            </div>
                            <div class="check-badges">
                                <span class="badge badge-info">PHP <?= $phpVersion ?></span>
                                <span class="badge badge-secondary"><?= $memoryLimit ?> RAM</span>
                                <span class="badge badge-secondary"><?= $uploadMax ?> Upload</span>
                            </div>
                        </div>
                        <div id="server-env-details" class="collapse check-details">
                            <table class="table table-sm mb-0">
                                <tr><td><i class="fa-brands fa-php text-primary mr-2"></i>PHP Version</td><td class="text-right"><strong><?= $phpVersion ?></strong></td></tr>
                                <tr><td><i class="fa-solid fa-memory text-success mr-2"></i>Memory Limit</td><td class="text-right"><strong><?= $memoryLimit ?></strong></td></tr>
                                <tr><td><i class="fa-solid fa-upload text-info mr-2"></i>Upload Max Size</td><td class="text-right"><strong><?= $uploadMax ?></strong></td></tr>
                                <tr><td><i class="fa-solid fa-file-import text-warning mr-2"></i>POST Max Size</td><td class="text-right"><strong><?= $postMax ?></strong></td></tr>
                                <tr><td><i class="fa-solid fa-clock text-secondary mr-2"></i>Max Execution Time</td><td class="text-right"><strong><?= $maxExecution ?>s</strong></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- PHP Extensions -->
                    <div class="check-card">
                        <div class="check-header" data-toggle="collapse" data-target="#php-extensions-collapse">
                            <div class="check-title">
                                <i class="fa-brands fa-php check-icon"></i>
                                <span>PHP Extensions</span>
                            </div>
                            <div class="check-status" id="php-extensions-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </div>
                        </div>
                        <div id="php-extensions-collapse" class="collapse check-details">
                            <table class="table table-sm mb-0" id="php-extensions"></table>
                        </div>
                    </div>

                    <!-- File Permissions -->
                    <div class="check-card">
                        <div class="check-header" data-toggle="collapse" data-target="#filesystem-collapse">
                            <div class="check-title">
                                <i class="fa-solid fa-folder-open check-icon"></i>
                                <span>File Permissions</span>
                            </div>
                            <div class="check-status" id="filesystem-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </div>
                        </div>
                        <div id="filesystem-collapse" class="collapse check-details">
                            <table class="table table-sm mb-0" id="filesystem-checks"></table>
                        </div>
                    </div>

                    <!-- File Integrity -->
                    <div class="check-card">
                        <div class="check-header" data-toggle="collapse" data-target="#integrity-collapse">
                            <div class="check-title">
                                <i class="fa-solid fa-shield-alt check-icon"></i>
                                <span>File Integrity</span>
                            </div>
                            <div class="check-status" id="integrity-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </div>
                        </div>
                        <div id="integrity-collapse" class="collapse check-details">
                            <table class="table table-sm mb-0" id="integrity-checks"></table>
                        </div>
                    </div>

                    <!-- Orphaned Files (hidden initially) -->
                    <div class="check-card" id="orphaned-files-section" style="display: none;">
                        <div class="check-header check-header-warning" data-toggle="collapse" data-target="#orphaned-collapse">
                            <div class="check-title">
                                <i class="fa-solid fa-exclamation-triangle check-icon text-warning"></i>
                                <span>Orphaned Files</span>
                            </div>
                            <div class="check-status" id="orphaned-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </div>
                        </div>
                        <div id="orphaned-collapse" class="collapse check-details">
                            <p class="small text-muted mb-2 px-3 pt-2">These files are not part of the official release and should be reviewed.</p>
                            <table class="table table-sm mb-0" id="orphaned-checks"></table>
                        </div>
                    </div>

                    <!-- Locale Support -->
                    <div class="check-card">
                        <div class="check-header" data-toggle="collapse" data-target="#locale-support-collapse">
                            <div class="check-title">
                                <i class="fa-solid fa-globe check-icon"></i>
                                <span>Locale Support</span>
                            </div>
                            <div class="check-status" id="locale-support-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </div>
                        </div>
                        <div id="locale-support-collapse" class="collapse check-details">
                            <div id="locale-support-summary" class="alert alert-info mb-0 mx-3 mt-3 py-2 small">
                                <i class="fa-solid fa-spinner fa-spin"></i> Detecting available locales...
                            </div>
                            <table class="table table-sm mb-0" id="locale-support-table"></table>
                        </div>
                    </div>

                    <!-- Hidden warning alert for status text -->
                    <div class="alert alert-warning d-none" id="prerequisites-war"></div>

                    <!-- Action Buttons -->
                    <div class="setup-actions">
                        <button type="button" class="btn btn-lg btn-primary" id="prerequisites-next-btn" disabled>
                            Continue to Configuration <i class="fa-solid fa-arrow-right ml-2"></i>
                        </button>
                        <button type="button" class="btn btn-outline-warning" id="prerequisites-force-btn" style="display: none;">
                            <i class="fa-solid fa-exclamation-triangle mr-2"></i>Continue Anyway
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Database Setup -->
                <div id="step-database" class="content" role="tabpanel" aria-labelledby="step-database-trigger">
                    <div class="step-intro">
                        <h4>Connect Your Database</h4>
                        <p class="text-muted">ChurchCRM requires MySQL 5.7+ or MariaDB 10.2+ with full privileges.</p>
                    </div>

                    <div class="form-card">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="DB_SERVER_NAME">Database Server <span class="text-danger">*</span></label>
                                    <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="<?= $DB_SERVER_NAME ?? 'localhost' ?>"
                                           class="form-control" placeholder="localhost" maxlength="64" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="DB_SERVER_PORT">Port <span class="text-danger">*</span></label>
                                    <input type="text" name="DB_SERVER_PORT" id="DB_SERVER_PORT" value="<?= $DB_SERVER_PORT ?? '3306' ?>"
                                           class="form-control" placeholder="3306" pattern="[0-9]+" maxlength="16" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="DB_NAME">Database Name <span class="text-danger">*</span></label>
                            <input type="text" name="DB_NAME" id="DB_NAME" value="<?= $DB_NAME ?? '' ?>"
                                   class="form-control" placeholder="churchcrm" maxlength="64" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group">
                            <label for="DB_USER">Username <span class="text-danger">*</span></label>
                            <input type="text" name="DB_USER" id="DB_USER" value="<?= $DB_USER ?? '' ?>"
                                   class="form-control" placeholder="churchcrm" maxlength="64" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="DB_PASSWORD">Password</label>
                                    <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value=""
                                           class="form-control" maxlength="255">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-0">
                                    <label for="DB_PASSWORD_CONFIRM">Confirm Password</label>
                                    <input type="password" name="DB_PASSWORD_CONFIRM" id="DB_PASSWORD_CONFIRM" value=""
                                           class="form-control" data-match="#DB_PASSWORD" maxlength="255">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced Settings (collapsed) -->
                    <div class="form-card mt-3 advanced-settings">
                        <div class="advanced-header" data-toggle="collapse" data-target="#advanced-settings-collapse">
                            <i class="fa-solid fa-cog mr-2"></i>
                            <span>Advanced Settings</span>
                            <small class="text-muted ml-2">(auto-detected)</small>
                            <i class="fa-solid fa-chevron-down ml-auto toggle-icon"></i>
                        </div>
                        <div id="advanced-settings-collapse" class="collapse">
                            <div class="advanced-body">
                                <p class="small text-muted mb-3">These settings are auto-detected from your server. Only change them if you know what you're doing.</p>
                                <div class="form-group">
                                    <label for="ROOT_PATH">Root Path</label>
                                    <input type="text" name="ROOT_PATH" id="ROOT_PATH"
                                           value="<?= $rootPath ?>" class="form-control"
                                           aria-describedby="ROOT_PATH_HELP"
                                           pattern="^(|\/[a-zA-Z0-9_\-\.\/]*)$"
                                           placeholder="Leave empty for root installation"
                                           maxlength="64">
                                    <div class="invalid-feedback"></div>
                                    <small id="ROOT_PATH_HELP" class="form-text text-muted">
                                        Example: <code>/churchcrm</code> for subdirectory, or leave empty for root.
                                    </small>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="URL">Base URL <span class="text-danger">*</span></label>
                                    <input type="url" name="URL" id="URL" value="<?= $URL ?>" class="form-control"
                                           aria-describedby="URL_HELP"
                                           placeholder="https://your-domain.com/"
                                           required>
                                    <div class="invalid-feedback"></div>
                                    <small id="URL_HELP" class="form-text text-muted">
                                        Must start with <code>http://</code> or <code>https://</code> and end with <code>/</code>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="setup-actions">
                        <button type="button" class="btn btn-lg btn-success" id="submit-setup">
                            <i class="fa-solid fa-database mr-2"></i>Install ChurchCRM
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="database-prev-btn">
                            <i class="fa-solid fa-arrow-left mr-1"></i>Back to System Check
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>
</div>

<!-- Setup Progress Modal -->
<div class="modal fade" id="setupModal" tabindex="-1" role="dialog" aria-labelledby="setupModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div id="setup-progress">
                    <div class="spinner-border text-primary mb-4" style="width: 3rem; height: 3rem;" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <h4>Installing ChurchCRM</h4>
                    <p class="text-muted">Creating database tables and configuring your system...</p>
                </div>
                <div id="setup-success" style="display: none;">
                    <div class="success-checkmark mb-4">
                        <i class="fa-solid fa-circle-check text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="mb-4">Installation Complete!</h3>
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <p class="text-muted mb-2">Sign in with your admin account:</p>
                            <div class="d-flex justify-content-center align-items-center mb-2">
                                <span class="text-muted mr-2">Username:</span>
                                <code class="h5 mb-0">admin</code>
                            </div>
                            <div class="d-flex justify-content-center align-items-center">
                                <span class="text-muted mr-2">Password:</span>
                                <code class="h5 mb-0">changeme</code>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                        <strong>Important:</strong> Change your password immediately after logging in.
                    </div>
                </div>
                <div id="setup-error" style="display: none;">
                    <i class="fa-solid fa-circle-xmark text-danger mb-4" style="font-size: 4rem;"></i>
                    <h4>Installation Failed</h4>
                    <div class="alert alert-danger mt-3 text-left">
                        <p id="setup-error-message" class="mb-0"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0" id="setup-footer" style="display: none;">
                <button type="button" class="btn btn-lg btn-primary px-5" id="continue-to-login">
                    Go to Login <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Force Install Confirmation Modal -->
<div class="modal fade" id="forceInstallModal" tabindex="-1" role="dialog" aria-labelledby="forceInstallModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="forceInstallModalLabel">
                    <i class="fa-solid fa-exclamation-triangle mr-2"></i>Proceed Anyway?
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Some system requirements were not met. Proceeding may cause:</p>
                <ul>
                    <li>Missing or broken functionality</li>
                    <li>Security vulnerabilities</li>
                    <li>Unpredictable behavior</li>
                </ul>
                <p class="mb-0 font-weight-bold">We recommend fixing the issues before continuing.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Go Back</button>
                <button type="button" class="btn btn-warning" id="confirm-force-install">
                    Continue Anyway
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>
