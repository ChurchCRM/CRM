<?php

use ChurchCRM\dto\SystemURLs;

$URL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';

$sPageTitle = 'ChurchCRM – Setup';
require_once '../Include/HeaderNotLoggedIn.php';
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = {
        root: "<?= SystemURLs::getRootPath() ?>",
        prerequisites : [],
        prerequisitesStatus : false //TODO this is not correct we need 2 flags
    };
</script>
<style nonce="<?= SystemURLs::getCSPNonce() ?>">
    .wizard .content > .body {
        width: 100%;
        height: auto;
        padding: 15px;
        position: relative;
    }
    /* Horizontal stepper at top */
    #setup-stepper.bs-stepper {
        box-shadow: none;
    }
    #setup-stepper .bs-stepper-header {
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 0.25rem;
        margin-bottom: 1.5rem;
    }
    .jumbotron {
        padding: 2rem 1rem;
        margin-bottom: 1.5rem;
    }
</style>
<div class="jumbotron text-center">
    <h1 class="display-4">Welcome to ChurchCRM Setup Wizard</h1>
    <p class="lead">Let's get your church management system configured and ready to use.</p>
</div>
<div id="setup-stepper" class="bs-stepper">
    <div class="bs-stepper-header" role="tablist">
        <div class="step" data-target="#step-prerequisites">
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
                <span class="bs-stepper-label">MySQL Database Setup</span>
            </button>
        </div>
    </div>
    <div class="bs-stepper-content">
        <form id="setup-form" novalidate>
            <div id="step-prerequisites" class="content" role="tabpanel" aria-labelledby="step-prerequisites-trigger">
                <div class="alert alert-warning alert-dismissible fade show" role="alert" id="prerequisites-war">
                    <strong><i class="fa-solid fa-exclamation-triangle"></i> Prerequisites Not Met</strong>
                    <p class="mb-0 mt-1">Some server requirements are not satisfied. ChurchCRM may not function correctly.</p>
                </div>
                
                <!-- PHP Extensions Group -->
                <div class="card mb-2">
                    <div class="card-header p-0" id="php-extensions-header">
                        <button class="btn btn-link btn-block text-left d-flex justify-content-between align-items-center p-2 text-decoration-none" 
                                type="button" data-toggle="collapse" data-target="#php-extensions-body" 
                                aria-expanded="true" aria-controls="php-extensions-body">
                            <span class="font-weight-bold">
                                <i class="fa-solid fa-php mr-2"></i>PHP Extensions
                            </span>
                            <span id="php-extensions-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </span>
                        </button>
                    </div>
                    <div id="php-extensions-body" class="collapse show" aria-labelledby="php-extensions-header">
                        <div class="card-body p-0">
                            <table class="table table-sm table-condensed mb-0" id="php-extensions"></table>
                        </div>
                    </div>
                </div>

                <!-- File Integrity Group -->
                <div class="card mb-3">
                    <div class="card-header p-0" id="integrity-header">
                        <button class="btn btn-link btn-block text-left d-flex justify-content-between align-items-center p-2 text-decoration-none" 
                                type="button" data-toggle="collapse" data-target="#integrity-body" 
                                aria-expanded="true" aria-controls="integrity-body">
                            <span class="font-weight-bold">
                                <i class="fa-solid fa-shield-alt mr-2"></i>File Integrity
                            </span>
                            <span id="integrity-status">
                                <i class="fa-solid fa-spinner fa-spin text-muted"></i>
                            </span>
                        </button>
                    </div>
                    <div id="integrity-body" class="collapse show" aria-labelledby="integrity-header">
                        <div class="card-body p-0">
                            <table class="table table-sm table-condensed mb-0" id="integrity-checks"></table>
                        </div>
                    </div>
                </div>

                <!-- Server Information Group (Collapsed by default) -->
                <div class="card mb-3">
                    <div class="card-header p-0" id="serverinfo-header">
                        <button class="btn btn-link btn-block text-left d-flex justify-content-between align-items-center p-2 text-decoration-none collapsed" 
                                type="button" data-toggle="collapse" data-target="#serverinfo-body" 
                                aria-expanded="false" aria-controls="serverinfo-body">
                            <span class="font-weight-bold">
                                <i class="fa-solid fa-server mr-2"></i>Server Information
                            </span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                    </div>
                    <div id="serverinfo-body" class="collapse" aria-labelledby="serverinfo-header">
                        <div class="card-body p-2">
                            <table class="table table-sm table-condensed mb-0">
                                <tr>
                                    <td>Max file upload size</td>
                                    <td><?php echo ini_get('upload_max_filesize') ?></td>
                                </tr>
                                <tr>
                                    <td>Max POST size</td>
                                    <td><?php echo ini_get('post_max_size') ?></td>
                                </tr>
                                <tr>
                                    <td>PHP Memory Limit</td>
                                    <td><?php echo ini_get('memory_limit') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="button" class="btn btn-primary" id="prerequisites-next-btn" disabled>Next</button>
                    <button type="button" class="btn btn-warning ml-2" id="prerequisites-force-btn" style="display: none;">
                        <i class="fa-solid fa-exclamation-triangle"></i> Force Install
                    </button>
                </div>
            </div>
            <div id="step-location" class="content" role="tabpanel" aria-labelledby="step-location-trigger">
            <div class="form-group">
                <label for="ROOT_PATH">Root Path</label>
                <input type="text" name="ROOT_PATH" id="ROOT_PATH"
                       value="<?= SystemURLs::getRootPath() ?>" class="form-control"
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
                <input type="text" name="URL" id="URL" value="<?= $URL ?>" class="form-control"
                       aria-describedby="URL_HELP"
                       required>
                <div class="invalid-feedback"></div>
                <small id="URL_HELP" class="form-text text-muted">
                    <strong>Example:</strong> <code>https://www.yourdomain.com/churchcrm/</code><br>
                    <strong>Rules:</strong> Must be a valid URL, including <code>http://</code> or <code>https://</code>. If using a non-standard port, include it (e.g., <code>https://www.yourdomain.com:8080/churchcrm/</code>). Case sensitive.
                </small>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" id="location-prev-btn">Previous</button>
                <button type="button" class="btn btn-primary" id="location-next-btn">Next</button>
            </div>
        </div>
        <div id="step-database" class="content" role="tabpanel" aria-labelledby="step-database-trigger">
                        <div class="form-group">
                <label for="DB_SERVER_NAME">Server Name</label>
                <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="<?= $DB_SERVER_NAME ?>"
                       class="form-control" maxlength="64" required>
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label for="DB_SERVER_PORT">Server Port</label>
                <input type="text" name="DB_SERVER_PORT" id="DB_SERVER_PORT" value="<?= $DB_SERVER_PORT ?>"
                       class="form-control" maxlength="16"
                       pattern="[0-9]+"
                       required>
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label for="DB_NAME">Database Name</label>
                <input type="text" name="DB_NAME" id="DB_NAME" value="<?= $DB_NAME ?>"
                       class="form-control" maxlength="64" required>
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label for="DB_USER">Database User</label>
                <input type="text" name="DB_USER" id="DB_USER" value="<?= $DB_USER ?>"
                       class="form-control" maxlength="64" required>
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD">Database Password</label>
                <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="" class="form-control"
                       maxlength="255">
                <div class="invalid-feedback"></div>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD_CONFIRM">Confirm Database Password</label>
                <input type="password" name="DB_PASSWORD_CONFIRM" id="DB_PASSWORD_CONFIRM" value=""
                       class="form-control"
                       data-match="#DB_PASSWORD"
                       maxlength="255">
                <div class="invalid-feedback"></div>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" id="database-prev-btn">Previous</button>
                <button type="button" class="btn btn-success" id="submit-setup">Finish</button>
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
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fa fa-user-shield"></i> Default Administrator Account</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="text-muted small mb-1">Username</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-lg text-center font-weight-bold" value="admin" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <label class="text-muted small mb-1">Password</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control form-control-lg text-center font-weight-bold" value="changeme" readonly>
                                    </div>
                                </div>
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

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bs-stepper/bs-stepper.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/setup.js"></script>
<?php
require_once '../Include/FooterNotLoggedIn.php';
