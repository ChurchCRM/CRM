<?php

/**
 * Impersonation banner (issue #9843).
 *
 * Rendered immediately after the opening <body> tag by BOTH header layouts —
 * Include/Header.php (every legacy `.php` page and every Slim MVC page,
 * including /v2) and Include/HeaderNotLoggedIn.php (the auth-flow pages, the
 * 404s and the Bootstrapper error page) — and, since MP8 (#9869), captured by
 * ChurchCRM\Portal\PortalExtension and printed by the Member Portal's Twig
 * layout, which is where an EditSelf-exclusive user is confined (#9863).
 * Keeping it in a single include is deliberate: a second copy would drift, and
 * a page without the banner would let an administrator forget they are acting
 * as someone else — or strand them with no way back.
 *
 * Nothing is emitted unless there is an authenticated session AND that session
 * carries a masquerade record, so the include is inert on anonymous pages.
 * `body.impersonating` (set by both headers) supplies the offset that keeps the
 * page content and the fixed navbars clear of the bar — see
 * skin/scss/_impersonation.scss.
 */

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\ImpersonationService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\InputUtils;

if (!ImpersonationService::isActive() || !AuthenticationManager::isUserAuthenticated()) {
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
