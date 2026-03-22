<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;

$sPageTitle = gettext("Password Reset Successful");
require(SystemURLs::getDocumentRoot() . "/Include/HeaderNotLoggedIn.php");
?>

<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
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

  .success-box {
    background: #f0f8f7;
    border: 2px solid #43d997;
    border-radius: 8px;
    padding: 30px 20px;
    text-align: center;
  }

  .success-icon {
    width: 60px;
    height: 60px;
    background: #43d997;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    margin: 0 auto 20px;
  }

  .success-box h2 {
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 12px;
  }

  .success-box p {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 12px;
  }

  .success-box .small {
    color: #777;
    font-size: 13px;
    display: block;
    margin: 8px 0;
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
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .btn-sign-in:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
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
        <p><?= gettext('Check your email') ?></p>
      </div>
    </div>

    <!-- Success Section -->
    <div class="login-form-section">
      <div class="login-form-inner">
        <div class="success-box">
          <div class="success-icon">
            <i class="fa-solid fa-check"></i>
          </div>
          <h2><?= gettext("Password Reset Successful") ?></h2>
          <p><?= gettext("A new password has been generated and sent to your email address.") ?></p>
          <span class="small"><?= gettext("Please check your email (including spam/junk folder) for your temporary password.") ?></span>
          <span class="small"><?= gettext("Once you receive the email, you can log in with your temporary password and change it to something you prefer.") ?></span>
        </div>

        <a href="<?= SystemURLs::getRootPath() ?>/session/begin" class="btn-sign-in">
          <i class="fa-solid fa-right-to-bracket"></i>
          <?= gettext("Go to Login") ?>
        </a>
      </div>
    </div>
  </div>
</div>
<?php
require(SystemURLs::getDocumentRoot() . "/Include/FooterNotLoggedIn.php");
