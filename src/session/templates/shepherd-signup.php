<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

$sPageTitle = $mode === 'request' ? 'Request an account' : 'Shepherd account';
$sBodyClass = 'page-auth page-login shepherd-auth';
require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';
?>
<div class="login-container">
  <div class="login-card">
    <div class="login-card-header">
      <div class="login-header-logo"><img src="<?= SystemURLs::assetVersioned('/skin/images/shepherd-logo.png') ?>" alt="Shepherd"></div>
      <h1 class="login-header-church-name">Shepherd</h1>
      <p class="login-header-tagline"><?= InputUtils::escapeHTML(\ChurchCRM\dto\SystemConfig::getValue('sChurchName')) ?></p>
    </div>
    <div class="login-card-body">
      <?php if ($mode === 'request'): ?>
        <div class="login-tab-control" role="tablist" aria-label="Account options">
          <a class="login-tab-btn" href="<?= $sRootPath ?>/session/begin" role="tab"><i class="fa-solid fa-right-to-bracket"></i> Sign In</a>
          <span class="login-tab-btn active" role="tab" aria-selected="true"><i class="fa-solid fa-user-plus"></i> Sign Up</span>
        </div>
        <h2 class="h3">Request an account</h2>
        <p class="text-secondary">Verify your email, then wait for an administrator to approve access.</p>
        <form method="post" action="<?= $sRootPath ?>/session/signup" autocomplete="on">
          <?= CSRFUtils::getTokenInputField('shepherd_signup') ?>
          <div class="row g-3">
            <div class="col-sm-6"><label class="form-label" for="first-name">First name</label><input class="form-control" id="first-name" name="first_name" maxlength="100" autocomplete="given-name" required></div>
            <div class="col-sm-6"><label class="form-label" for="last-name">Last name</label><input class="form-control" id="last-name" name="last_name" maxlength="100" autocomplete="family-name" required></div>
            <div class="col-12"><label class="form-label" for="signup-email">Email</label><input class="form-control" id="signup-email" name="email" type="email" maxlength="254" autocomplete="email" required></div>
            <div class="col-12"><label class="form-label" for="signup-username">Username</label><input class="form-control" id="signup-username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._-]+" autocomplete="username" required><small class="form-text">Letters, numbers, periods, underscores, and hyphens.</small></div>
            <div class="col-12"><label class="form-label" for="signup-note">Note <span class="text-secondary">(optional)</span></label><textarea class="form-control" id="signup-note" name="note" maxlength="2000" rows="3"></textarea></div>
            <div class="shepherd-honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
          </div>
          <button class="btn-sign-in mt-4" type="submit">Send verification email</button>
        </form>
      <?php elseif ($mode === 'password'): ?>
        <h2 class="h3">Choose your password</h2>
        <?php if (!empty($message)): ?><div class="alert alert-danger" role="alert"><?= InputUtils::escapeHTML($message) ?></div><?php endif; ?>
        <form method="post" action="<?= $sRootPath ?>/session/signup/password/<?= rawurlencode($token) ?>">
          <?= CSRFUtils::getTokenInputField('shepherd_password_setup') ?>
          <div class="mb-3"><label class="form-label" for="new-password">Password</label><input class="form-control" id="new-password" name="password" type="password" autocomplete="new-password" required></div>
          <div class="mb-3"><label class="form-label" for="confirm-password">Confirm password</label><input class="form-control" id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required></div>
          <button class="btn-sign-in" type="submit">Set password</button>
        </form>
      <?php else: ?>
        <div class="alert <?= !empty($success) ? 'alert-success' : ($mode === 'submitted' ? 'alert-info' : 'alert-warning') ?>" role="status">
          <?= InputUtils::escapeHTML((string) $message) ?>
        </div>
        <a class="btn btn-primary w-100" href="<?= $sRootPath ?>/session/begin">Return to Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
