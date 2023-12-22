<?php

use ChurchCRM\dto\SystemURLs;

$URL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';

$sPageTitle = 'ChurchCRM – Setup';
require '../Include/HeaderNotLoggedIn.php';
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = {
        root: "<?= SystemURLs::getRootPath() ?>",
        prerequisites : [],
        prerequisitesStatus : false //TODO this is not correct we need 2 flags
    };
</script>
<style>
    .wizard .content > .body {
        width: 100%;
        height: auto;
        padding: 15px;
        position: relative;
    }

</style>
<h1 class="text-center">Welcome to ChurchCRM setup wizard</h1>
<p/><br/>
<form id="setup-form">
    <div id="wizard">
        <h2>System Prerequisite</h2>
        <section>
            <table class="table table-condensed" id="prerequisites"></table>
            <p/>
            <div class="callout callout-warning" id="prerequisites-war">
                This server isn't quite ready for ChurchCRM. If you know what you are doing.
                <a href="#" onclick="skipCheck()"><b>Click here</b></a>.
            </div>
        </section>

        <h2>Useful Server Info</h2>
        <section>
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
        </section>

        <h2>Install Location</h2>
        <section>
            <div class="form-group">
                <label for="ROOT_PATH">Root Path</label>
                <input type="text" name="ROOT_PATH" id="ROOT_PATH"
                       value="<?= SystemURLs::getRootPath() ?>" class="form-control"
                       aria-describedby="ROOT_PATH_HELP">
                <small id="ROOT_PATH_HELP" class="form-text text-muted">
                    Root path of your ChurchCRM installation ( THIS MUST BE SET CORRECTLY! )
                    <p/>
                    <i><b>Examples:</b></i>
                    <p/>
                    If you will be accessing from <b>http://www.yourdomain.com/churchcrm</b> then you would
                    enter <b>'/churchcrm'</b> here.
                    <br/>
                    If you will be accessing from <b>http://www.yourdomain.com</b> then you leave
                    this field blank.

                    <p/>
                    <i><b>NOTE:</b></i>
                    <p/>
                    SHOULD Start with slash.<br/>
                    SHOULD NOT end with slash.<br/>
                    It is case sensitive.
                    </ul>
                </small>
            </div>
            <div class="form-group">
                <label for="URL">Base URL</label>
                <input type="text" name="URL" id="URL" value="<?= $URL ?>" class="form-control"
                       aria-describedby="URL_HELP" required>
                <small id="URL_HELP" class="form-text text-muted">
                    This is the URL that you prefer most users use when they log in. These are case sensitive.
                </small>
            </div>
        </section>
        <h2>MySQL Database Setup</h2>
        <section>
            <div class="form-group">
                <label for="DB_SERVER_NAME">MySQL Database Server Name</label>
                <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" class="form-control"
                       aria-describedby="DB_SERVER_NAME_HELP" required>
                <small id="DB_SERVER_NAME_HELP" class="form-text text-muted">Use localhost over 127.0.0.1</small>
            </div>
            <div class="form-group">
                <label for="DB_SERVER_PORT">MySQL Database Server Port</label>
                <input type="text" name="DB_SERVER_PORT" id="DB_SERVER_PORT" class="form-control"
                       aria-describedby="DB_SERVER_PORT_HELP" required value="3306">
                <small id="DB_SERVER_PORT_HELP" class="form-text text-muted">Default MySQL Port is 3306</small>
            </div>
            <div class="form-group">
                <label for="DB_NAME">Database Name</label>
                <input type="text" name="DB_NAME" id="DB_NAME" placeholder="churchcrm" class="form-control"
                       aria-describedby="DB_NAME_HELP" required>
                <small id="DB_NAME_HELP" class="form-text text-muted"></small>
            </div>
            <div class="form-group">
                <label for="DB_USER">Database User</label>
                <input type="text" name="DB_USER" id="DB_USER" placeholder="churchcrm" class="form-control"
                       aria-describedby="DB_USER_HELP" required>
                <small id="DB_USER_HELP" class="form-text text-muted">Must have permissions to create tables and views</small>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD">Database Password</label>
                <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" class="form-control"
                       aria-describedby="DB_PASSWORD_HELP" required>
                <small id="DB_PASSWORD_HELP" class="form-text text-muted"></small>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD2">Confirm Database Password</label>
                <input type="password" name="DB_PASSWORD2" id="DB_PASSWORD2" class="form-control"
                       aria-describedby="DB_PASSWORD2_HELP" required>
                <small id="DB_PASSWORD2_HELP" class="form-text text-muted"></small>
            </div>
        </section>
        <!--
        <h2>Church Info</h2>
        <section>
            <div class="form-group">
                <label for="sChurchName">Church Name</label>
                <input type="text" name="sChurchName" id="sChurchName" class="form-control"
                       aria-describedby="sChurchNameHelp" required>
                <small id="sChurchNameHelp" class="form-text text-muted"></small>
            </div>
            <div class="form-group">
                <label for="sChurchAddress">Church Address</label>
                <input type="text" name="sChurchAddress" id="sChurchAddress" class="form-control"
                       aria-describedby="sChurchAddressHelp" required>
                <small id="sChurchAddressHelp" class="form-text text-muted"></small>
            </div>

            <div class="form-group">
                <label for="sChurchCity">Church City</label>
                <input type="text" name="sChurchCity" id="sChurchCity" class="form-control"
                       aria-describedby="sChurchCityHelp" required>
                <small id="sChurchCityHelp" class="form-text text-muted"></small>
            </div>

            <div class="form-group">
                <label for="sChurchState">Church State</label>
                <input type="text" name="sChurchState" id="sChurchState" class="form-control"
                       aria-describedby="sChurchStateHelp" required>
                <small id="sChurchStateHelp" class="form-text text-muted"></small>
            </div>

            <div class="form-group">
                <label for="sChurchZip">Church Zip</label>
                <input type="text" name="sChurchZip" id="sChurchZip" class="form-control"
                       aria-describedby="sChurchZipHelp" required>
                <small id="sChurchZipHelp" class="form-text text-muted"></small>
            </div>

            <div class="form-group">
                <label for="sChurchCountry">Church Country</label>
                <input type="text" name="sChurchCountry" id="sChurchCountry" class="form-control"
                       aria-describedby="sChurchCountryHelp" required>
                <small id="sChurchCountryHelp" class="form-text text-muted"></small>
            </div>

            <div class="form-group">
                <label for="sChurchPhone">Church Phone</label>
                <input type="text" name="sChurchPhone" id="sChurchPhone" class="form-control"
                       aria-describedby="sChurchPhoneHelp">
                <small id="sChurchPhoneHelp" class="form-text text-muted"></small>
            </div>

            <div class="form-group">
                <label for="sChurchEmail">Church email</label>
                <input type="email" name="sChurchEmail" id="sChurchEmail" class="form-control"
                       aria-describedby="sChurchEmailHelp" required>
                <small id="sChurchEmailHelp" class="form-text text-muted"></small>
            </div>

            <div class="callout callout-info" id="prerequisites-war">
                This information can be updated late on via <b><i>System Settings</i></b>.
            </div>
        </section>

        <h2>Mail Server</h2>
        <section>
            <div class="form-group">
                <label for="sSMTPHost">SMTP Host</label>
                <input type="text" name="sSMTPHost" id="sSMTPHost" class="form-control"
                       aria-describedby="sSMTPHostHelp" required>
                <small id="sSMTPHostHelp" class="form-text text-muted">
                    Either a single hostname, you can also specify a different port by using this format: [hostname:port]
                </small>
            </div>
            <div class="form-group">
                <label for="iSMTPTimeout">SMTP Host Timeout</label>
                <input type="number" name="iSMTPTimeout" id="iSMTPTimeout" class="form-control"
                       aria-describedby="iSMTPTimeoutHelp" value="30" required>
                <small id="iSMTPTimeoutHelp" class="form-text text-muted">
                    The SMTP server timeout in seconds.
                </small>
            </div>
            <div class="form-group">
                <label for="sSMTPUser">SMTP Host User</label>
                <input type="text" name="sSMTPUser" id="sSMTPUser" class="form-control"
                       aria-describedby="sSMTPUserHelp" required>
                <small id="sSMTPUserHelp" class="form-text text-muted">
                    SMTP username.
                </small>
            </div>
            <div class="form-group">
                <label for="sSMTPPass">SMTP Host Password</label>
                <input type="password" name="sSMTPPass" id="sSMTPPass" class="form-control"
                       aria-describedby="sSMTPPassHelp" required>
                <small id="sSMTPPassHelp" class="form-text text-muted">
                    SMTP password.
                </small>
            </div>
        </section>-->
    </div>
</form>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery.steps/jquery.steps.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery-validation/jquery.validate.min.js"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/js/setup.js"></script>

<?php
require '../Include/FooterNotLoggedIn.php';
?>
