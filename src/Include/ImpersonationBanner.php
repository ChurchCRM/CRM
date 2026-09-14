<?php

/**
 * Impersonation banner (issue #9843).
 *
 * Rendered by Include/Header.php immediately after the opening <body> tag, so
 * it reaches every legacy `.php` page and every Slim MVC page (including the
 * /v2 module) through the one header both of them require. Keeping it in a
 * single include is deliberate: a second copy would drift, and a page without
 * the banner would let an administrator forget they are acting as someone else.
 *
 * Nothing is emitted unless the session actually carries a masquerade record.
 * `body.impersonating` (set in Header.php) supplies the offset that keeps the
 * page content and the fixed navbars clear of the bar — see
 * skin/scss/_impersonation.scss.
 */

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

if (!ImpersonationService::isActive()) {
    return;
}

$impersonationTargetName = AuthenticationManager::getCurrentUser()->getName();
$impersonationExitLabel = gettext('Exit and return to your own account');
?>
<div id="impersonationBanner" class="impersonation-bar bg-warning-lt d-print-none" role="status">
  <div class="impersonation-bar-inner">
    <span class="impersonation-bar-text">
      <i class="fa-solid fa-user-secret me-2" aria-hidden="true"></i>
      <?= InputUtils::escapeHTML(sprintf(
          /* Translators: %s is the name of the user the administrator is currently logged in as. */
          gettext('You are logged in as %s. Actions are recorded as them.'),
          $impersonationTargetName
      )) ?>
    </span>
    <form class="impersonation-bar-actions" method="post"
          action="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/v2/user/impersonate/exit') ?>">
      <?= CSRFUtils::getTokenInputField('user_impersonate') ?>
      <button type="submit" id="impersonationExit" class="btn btn-icon btn-sm btn-warning"
              aria-label="<?= InputUtils::escapeAttribute($impersonationExitLabel) ?>"
              title="<?= InputUtils::escapeAttribute($impersonationExitLabel) ?>">
        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
      </button>
    </form>
  </div>
</div>
