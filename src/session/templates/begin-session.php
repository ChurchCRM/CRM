<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

$sPageTitle = 'Login';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital@0;1&family=Inter:wght@400;500;600;700&display=swap');

body.login-page {
    background: url('/Images/church-bg.png') no-repeat center center fixed;
    background-size: cover;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
body.login-page::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.35);
}
.login-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 28px;
}
.login-box {
    width: 440px;
    position: relative;
    z-index: 10;
}
.login-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border-radius: 28px;
    border: 1px solid rgba(212, 175, 55, 0.25);
    padding: 45px 40px;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.25), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}
.login-title-church {
    font-family: 'Inter', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: #1a3a5c;
    letter-spacing: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.login-title-icon {
    color: #d4af37;
    font-size: 24px;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.login-title-crm {
    font-family: 'Inter', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: #d4af37;
    letter-spacing: 3px;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}
.login-subtitle {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    color: #1a3a5c;
    margin: 10px 0 5px 0;
    font-style: italic;
}
.login-subtitle-2 {
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    color: #1a3a5c;
    margin: 0 0 30px 0;
    text-transform: uppercase;
    letter-spacing: 4px;
    font-weight: 600;
}
.login-input-group {
    position: relative;
    margin-bottom: 18px;
}
.login-input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    border: 2px solid rgba(212, 175, 55, 0.6);
    border-radius: 12px;
    font-size: 14px;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.05);
    box-sizing: border-box;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
}
.login-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}
.login-input:focus {
    outline: none;
    border-color: #d4af37;
    background: rgba(255, 255, 255, 0.1);
    box-shadow: 0 0 25px rgba(212, 175, 55, 0.15);
}
.login-input-group .input-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: #d4af37;
    font-size: 16px;
}
.login-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #1a3a5c 0%, #0d2840 100%);
    color: #d4af37;
    border: 2px solid #d4af37;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 12px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-family: 'Inter', sans-serif;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.login-btn i {
    font-size: 14px;
}
.login-btn:hover {
    background: linear-gradient(135deg, #d4af37 0%, #c5a028 100%);
    color: #1a3a5c;
    border-color: #d4af37;
    box-shadow: 0 8px 30px rgba(212, 175, 55, 0.35);
    transform: translateY(-2px);
}
.login-forgot {
    text-align: center;
    margin-top: 20px;
}
.login-forgot a {
    color: #1a3a5c;
    text-decoration: none;
    font-size: 13px;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
    transition: all 0.3s ease;
}
.login-forgot a:hover {
    color: #d4af37;
    text-decoration: underline;
}
.login-divider {
    display: flex;
    align-items: center;
    margin: 25px 0;
}
.login-divider::before,
.login-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(212, 175, 55, 0.35);
}
.login-divider span {
    padding: 0 15px;
    color: #1a3a5c;
    font-size: 11px;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
}
.login-register {
    text-align: center;
}
.login-register p {
    color: #1a3a5c;
    font-size: 13px;
    margin: 0 0 14px 0;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
}
.login-register-btn {
    width: 100%;
    padding: 13px;
    background: transparent;
    color: #d4af37;
    border: 2px solid #d4af37;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-decoration: none;
}
.login-register-btn i {
    font-size: 14px;
}
.login-register-btn:hover {
    background: rgba(212, 175, 55, 0.1);
    box-shadow: 0 6px 20px rgba(212, 175, 55, 0.25);
    transform: translateY(-2px);
}
.login-error {
    background: rgba(220, 38, 38, 0.9);
    border: 1px solid rgba(254, 202, 202, 0.4);
    color: #ffffff;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 13px;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
}
</style>

<div class="login-box">
    <div class="login-card">
        <div class="login-overlay"></div>
        
        <div style="position: relative; z-index: 2;">
            <h1 class="login-title-church">CHURCH <i class="fa-solid fa-cross login-title-icon"></i> <span class="login-title-crm">CRM</span></h1>
            <p class="login-subtitle">Main St. Cathedral</p>
            <p class="login-subtitle-2">Secure Login</p>
            
            <?php
            if (isset($_GET['Timeout'])) {
                echo '<div class="login-error">Your previous session timed out. Please login again.</div>';
            }
            if (isset($sErrorText)) {
                echo '<div class="login-error">' . $sErrorText . '</div>';
            }
            ?>
            <form role="form" method="post" name="LoginForm" action="<?= $localAuthNextStepURL ?>">
                <div class="login-input-group">
                    <input type="text" id="UserBox" name="User" class="login-input" value="<?= $prefilledUserName ?>" placeholder="Email/Username" required autofocus>
                    <span class="input-icon"><i class="fa-solid fa-envelope"></i></span>
                </div>
                <div class="login-input-group">
                    <input type="password" id="PasswordBox" name="Password" class="login-input" placeholder="Password" required>
                    <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                </div>
                <button type="submit" class="login-btn">
                    <i class="fa-solid fa-cross"></i>
                    Sign In
                </button>
            </form>
            <div class="login-forgot">
                <a href="<?= $forgotPasswordURL ?>">I forgot my password</a>
            </div>
            
            <div class="login-divider">
                <span>OR</span>
            </div>
            
            <div class="login-register">
                <p>New to Main St. Cathedral?</p>
                <a href="<?= SystemURLs::getRootPath() ?>/external/register/" class="login-register-btn">
                    <i class="fa-solid fa-user-check"></i>
                    Register a New Family
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php';
