<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = gettext("User") . " - " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>Login Info</h4>
            </div>
            <div class="box-body">
                <b><?= gettext("Username") ?>:</b> <?= $user->getUserName() ?>
                <br/>
                <p/>
                <br/>
                <!--                <a id="sendNewPassword" class="btn btn-warning"><i class="fa fa-repeat"></i> Reset Password </a> -->
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>Api Key</h4>
            </div>
            <div class="box-body">
                <form>
                    <input id="apiKey" class="form-control" type="text" readonly value="<?= $user->getApiKey() ?>"/>
                </form>
                <br/>
                <p/>

<!--                <a id="copyApiKey" class="btn btn-default"><i class="fa fa-copy"></i> Copy API Key </a>
                &nbsp; -->
                <a id="regenApiKey" class="btn btn-warning"><i class="fa fa-repeat"></i> Regen API Key </a>
            </div>
        </div>
    </div>
</div>

<script >
    $("#regenApiKey").click(function () {
        $.ajax({
            type: 'POST',
            url: window.CRM.root + '/api/users/<?= $user->getId()?>/apikey/regen'
        })
            .done(function (data, textStatus, xhr) {
                if (xhr.status == 200) {
                    $("#apiKey").val (data.apiKey);
                } else {
                    showGlobalMessage(i18next.t("Failed generate a new API Key"), "danger")
                }
            });
    });

</script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
