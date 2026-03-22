<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
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
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
  }

  .login-wrapper {
    display: flex;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    background: white;
    min-height: auto;
  }

  .login-hero {
    flex: 0 0 45%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 50px 40px;
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
    margin-bottom: 30px;
  }

  .login-hero-logo img {
    max-width: 160px;
    height: auto;
  }

  .login-hero h2 {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 12px;
    line-height: 1.2;
  }

  .login-hero p {
    font-size: 14px;
    opacity: 0.95;
    line-height: 1.5;
  }

  .login-form-section {
    flex: 1;
    padding: 45px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .login-form-header {
    margin-bottom: 28px;
  }

  .login-form-header h1 {
    font-size: 22px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 6px;
  }

  .login-form-header p {
    color: #666;
    font-size: 13px;
  }

  .form-group {
    margin-bottom: 15px;
  }

  .form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #333;
    font-size: 13px;
  }

  .form-group input {
    width: 100%;
    padding: 9px 11px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  .form-group input::placeholder {
    color: #999;
  }

  .form-check {
    display: flex;
    align-items: center;
    margin-bottom: 13px;
  }

  .form-check input {
    margin: 0;
    margin-right: 8px;
    width: auto;
  }

  .form-check label {
    margin: 0;
    font-size: 13px;
    cursor: pointer;
  }

  .form-footer {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 18px;
    font-size: 12px;
  }

  .form-footer a {
    color: #667eea;
    text-decoration: none;
    transition: color 0.2s;
  }

  .form-footer a:hover {
    color: #764ba2;
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
    margin-bottom: 24px;
  }

  .btn-sign-in:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
  }

  .btn-register {
    width: 100%;
    padding: 11px;
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
    border-radius: 6px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
  }

  .btn-register:hover {
    background: #f8f9ff;
    border-color: #764ba2;
    color: #764ba2;
    transform: translateY(-2px);
  }

  .signup-link {
    text-align: center;
    border-top: 1px solid #eee;
    padding-top: 24px;
    margin-top: 6px;
  }

  .signup-link h3 {
    color: #1a1a1a;
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 14px 0;
    line-height: 1.3;
  }

  .alert {
    border-radius: 6px;
    margin-bottom: 20px;
    padding: 12px 16px;
    font-size: 14px;
  }

  .alert-danger {
    background-color: #fee;
    border: 1px solid #fcc;
    color: #c33;
  }

  .alert-warning {
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
  }

  /* Responsive */
  @media (max-width: 768px) {
    .login-wrapper {
      flex-direction: column;
    }

    .login-hero {
      flex: none;
      padding: 40px 30px;
    }

    .login-form-section {
      padding: 40px 30px;
    }

    .login-hero h2 {
      font-size: 20px;
    }

    .login-hero-logo img {
      max-width: 130px;
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
        <p><?= gettext('Manage your church community with ease and confidence') ?></p>
      </div>
    </div>

    <!-- Login Form Section -->
    <div class="login-form-section">
      <div class="login-form-inner">
        <div class="login-form-header">
          <h1><?= gettext('Login to your account') ?></h1>
          <p><?= gettext('Welcome back! Please enter your details') ?></p>
        </div>

        <?php
        if (isset($_GET['Timeout'])) {
            $loginPageMsg = gettext('Your previous session timed out. Please login again.');
        }

        // output warning and error messages
        if (isset($sErrorText)) {
            echo '<div class="alert alert-danger">' . htmlspecialchars($sErrorText) . '</div>';
        }
        if (isset($loginPageMsg)) {
            echo '<div class="alert alert-warning">' . htmlspecialchars($loginPageMsg) . '</div>';
        }
        ?>

        <form method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
          <div class="form-group">
            <label for="UserBox"><?= gettext('Email address') ?></label>
            <input type="text" id="UserBox" name="User" placeholder="<?= gettext('name@example.com') ?>" value="<?= htmlspecialchars($prefilledUserName) ?>" required autofocus>
          </div>

          <div class="form-group">
            <label for="PasswordBox"><?= gettext('Password') ?></label>
            <input type="password" id="PasswordBox" name="Password" placeholder="<?= gettext('Enter your password') ?>" required>
          </div>

          <div class="form-check">
            <input type="checkbox" id="rememberMe" name="RememberMe" value="1">
            <label for="rememberMe"><?= gettext('Remember me on this device') ?></label>
          </div>

          <div class="form-footer">
            <span></span>
            <?php if (SystemConfig::getBooleanValue('bEnableLostPassword')) { ?>
              <a href="<?= htmlspecialchars($forgotPasswordURL) ?>"><?= gettext('Forgot password?') ?></a>
            <?php } ?>
          </div>

          <button type="submit" class="btn-sign-in"><?= gettext('Sign in') ?></button>
        </form>

        <?php if (SystemConfig::getBooleanValue('bEnableSelfRegistration')) { ?>
          <div class="signup-link">
            <h3><?= gettext('New to') ?> <?= ChurchMetaData::getChurchName() ?>?</h3>
            <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="btn-register"><?= gettext('Register a New Family') ?></a>
          </div>
        <?php } else { ?>
          <div class="signup-link">
            <p><?= gettext('Need help?') ?> <a href="<?= SystemURLs::getRootPath() ?>/external/register/" style="color: #999;"><?= gettext('Contact us') ?></a></p>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
