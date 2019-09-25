<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = $user->getFullName() . gettext("2 Factor Authentication enrollment");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div class="row">
    <div class="col-lg-3">
        <div class="box">
            <div class="box-header">
                <h4>2FA enrollment</h4>
            </div>
            <div class="box-body">
                <b><?= gettext("Username") ?>:</b> <?= $user->getUserName() ?>
                <br/>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="box">
            <div class="box-header">
                <h4>2 Factor Authentication Secret</h4>
            </div>
            <div class="box-body">
                </b><img id="2fakey" src="<?= $user->getTwoFactorAuthQRCodeDataUri() ?>"/>
                <br/>
                <a id="regen2faKey" class="btn btn-warning"><i class="fa fa-repeat"></i><?= gettext("Regenerate 2 Factor Authentication Secret") ?></a>
                <a id="remove2faKey" class="btn btn-warning"><i class="fa fa-repeat"></i><?= gettext("Remove 2 Factor Authentication Secret") ?></a>

            </div>
        </div>
    </div>
</div>

<script >
    $("#regen2faKey").click(function () {
        $.ajax({
            type: 'POST',
            url: window.CRM.root + '/api/user/current/refresh2fasecret'
        })
            .done(function (data, textStatus, xhr) {
                if (xhr.status == 200) {
                    $("#2fakey").attr("src",data.TwoFAQRCodeDataUri);
                } else {
                    showGlobalMessage(i18next.t("Failed generate a new API Key"), "danger")
                }
            });
    });
    $("#remove2faKey").click(function () {
        $.ajax({
            type: 'POST',
            url: window.CRM.root + '/api/user/current/remove2fasecret'
        })
            .done(function (data, textStatus, xhr) {
                if (xhr.status == 200) {
                    $("#2fakey").attr("src",data.TwoFAQRCodeDataUri);
                } else {
                    showGlobalMessage(i18next.t("Failed generate a new API Key"), "danger")
                }
            });
    });

</script>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
