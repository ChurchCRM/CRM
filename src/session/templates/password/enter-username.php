<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext("Password Reset");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="text-center my-4">
            <?php
            $headerHTML = '<b>Church</b>CRM';
            $sHeader = SystemConfig::getValue("sHeader");
            $sChurchName = SystemConfig::getValue("sChurchName");
            if (!empty($sHeader)) {
                $headerHTML = html_entity_decode($sHeader, ENT_QUOTES);
            } else if (!empty($sChurchName)) {
                $headerHTML = $sChurchName;
            }
            ?>
            <a href="<?= SystemURLs::getRootPath() ?>/"><?= $headerHTML ?></a>
                </div>

                <div class="card mt-4">
                    <div class="card-body">
                        <h4 class="card-title text-center"><?= gettext('Reset your password') ?></h4>
                        <p class="text-center text-muted mb-4"><?= gettext('Enter your login name and we will email you a link to reset your password.') ?></p>

                        <form id="resetPasswordForm">
                            <div class="form-group">
                                <label for="username" class="sr-only"><?= gettext('Login Name') ?></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fa fa-user" aria-hidden="true"></i></span>
                                    </div>
                                    <input id="username" name="username" type="text" class="form-control" placeholder="<?= gettext('Login Name') ?>" required autofocus aria-label="<?= gettext('Login Name') ?>">
                                </div>
                            </div>

                            <button type="button" id="resetPassword" class="btn btn-primary btn-block"><?= gettext('Send Reset Email'); ?></button>

                            <div class="text-center mt-3">
                                <a href="<?= SystemURLs::getRootPath() . '/session/begin' ?>"><?= gettext('Back to login') ?></a>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script nonce="<?= SystemURLs::getCSPNonce() ?>">
        $(document).ready(function() {
            var init = function() {
                var $btn = $("#resetPassword");
                var $user = $("#username");

                function reset() {
                    var user = $user.val().trim();
                    if (!user) {
                        window.CRM.notify(i18next.t('Login Name is Required'), { type: 'warning', delay: 3000 });
                        return;
                    }

                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + i18next.t('Sending...'));
                    $.ajax({
                        method: 'POST',
                        url: window.CRM.root + '/api/public/user/password-reset',
                        data: JSON.stringify({ userName: user }),
                        contentType: 'application/json; charset=utf-8',
                        dataType: 'json'
                    })
                        .done(function(data) {
                            window.CRM.notify(i18next.t('Check your email for a password reset link'), { type: 'success', delay: 3000 });
                            setTimeout(function() { window.location.href = window.CRM.root + '/'; }, 3100);
                        })
                        .fail(function(jqXHR) {
                            var msg = i18next.t('Sorry, we are unable to process your request at this point in time.');
                            if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                                msg = jqXHR.responseJSON.message;
                            }
                            window.CRM.notify(msg, { type: 'danger', delay: 5000 });
                            $btn.prop('disabled', false).text(i18next.t('Send Reset Email'));
                        });
                }

                $btn.on('click', reset);
                $user.on('keydown', function(e) {
                    if (e.which === 13 || e.key === 'Enter') {
                        e.preventDefault();
                        reset();
                    }
                });
            };

            if (window.CRM && typeof window.CRM.onLocalesReady === 'function') {
                window.CRM.onLocalesReady(init);
            } else {
                init();
            }
        });
    </script>
<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
