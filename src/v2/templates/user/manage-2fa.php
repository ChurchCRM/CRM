<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

//Set the page title
$sPageTitle = $user->getFullName() . gettext("2 Factor Authentication enrollment");
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>
<div id="two-factor-enrollment-react-app"> </div>

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
<script src="<?= SystemURLs::getRootPath() ?>/skin/js-react/two-factor-enrollment-app.js"></script>
<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
