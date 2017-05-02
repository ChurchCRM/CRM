<?php

use ChurchCRM\dto\SystemURLs;

$URL = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');

if (!function_exists('bindtextdomain')) {
    function gettext($string)
    {
        return $string + 4;
    }
}

$sPageTitle = gettext('ChurchCRM – Setup');
require '../Include/HeaderNotLoggedIn.php';
?>
<script>
    window.CRM = {};
    window.CRM.prerequisites = [];
    window.CRM.prerequisitesStatus = false; //TODO this is not correct we need 2 flags 

    function skipCheck() {
        $("#prerequisites-war").hide();
        window.CRM.prerequisitesStatus = true;
    }

    window.CRM.checkIntegrity = function () {
        window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "pending");
        $.ajax({
            url: "<?= SystemURLs::getRootPath() ?>/setup/SystemIntegrityCheck",
            method: "GET"
        }).done(function (data) {
            if (data == "success") {
                window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "pass");
                $("#prerequisites-war").hide();
                window.CRM.prerequisitesStatus = true;
            }
            else {
                window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "fail");
            }

        }).fail(function () {
            window.CRM.renderPrerequisite("ChurchCRM File Integrity Check", "fail");
        });
    };

    window.CRM.checkPrerequisites = function () {
        $.ajax({
            url: "<?= SystemURLs::getRootPath() ?>/setup/SystemPrerequisiteCheck",
            method: "GET",
            contentType: "application/json"
        }).done(function (data) {
            $.each(data, function (key, value) {
                if (value) {
                    status = "pass";
                }
                else {
                    status = "fail";
                }
                window.CRM.renderPrerequisite(key, status);
            });
        });
    };
    window.CRM.renderPrerequisite = function (name, status) {
        var td = {};
        if (status == "pass") {
            td = {
                class: 'text-blue',
                html: '&check;'
            };
        }
        else if (status == "pending") {
            td = {
                class: 'text-orange',
                html: '<i class="fa fa-spinner fa-spin"></i>'
            };
        }
        else if (status == "fail") {
            td = {
                class: 'text-red',
                html: '&#x2717;'
            };
        }
        var id = name.replace(/[^A-z0-9]/g, '');
        window.CRM.prerequisites[id] = status;
        var domElement = "#" + id;
        var prerequisite = $("<tr>", {id: id}).append(
            $("<td>", {text: name})).append(
            $("<td>", td));

        if ($(domElement).length != 0) {
            $(domElement).replaceWith(prerequisite);
        }
        else {
            $("#prerequisites").append(prerequisite);
        }

    };

</script>
<h1 class="text-center"><?= gettext('Welcome to ChurchCRM setup wizard') ?></h1>
<p/><br/>
<form>
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
                       value="<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>" class="form-control"
                       aria-describedby="ROOT_PATH_HELP" required>
                <small id="ROOT_PATH_HELP" class="form-text text-muted">
                    Root path of your ChurchCRM installation ( THIS MUST BE SET CORRECTLY! )
                    <p/>
                    <i><b>Examples:</b></i>
                    <p/>
                    If you will be accessing from <b>http://www.yourdomain.com/churchcrm</b> then you would
                    enter <b>'/churchcrm'</b> here.
                    <br/>
                    If you will be accessing from <b>http://www.yourdomain.com</b> then you would enter
                    <b>''</b> ... an empty string for a top level installation.

                    <p/>
                    <i><b>NOTE:</b></i>
                    <p/>
                    SHOULD Start end with slash.<br/>
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
        <h2>Database Setup</h2>
        <section>
            <div class="form-group">
                <label for="DB_SERVER_NAME">Database Server Name</label>
                <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" class="form-control"
                       aria-describedby="DB_SERVER_NAME_HELP" required>
                <small id="DB_SERVER_NAME_HELP" class="form-text text-muted"></small>
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
                <small id="DB_USER_HELP" class="form-text text-muted">Must have permissions to create tables</small>
            </div>
            <div class="form-group">
                <label for="DB_PASSWORD">Database Password</label>
                <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" class="form-control"
                       aria-describedby="DB_PASSWORD_HELP" required>
                <small id="DB_PASSWORD_HELP" class="form-text text-muted"></small>
            </div>
        </section>

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
            <div class="callout callout-info" id="prerequisites-war">
                This information can be updated late on via <b><i>System Settings</i></b>.
            </div>
        </section>

        <h2>Configuration</h2>
        <section>
            <div class="callout callout-info" id="prerequisites-war">
                This information can be updated late on via <b><i>System Settings</i></b>.
            </div>
        </section>

        <h2>Integration</h2>
        <section>
            <div class="callout callout-info" id="prerequisites-war">
                This information can be updated late on via <b><i>System Settings</i></b>.
            </div>
        </section>
    </div>
</form>
<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery.steps/jquery.steps.min.js"></script>
<script>
    $("document").ready(function () {
        $("#wizard").steps({
            headerTag: "h2",
            bodyTag: "section",
            transitionEffect: "slideLeft",
            stepsOrientation: "vertical",
            onStepChanging: function (event, currentIndex, newIndex) {
                if (currentIndex == 0) {
                    return window.CRM.prerequisitesStatus;
                }
                return true;
            }
        });

        window.CRM.checkIntegrity();
        window.CRM.checkPrerequisites();
    });
</script>
<style>
    .wizard .content > .body {
        width: 100%;
        height: auto;
        padding: 15px;
        position: relative;
    }
</style>

<?php
require '../Include/FooterNotLoggedIn.php';
?>
