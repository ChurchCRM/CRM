<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset");
$sBodyClass = 'page-auth page-login';
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>


<div class="forgot-password-container">
  <div class="forgot-password-card">
    <!-- Header with Logo and Church Name -->
    <div class="forgot-password-card-logo">
      <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
    </div>
    <h2><?= ChurchMetaData::getChurchName() ?></h2>
    <p class="login-header-tagline"><?= gettext('Account Recovery') ?></p>

    <!-- Form Title -->
    <h3><i class="fa-solid fa-key me-2"></i><?= gettext('Reset your password') ?></h3>
    <p><?= gettext('Enter your login name and we will email you a link to reset your password.') ?></p>

    <form id="resetPasswordForm">
      <div class="mb-3">
        <label for="username"><?= gettext('Login Name') ?></label>
        <input 
          id="username" 
          name="username" 
          type="text" 
          placeholder="<?= gettext('Enter your login name') ?>" 
          required 
          autofocus 
          aria-label="<?= gettext('Login Name') ?>">
      </div>

      <button type="button" id="resetPassword" class="btn-reset"><?= gettext('Send Reset Email') ?></button>

      <div class="form-footer">
        <a href="<?= SystemURLs::getRootPath() . '/session/begin' ?>"><?= gettext('Back to login') ?></a>
      </div>
    </form>
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

                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>' + i18next.t('Sending...'));
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
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
