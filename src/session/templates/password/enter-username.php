<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset");
require(SystemURLs::getDocumentRoot() ."/Include/HeaderNotLoggedIn.php");
?>

<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .forgot-password-container {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 20px;
  }

  .forgot-password-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    padding: 60px 50px;
    max-width: 440px;
    width: 100%;
    text-align: center;
  }

  .forgot-password-card-logo {
    margin-bottom: 30px;
  }

  .forgot-password-card-logo img {
    max-width: 200px;
    height: auto;
  }

  .forgot-password-card h2 {
    color: #333;
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 12px;
  }

  .forgot-password-card p {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 30px;
  }

  .mb-3 {
    margin-bottom: 20px;
    text-align: left;
  }

  .mb-3 label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #333;
    margin-bottom: 8px;
  }

  .mb-3 input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-family: inherit;
  }

  .mb-3 input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  .mb-3 input::placeholder {
    color: #999;
  }

  .btn-reset {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    margin-bottom: 20px;
  }

  .btn-reset:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
  }

  .btn-reset:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
  }

  .form-footer {
    text-align: center;
    font-size: 14px;
    color: #666;
  }

  .form-footer a {
    color: #667eea;
    text-decoration: none;
    transition: color 0.2s;
    font-weight: 500;
  }

  .form-footer a:hover {
    color: #764ba2;
    text-decoration: underline;
  }

  /* Responsive */
  @media (max-width: 480px) {
    .forgot-password-card {
      padding: 40px 30px;
    }

    .forgot-password-card h2 {
      font-size: 24px;
    }

    .forgot-password-card-logo img {
      max-width: 150px;
    }

    body {
      padding: 20px;
    }
  }
</style>

<div class="forgot-password-container">
  <div class="forgot-password-card">
    <div class="forgot-password-card-logo">
      <a href="<?= SystemURLs::getRootPath() ?>/">
        <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="<?= ChurchMetaData::getChurchName() ?>">
      </a>
    </div>

    <h2><?= gettext('Reset your password') ?></h2>
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
require(SystemURLs::getDocumentRoot() ."/Include/FooterNotLoggedIn.php");
