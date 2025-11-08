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
</style>
<h1 class="text-center">Welcome to ChurchCRM setup wizard</h1>
<p/><br/>
<div id="setup-stepper" class="bs-stepper vertical">
    <div class="bs-stepper-header" role="tablist">
        <div class="step" data-target="#step-prerequisites">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-prerequisites" id="step-prerequisites-trigger">
                <span class="bs-stepper-circle">1</span>
                <span class="bs-stepper-label">System Prerequisites</span>
            </button>
        </div>
        <div class="line"></div>
        <div class="step" data-target="#step-serverinfo">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-serverinfo" id="step-serverinfo-trigger">
                <span class="bs-stepper-circle">2</span>
                <span class="bs-stepper-label">Useful Server Info</span>
            </button>
        </div>
        <div class="line"></div>
        <div class="step" data-target="#step-location">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-location" id="step-location-trigger">
                <span class="bs-stepper-circle">3</span>
                <span class="bs-stepper-label">Install Location</span>
            </button>
        </div>
        <div class="line"></div>
        <div class="step" data-target="#step-database">
            <button type="button" class="step-trigger" role="tab" aria-controls="step-database" id="step-database-trigger">
                <span class="bs-stepper-circle">4</span>
                <span class="bs-stepper-label">MySQL Database Setup</span>
            </button>
        </div>
    </div>
    <div class="bs-stepper-content">
        <form id="setup-form" novalidate>
            <div id="step-prerequisites" class="content" role="tabpanel" aria-labelledby="step-prerequisites-trigger">
                <table class="table table-condensed" id="prerequisites"></table>
                <p/>
                <div class="callout callout-warning" id="prerequisites-war">
                    This server isn't quite ready for ChurchCRM. If you know what you are doing.
                    <a href="#" onclick="skipCheck()"><b>Click here</b></a>.
                </div>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="setupStepper.next()">Next</button>
                </div>
            </div>
            <div id="step-serverinfo" class="content" role="tabpanel" aria-labelledby="step-serverinfo-trigger">
                <table class="table">
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
                <div class="mt-3">
                    <button type="button" class="btn btn-secondary" onclick="setupStepper.previous()">Previous</button>
                    <button type="button" class="btn btn-primary" onclick="setupStepper.next()">Next</button>
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
                <div class="help-block with-errors"></div>
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
                <div class="help-block with-errors"></div>
                <small id="URL_HELP" class="form-text text-muted">
                    <strong>Example:</strong> <code>https://www.yourdomain.com/churchcrm/</code><br>
                    <strong>Rules:</strong> Must be a valid URL, including <code>http://</code> or <code>https://</code>. If using a non-standard port, include it (e.g., <code>https://www.yourdomain.com:8080/churchcrm/</code>). Case sensitive.
                </small>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" onclick="setupStepper.previous()">Previous</button>
                <button type="button" class="btn btn-primary" onclick="setupStepper.next()">Next</button>
            </div>
        </div>
        <div id="step-database" class="content" role="tabpanel" aria-labelledby="step-database-trigger">
                        <div class="form-group">
                <label for="DB_SERVER_NAME">Server Name</label>
                <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="<?= $DB_SERVER_NAME ?>"
                       class="form-control" maxlength="64" required>
                <div class="help-block with-errors"></div>
            </div>
            <div class="form-group">
                <label for="DB_SERVER_PORT">Server Port</label>
                <input type="text" name="DB_SERVER_PORT" id="DB_SERVER_PORT" value="<?= $DB_SERVER_PORT ?>"
                       class="form-control" maxlength="16"
                       pattern="[0-9]+"
                       required>
                <div class="help-block with-errors"></div>
            </div>
            <div class="form-group">
                <label for="DB_NAME">Database Name</label>
                <input type="text" name="DB_NAME" id="DB_NAME" value="<?= $DB_NAME ?>"
                       class="form-control" maxlength="64" required>
                <div class="help-block with-errors"></div>
            </div>
            <div class="form-group">
                <label for="DB_USER">Database User</label>
                <input type="text" name="DB_USER" id="DB_USER" value="<?= $DB_USER ?>"
                       class="form-control" maxlength="64" required>
                <div class="help-block with-errors"></div>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD">Database Password</label>
                <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="" class="form-control"
                       maxlength="255">
                <div class="help-block with-errors"></div>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD_CONFIRM">Confirm Database Password</label>
                <input type="password" name="DB_PASSWORD_CONFIRM" id="DB_PASSWORD_CONFIRM" value=""
                       class="form-control"
                       data-match="#DB_PASSWORD"
                       maxlength="255">
                <div class="help-block with-errors"></div>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" onclick="setupStepper.previous()">Previous</button>
                <button type="button" class="btn btn-success" id="submit-setup">Finish</button>
            </div>
        </div>
    </form>
    </div>
</div>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/bs-stepper/bs-stepper.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/setup.js"></script>
<?php
require_once '../Include/FooterNotLoggedIn.php';
