<?php
// Setup wizard - standalone, no Config.php dependency
$rootPath = $GLOBALS['CHURCHCRM_SETUP_ROOT_PATH'] ?? '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '80') === '443');
$scheme = $isHttps ? 'https' : 'http';
$normalizedRootPath = rtrim($rootPath, '/');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$URL = $scheme . '://' . $host . ($normalizedRootPath === '' ? '' : $normalizedRootPath) . '/';
$sPageTitle = 'ChurchCRM – Setup';

require_once __DIR__ . '/header.php';
?>
<div class="container-fluid">
<div class="jumbotron text-center">
    <h1 class="display-4">Welcome to ChurchCRM Setup Wizard</h1>
    <p class="lead">Let's get your church management system configured and ready to use.</p>
</div>
<div id="setup-stepper" class="bs-stepper">
    <div class="bs-stepper-header" role="tablist">
        <div class="step active" data-target="#step-prerequisites">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-prerequisites" id="step-prerequisites-trigger">
                <span class="bs-stepper-circle">1</span>
                <span class="bs-stepper-label">System Check</span>
            </button>
        </div>
        <div class="line"></div>
        <div class="step" data-target="#step-location">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-location" id="step-location-trigger">
                <span class="bs-stepper-circle">2</span>
                <span class="bs-stepper-label">Install Location</span>
            </button>
        </div>
        <div class="line"></div>
        <div class="step" data-target="#step-database">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-database" id="step-database-trigger">
                <span class="bs-stepper-circle">3</span>
                <span class="bs-stepper-label">Database Setup</span>
            </button>
        </div>
    </div>
    <div class="bs-stepper-content">
        <form id="setup-form" novalidate>
            <div id="step-prerequisites" class="content active" role="tabpanel" aria-labelledby="step-prerequisites-trigger">
                <div class="row">
                    <!-- Left Column - System Checks -->
                    <div class="col-lg-8">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fa-solid fa-check-circle mr-2"></i>System Requirements Check</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning alert-dismissible fade show" role="alert" id="prerequisites-war">
                                    <strong><i class="fa-solid fa-exclamation-triangle"></i> Prerequisites Not Met</strong>
                                    <p class="mb-0 mt-1">Some server requirements are not satisfied. ChurchCRM may not function correctly.</p>
                                </div>

                                <!-- File Permissions - Collapsible (Collapsed by default) -->
                                <h6 class="mb-3">
                                    <a href="#filesystem-collapse" data-toggle="collapse" class="text-dark text-decoration-none collapsed" aria-expanded="false" aria-controls="filesystem-collapse">
                                        <span>
                                            <i class="fa-solid fa-folder-open mr-2"></i>File Permissions
                                            <span id="filesystem-status" class="ml-2">
                                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                                            </span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </a>
                                </h6>
                                <div id="filesystem-collapse" class="collapse">
                                    <table class="table table-sm table-condensed mb-4" id="filesystem-checks"></table>
                                </div>

                                <!-- File Integrity - Collapsible (Collapsed by default) -->
                                <h6 class="mb-3">
                                    <a href="#integrity-collapse" data-toggle="collapse" class="text-dark text-decoration-none collapsed" aria-expanded="false" aria-controls="integrity-collapse">
                                        <span>
                                            <i class="fa-solid fa-shield-alt mr-2"></i>File Integrity
                                            <span id="integrity-status" class="ml-2">
                                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                                            </span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </a>
                                </h6>
                                <div id="integrity-collapse" class="collapse">
                                    <table class="table table-sm table-condensed mb-4" id="integrity-checks"></table>
                                </div>

                                <!-- Orphaned Files - Collapsible (Collapsed by default, hidden initially) -->
                                <div id="orphaned-files-section" style="display: none;">
                                    <h6 class="mb-3">
                                        <a href="#orphaned-collapse" data-toggle="collapse" class="text-danger text-decoration-none collapsed" aria-expanded="false" aria-controls="orphaned-collapse">
                                            <span>
                                                <i class="fa-solid fa-exclamation-triangle mr-2"></i>Orphaned Files
                                                <span id="orphaned-status" class="ml-2">
                                                    <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                                                </span>
                                            </span>
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </a>
                                    </h6>
                                    <div id="orphaned-collapse" class="collapse">
                                        <p class="small text-muted mb-2">These files are not part of the official release and should be reviewed.</p>
                                        <table class="table table-sm table-condensed mb-0" id="orphaned-checks"></table>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <button type="button" class="btn btn-primary" id="prerequisites-next-btn" disabled>Next</button>
                                <button type="button" class="btn btn-warning ml-2" id="prerequisites-force-btn" style="display: none;">
                                    <i class="fa-solid fa-exclamation-triangle"></i> Force Install
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Server Information -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0"><i class="fa-solid fa-server mr-2"></i>Server Information</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm table-borderless mb-3">
                                    <tr>
                                        <td class="text-muted">PHP Version</td>
                                        <td class="text-right font-weight-bold"><?php echo PHP_VERSION ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Upload Size</td>
                                        <td class="text-right font-weight-bold"><?php echo ini_get('upload_max_filesize') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">POST Size</td>
                                        <td class="text-right font-weight-bold"><?php echo ini_get('post_max_size') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Memory Limit</td>
                                        <td class="text-right font-weight-bold"><?php echo ini_get('memory_limit') ?></td>
                                    </tr>
                                </table>

                                <!-- PHP Extensions - Collapsible -->
                                <h6 class="mb-2">
                                    <a href="#php-extensions-collapse" data-toggle="collapse" class="text-dark text-decoration-none collapsed" aria-expanded="false" aria-controls="php-extensions-collapse">
                                        <span>
                                            <i class="fa-brands fa-php mr-2"></i>PHP Extensions
                                            <span id="php-extensions-status" class="ml-2">
                                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                                            </span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </a>
                                </h6>
                                <div id="php-extensions-collapse" class="collapse">
                                    <table class="table table-sm table-condensed mb-0" id="php-extensions"></table>
                                </div>

                                <!-- Locale Support - Collapsible -->
                                <h6 class="mb-2 mt-3">
                                    <a href="#locale-support-collapse" data-toggle="collapse" class="text-dark text-decoration-none collapsed" aria-expanded="false" aria-controls="locale-support-collapse">
                                        <span>
                                            <i class="fa-solid fa-globe mr-2"></i>Locale Support
                                            <span id="locale-support-status" class="ml-2">
                                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                                            </span>
                                        </span>
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </a>
                                </h6>
                                <div id="locale-support-collapse" class="collapse">
                                    <div id="locale-support-summary" class="alert alert-info mb-2">
                                        <i class="fa-solid fa-spinner fa-spin"></i> Detecting available locales...
                                    </div>
                                    <table class="table table-sm table-condensed mb-0" id="locale-support-table"></table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="step-location" class="content" role="tabpanel" aria-labelledby="step-location-trigger">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa-solid fa-map-marker-alt mr-2"></i>Installation Location Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="ROOT_PATH">Root Path</label>
                            <input type="text" name="ROOT_PATH" id="ROOT_PATH"
                                   value="<?= $rootPath ?>" class="form-control"
                                   aria-describedby="ROOT_PATH_HELP"
                                   pattern="^(|\/[a-zA-Z0-9_\-\.\/]*)$"
                                   maxlength="64">
                            <div class="invalid-feedback"></div>
                            <small id="ROOT_PATH_HELP" class="form-text text-muted">
                                <strong>Examples:</strong><br>
                                <code>/churchcrm</code> (for <code>http://www.yourdomain.com/churchcrm</code>)<br>
                                <code>/</code> or leave empty (for <code>http://www.yourdomain.com</code>)<br>
                                <strong>Rules:</strong> Must start with a slash (<code>/</code>) if not empty. Do <b>not</b> end with a slash. Case sensitive. Only letters, numbers, <code>_</code>, <code>-</code>, <code>.</code>, <code>/</code> allowed.
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="URL">Base URL</label>
                            <input type="url" name="URL" id="URL" value="<?= $URL ?>" class="form-control"
                                   aria-describedby="URL_HELP"
                                   pattern="https?://[a-zA-Z0-9\-\.]+(:[0-9]+)?(/[a-zA-Z0-9\-\._~:/?#\[\]@!$&'()*+,;=%]*)?/$"
                                   title="Must be a valid URL starting with http:// or https:// and ending with a forward slash"
                                   required>
                            <div class="invalid-feedback"></div>
                            <small id="URL_HELP" class="form-text text-muted">
                                <strong>Examples:</strong><br>
                                <code>https://www.yourdomain.com/</code> (at domain root)<br>
                                <code>https://www.yourdomain.com/churchcrm/</code> (in subdirectory)<br>
                                <code>https://www.yourdomain.com:8080/churchcrm/</code> (with custom port)<br>
                                <strong>Required rules:</strong><br>
                                • Must start with <code>http://</code> or <code>https://</code><br>
                                • Must end with a trailing slash (<code>/</code>)<br>
                                • Use <code>https://</code> when possible for security<br>
                                • Include custom port if your server uses a non-standard port (not 80 or 443)
                            </small>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <button type="button" class="btn btn-secondary" id="location-prev-btn">Previous</button>
                        <button type="button" class="btn btn-primary" id="location-next-btn">Next</button>
                    </div>
                </div>
            </div>
        <div id="step-database" class="content" role="tabpanel" aria-labelledby="step-database-trigger">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fa-solid fa-database mr-2"></i>Database Setup</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong><i class="fa-solid fa-info-circle"></i> Database Requirements</strong>
                            <p class="mb-0 mt-2">ChurchCRM requires <strong>MySQL 5.7+</strong> or <strong>MariaDB 10.2+</strong>. The database user must have permissions to create tables, views, and execute stored procedures.</p>
                        </div>
                        <div class="form-group">
                            <label for="DB_SERVER_NAME">Server Name</label>
                            <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="<?= $DB_SERVER_NAME ?>"
                                   class="form-control" maxlength="64" 
                                   aria-describedby="DB_SERVER_NAME_HELP" 
                                   required>
                            <div class="invalid-feedback"></div>
                            <small id="DB_SERVER_NAME_HELP" class="form-text text-muted">Use localhost over 127.0.0.1</small>
                        </div>
                        <div class="form-group">
                            <label for="DB_SERVER_PORT">Server Port</label>
                            <input type="text" name="DB_SERVER_PORT" id="DB_SERVER_PORT" value="<?= $DB_SERVER_PORT ?>"
                                   class="form-control" maxlength="16"
                                   pattern="[0-9]+"
                                   aria-describedby="DB_SERVER_PORT_HELP"
                                   required>
                            <div class="invalid-feedback"></div>
                            <small id="DB_SERVER_PORT_HELP" class="form-text text-muted">Default MySQL Port is 3306</small>
                        </div>
                        <div class="form-group">
                            <label for="DB_NAME">Database Name</label>
                            <input type="text" name="DB_NAME" id="DB_NAME" value="<?= $DB_NAME ?>"
                                   class="form-control" maxlength="64" 
                                   placeholder="churchcrm"
                                   aria-describedby="DB_NAME_HELP"
                                   required>
                            <div class="invalid-feedback"></div>
                            <small id="DB_NAME_HELP" class="form-text text-muted"></small>
                        </div>
                        <div class="form-group">
                            <label for="DB_USER">Database User</label>
                            <input type="text" name="DB_USER" id="DB_USER" value="<?= $DB_USER ?>"
                                   class="form-control" maxlength="64" 
                                   placeholder="churchcrm"
                                   aria-describedby="DB_USER_HELP"
                                   required>
                            <div class="invalid-feedback"></div>
                            <small id="DB_USER_HELP" class="form-text text-muted">Must have permissions to create tables and views</small>
                        </div>
                        <div class="form-group">
                            <label for="DB_PASSWORD">Database Password</label>
                            <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="" class="form-control"
                                   maxlength="255"
                                   aria-describedby="DB_PASSWORD_HELP">
                            <div class="invalid-feedback"></div>
                            <small id="DB_PASSWORD_HELP" class="form-text text-muted"></small>
                        </div>
                        <div class="form-group">
                            <label for="DB_PASSWORD_CONFIRM">Confirm Database Password</label>
                            <input type="password" name="DB_PASSWORD_CONFIRM" id="DB_PASSWORD_CONFIRM" value=""
                                   class="form-control"
                                   data-match="#DB_PASSWORD"
                                   maxlength="255">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <button type="button" class="btn btn-secondary" id="database-prev-btn">Previous</button>
                        <button type="button" class="btn btn-success" id="submit-setup">Finish</button>
                    </div>
                </div>
            </div>
    </form>
    </div>
</div>

<!-- Setup Progress Modal -->
<div class="modal fade" id="setupModal" tabindex="-1" role="dialog" aria-labelledby="setupModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="setupModalLabel">Setting Up ChurchCRM</h5>
            </div>
            <div class="modal-body text-center">
                <div id="setup-progress">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Installing ChurchCRM, please wait...</p>
                </div>
                <div id="setup-success" style="display: none;">
                    <i class="fa fa-check-circle text-success" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 mb-4">Setup Complete!</h4>
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fa fa-user-shield"></i> Default Administrator Account</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="text-muted small mb-1">Username</label>
                                <input type="text" class="form-control form-control-lg text-center font-weight-bold" value="admin" readonly>
                            </div>
                            <div class="form-group mb-0">
                                <label class="text-muted small mb-1">Password</label>
                                <input type="text" class="form-control form-control-lg text-center font-weight-bold" value="changeme" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3">
                        <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Please change the admin password immediately after logging in.
                    </div>
                </div>
                <div id="setup-error" style="display: none;">
                    <i class="fa fa-times-circle text-danger" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">Setup Failed</h4>
                    <div class="alert alert-danger mt-3 text-left">
                        <p id="setup-error-message"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="setup-footer" style="display: none;">
                <button type="button" class="btn btn-primary" id="continue-to-login">Continue to Login</button>
            </div>
        </div>
    </div>
</div>

<!-- Force Install Confirmation Modal -->
<div class="modal fade" id="forceInstallModal" tabindex="-1" role="dialog" aria-labelledby="forceInstallModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="forceInstallModalLabel">
                    <i class="fa-solid fa-exclamation-triangle mr-2"></i>Force Installation Warning
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="font-weight-bold">You are about to proceed with installation despite unmet system requirements.</p>
                <p>This may result in:</p>
                <ul class="text-left">
                    <li>Missing or broken functionality</li>
                    <li>Data integrity issues</li>
                    <li>Security vulnerabilities</li>
                    <li>Unpredictable system behavior</li>
                </ul>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-exclamation-circle mr-2"></i>
                    <strong>We strongly recommend</strong> fixing the issues before proceeding.
                </div>
                <p class="mb-0">Do you understand the risks and wish to continue anyway?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-force-install">
                    <i class="fa-solid fa-exclamation-triangle mr-2"></i>Yes, Force Install
                </button>
            </div>
        </div>
    </div>
</div>
</div>

</body>
</html>
