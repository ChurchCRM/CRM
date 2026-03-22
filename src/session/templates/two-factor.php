<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('Login');
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>

<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  .login-container {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    padding: 20px;
  }

  .login-wrapper {
    display: flex;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    background: white;
    min-height: 500px;
  }

  .login-hero {
    flex: 1;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 40px;
    position: relative;
    color: white;
    overflow: hidden;
  }

  .login-hero::before {
    content: '';
    position: absolute;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    top: -100px;
    right: -100px;
  }

  .login-hero::after {
    content: '';
    position: absolute;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    bottom: -80px;
    left: -80px;
  }

  .login-hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
  }

  .login-hero-logo {
    margin-bottom: 40px;
  }

  .login-hero-logo img {
    max-width: 200px;
    height: auto;
  }

  .login-hero h2 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 16px;
    line-height: 1.2;
  }

  .login-hero p {
    font-size: 16px;
    opacity: 0.95;
    line-height: 1.6;
  }

  .login-form-section {
    flex: 1;
    padding: 60px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .login-form-inner {
    max-width: 100%;
  }

  .login-form-header {
    margin-bottom: 40px;
  }

  .login-form-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
  }

  .login-form-header p {
    color: #666;
    font-size: 14px;
  }

  .mb-3 {
    margin-bottom: 20px;
  }

  .mb-3 label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
    font-size: 14px;
  }

  .mb-3 input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .mb-3 input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  .mb-3 input::placeholder {
    color: #999;
  }

  /* 2FA code input styling */
  #TwoFACode {
    font-size: 2em !important;
    letter-spacing: 0.3em !important;
    text-align: center !important;
    font-weight: 300 !important;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
  }

  .alert {
    border-radius: 6px;
    padding: 12px 16px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
  }

  .alert-info {
    background-color: #e7f3ff;
    border: 1px solid #b3d9ff;
    color: #004085;
  }

  .alert-danger {
    background-color: #fee;
    border: 1px solid #fcc;
    color: #c33;
  }

  .alert i {
    font-size: 18px;
    flex-shrink: 0;
  }

  .btn-sign-in {
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
    margin: 20px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-sign-in:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
  }

  .back-link {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
  }

  .back-link a {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
  }

  .back-link a:hover {
    color: #764ba2;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .login-wrapper {
      flex-direction: column;
      min-height: auto;
    }

    .login-hero {
      padding: 40px 30px;
      min-height: 250px;
    }

    .login-form-section {
      padding: 40px 30px;
    }

    .login-hero h2 {
      font-size: 22px;
    }

    .login-hero-logo img {
      max-width: 150px;
    }

    body {
      background: white;
    }
  }
</style>

<div class="login-container">
  <div class="login-wrapper">
    <!-- Hero Section -->
    <div class="login-hero">
      <div class="login-hero-content">
        <div class="login-hero-logo">
          <img src="<?= SystemURLs::getRootPath() ?>/Images/logo-churchcrm-350.jpg" alt="ChurchCRM" />
        </div>
        <h2><?= ChurchMetaData::getChurchName() ?></h2>
        <p><?= gettext('Secure your account with two-factor authentication') ?></p>
      </div>
    </div>

    <!-- Form Section -->
    <div class="login-form-section">
      <div class="login-form-inner">
        <div class="login-form-header">
          <h1><?= gettext('Verify your identity') ?></h1>
          <p><?= gettext('Enter the 6-digit code from your authenticator app') ?></p>
        </div>

        <div class="alert alert-info">
          <i class="fa-solid fa-mobile"></i>
          <div><?= gettext('Enter the 6-digit code from your authenticator app') ?></div>
        </div>

        <?php if (isset($bInvalidCode) && $bInvalidCode) { ?>
          <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= gettext('Invalid code. Please try again.') ?></div>
          </div>
        <?php } ?>

        <form method="post" name="TwoFAForm" action="<?= SystemURLs::getRootPath()?>/session/two-factor">
          <div class="mb-3">
            <label for="TwoFACode"><?= gettext('Authentication Code') ?></label>
            <input type="text" id="TwoFACode" name="TwoFACode" placeholder="000000" maxlength="6" inputmode="numeric" required autofocus>
          </div>

          <button type="submit" class="btn-sign-in">
            <i class="fa-solid fa-right-to-bracket"></i><?= gettext('Verify & Sign In') ?>
          </button>
        </form>

        <div class="back-link">
          <a href="<?= SystemURLs::getRootPath() ?>/session/begin"><?= gettext('Use a different account') ?></a>
        </div>
      </div>
    </div>
  </div>
</div>
