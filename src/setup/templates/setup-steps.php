<?php

use ChurchCRM\dto\SystemURLs;

$URL = 'http'.(isset($_SERVER['HTTPS']) ? 's' : '').'://'.$_SERVER['HTTP_HOST'].'/';

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
    window.CRM.checkIntegrity = function () {
        window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","pending");
        $.ajax({
            url: "<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>/Setup/SystemIntegrityCheck",
            method: "GET"
        }).done(function(data){
            if (data == "success" )
            {
                window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","pass");
                $("#prerequisites-war").hide();
            }
            else
            {
                window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","fail");
            }

        }).fail(function(){
            window.CRM.renderPrerequisite("ChurchCRM File Integrity Check","fail");
        });
    };

    window.CRM.checkPrerequisites = function () {
        $.ajax({
            url: "<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>/setup/SystemPrerequisiteCheck",
            method: "GET",
            contentType: "application/json"
        }).done(function(data){
            $.each(data, function (key,value) {
                if (value)
                {
                    status="pass";
                }
                else
                {
                    status="fail";
                }
                window.CRM.renderPrerequisite(key,status);
            });
        });
    };
    window.CRM.renderPrerequisite = function (name, status) {
        var td = {};
        if (status == "pass")
        {
            td = {
                class: 'text-blue',
                html: '&check;'
            };
        }
        else if(status =="pending")
        {
            td = {
                class: 'text-orange',
                html: '<i class="fa fa-spinner fa-spin"></i>'
            };
        }
        else if (status == "fail")
        {
            td = {
                class: 'text-red',
                html: '&#x2717;'
            };
        }
        var id = name.replace(/[^A-z0-9]/g,'');
        window.CRM.prerequisites[id] = status;
        var domElement = "#"+id;
        var prerequisite = $("<tr>",{ id: id }).append(
            $("<td>",{text:name})).append(
            $("<td>",td));

        if ($(domElement).length != 0 )
        {
            $(domElement).replaceWith(prerequisite);
        }
        else
        {
            $("#prerequisites").append(prerequisite);
        }

    };

</script>
<h1 class="text-center"><?= gettext('Welcome to ChurchCRM setup wizard')?></h1>
<p/><br/>
<div id="wizard">
    <h2>System Prerequisite</h2>
    <section>
        <table class="table table-condensed" id="prerequisites"></table>

        <p/>

        <div class="callout callout-warning" id="prerequisites-war">This server isn't quite ready for ChurchCRM. If you know what you are doing.  Click <b>Next</b>.</button>
        </div>


    </section>

    <h2>Useful Server Info</h2>
    <section>
        <p> <table class="table">
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
        </table></p>
    </section>

    <h2>Install Location</h2>
    <section>
        <p><form target="_self" method="post">
            <div class="form-group">
                <div class="row">
                    <div class="col-md-4">
                        <label for="DB_SERVER_NAME"><?= gettext('DB Server Name') ?>:</label>
                        <input type="text" name="DB_SERVER_NAME" id="DB_SERVER_NAME" value="localhost" class="form-control"
                               required>
                    </div>
                    <div class="col-md-4">
                        <label for="DB_NAME"><?= gettext('DB Name') ?>:</label>
                        <input type="text" name="DB_NAME" id="DB_NAME" value="churchcrm" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="DB_USER"><?= gettext('DB User') ?>:</label>
                        <input type="text" name="DB_USER" id="DB_USER" value="churchcrm" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="DB_PASSWORD"><?= gettext('DB Password') ?>:</label>
                        <input type="password" name="DB_PASSWORD" id="DB_PASSWORD" value="churchcrm" class="form-control"
                               required>
                    </div>

                    <div class="col-md-4">
                        <label for="ROOT_PATH"><?= gettext('Root Path') ?>:</label>
                        <input type="text" name="ROOT_PATH" id="ROOT_PATH" value="<?= \ChurchCRM\dto\SystemURLs::getRootPath() ?>" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label for="URL"><?= gettext('Base URL') ?>:</label>
                        <input type="text" name="URL" id="URL" value="<?= $URL ?>" class="form-control" required>
                    </div>
                </div>
            </div>
            <input type="submit" class="btn btn-primary" value="<?= gettext('Setup') ?>" name="Setup">
        </form>
        </p>
    </section>

    <h2>Database Setup</h2>
    <section>
        <p>Quisque at sem turpis, id sagittis diam. Suspendisse malesuada eros posuere mauris vehicula vulputate. Aliquam sed sem tortor.
            Quisque sed felis ut mauris feugiat iaculis nec ac lectus. Sed consequat vestibulum purus, imperdiet varius est pellentesque vitae.
            Suspendisse consequat cursus eros, vitae tempus enim euismod non. Nullam ut commodo tortor.</p>
    </section>

    <h2>Church Info</h2>
    <section>
        <p>Quisque at sem turpis, id sagittis diam. Suspendisse malesuada eros posuere mauris vehicula vulputate. Aliquam sed sem tortor.
            Quisque sed felis ut mauris feugiat iaculis nec ac lectus. Sed consequat vestibulum purus, imperdiet varius est pellentesque vitae.
            Suspendisse consequat cursus eros, vitae tempus enim euismod non. Nullam ut commodo tortor.</p>
    </section>

    <h2>Mail Server</h2>
    <section>
        <p>Quisque at sem turpis, id sagittis diam. Suspendisse malesuada eros posuere mauris vehicula vulputate. Aliquam sed sem tortor.
            Quisque sed felis ut mauris feugiat iaculis nec ac lectus. Sed consequat vestibulum purus, imperdiet varius est pellentesque vitae.
            Suspendisse consequat cursus eros, vitae tempus enim euismod non. Nullam ut commodo tortor.</p>
    </section>

    <h2>Configuration</h2>
    <section>
        <p>Quisque at sem turpis, id sagittis diam. Suspendisse malesuada eros posuere mauris vehicula vulputate. Aliquam sed sem tortor.
            Quisque sed felis ut mauris feugiat iaculis nec ac lectus. Sed consequat vestibulum purus, imperdiet varius est pellentesque vitae.
            Suspendisse consequat cursus eros, vitae tempus enim euismod non. Nullam ut commodo tortor.</p>
    </section>

    <h2>Integration</h2>
    <section>
        <p>Quisque at sem turpis, id sagittis diam. Suspendisse malesuada eros posuere mauris vehicula vulputate. Aliquam sed sem tortor.
            Quisque sed felis ut mauris feugiat iaculis nec ac lectus. Sed consequat vestibulum purus, imperdiet varius est pellentesque vitae.
            Suspendisse consequat cursus eros, vitae tempus enim euismod non. Nullam ut commodo tortor.</p>
    </section>
</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/external/jquery.steps/jquery.steps.min.js" ></script>
<script>
    $("document").ready(function(){
        $("#wizard").steps({
            headerTag: "h2",
            bodyTag: "section",
            transitionEffect: "slideLeft",
            stepsOrientation: "vertical"
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
