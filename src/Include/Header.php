<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\UserSetting;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\SystemIdentity;
use ChurchCRM\view\MenuRenderer;
use ChurchCRM\Service\DashboardLandingService;
use ChurchCRM\Service\InstallSetupService;
use ChurchCRM\Service\LocaleService;

$localeInfo = Bootstrapper::getCurrentLocale();

// Turn ON output buffering
ob_start();

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system for logged-in users
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

// Resolve theme attributes from user settings
$currentUser = AuthenticationManager::getCurrentUser();
// Until the church has a name, an administrator sees only the Setup Dashboard: no sidebar, a minimal top bar.
$setupMode = $currentUser->isAdmin() && InstallSetupService::needsChurchSetup();
$homeUrl = $setupMode ? SystemURLs::getRootPath() . DashboardLandingService::SETUP_PATH : DashboardLandingService::urlFor($currentUser);
// Hydrate the notification registry before the top nav renders: the
// notification center drops down from it. Remote announcements join on
// every page except in setup mode — the church has not used the system yet.
NotificationService::loadSessionNotifications(!$setupMode);
$pluginsClientConfig = PluginManager::getPluginsClientConfig();
$_themeMode = $currentUser->getThemeMode(); // 'auto' | 'default' | 'dark'
$_themeAttrs = ' data-theme-mode="' . InputUtils::escapeAttribute($_themeMode) . '"';
// Explicit dark: stamp data-bs-theme on <html> so first paint does not wait on theme.min.js.
if ($_themeMode === 'dark') {
    $_themeAttrs .= ' data-bs-theme="dark"';
}
$_themePrimary = $currentUser->getSettingValue('ui.theme.primary');
if ($_themePrimary !== '') {
    $_themeAttrs .= ' data-bs-theme-primary="' . InputUtils::escapeAttribute($_themePrimary) . '"';
}
// Top level menu index counter
$MenuFirst = 1;
// Currency substrate — data attribute on <html> + CSS custom property via <style> block.
// The symbol is JSON-encoded so any char (including '"' and backslash) produces
// a valid CSS string literal without breaking the declaration.
$_currencyAttrs     = ' data-currency-position="' . InputUtils::escapeAttribute(CurrencyFormatter::position()) . '"';
$_currencySymbolCss = json_encode(CurrencyFormatter::symbol(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html data-bs-navbar-position="combined"<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?><?= $_themeAttrs ?><?= $_currencyAttrs ?>>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <!-- Before skin CSS so auto mode can set data-bs-theme before first paint. -->
  <script src="<?= SystemURLs::assetVersioned('/skin/v2/theme.min.js') ?>"></script>
  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
  <?= PluginManager::getPluginHeadContent() ?>
  <style nonce="<?= SystemURLs::getCSPNonce() ?>">:root { --currency-symbol: <?= $_currencySymbolCss ?>; }</style>

</head>

<body class="antialiased">
<a class="skip-link visually-hidden-focusable" href="#main-content"><?= gettext('Skip to main content') ?></a>
<div class="page">

  <!-- Issue Report Modal -->
  <div id="IssueReportModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
      <div class="modal-content" id="bugForm">
        <form name="issueReport">
          <input type="hidden" name="pageName" value="<?= InputUtils::escapeAttribute($_SERVER['REQUEST_URI'] ?? '') ?>"/>
          <div class="modal-header">
            <h5 class="modal-title"><i class="fa-solid fa-bug me-2"></i><?= gettext('Report an Issue') ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info mb-3">
              <i class="fa-solid fa-circle-info me-1"></i>
              <?= gettext('Clicking "Open GitHub Issue" will open a new tab with your system info pre-filled. No personally identifiable information will be included unless you add it.') ?>
            </div>
            <div class="mb-3">
              <label for="issueDescription" class="fw-bold"><?= gettext('Describe the issue') ?> <span class="text-body-secondary fw-normal">(<?= gettext('optional') ?>)</span></label>
              <textarea id="issueDescription" class="form-control" rows="4" placeholder="<?= gettext('What went wrong? What did you expect to happen?') ?>"></textarea>
            </div>
            <?php if ($currentUser->isAdmin()) { ?>
            <div class="alert alert-warning mb-0">
              <i class="fa-solid fa-file-lines me-1"></i>
              <?= gettext('As an admin, please also review the') ?>
              <a href="<?= SystemURLs::getRootPath() ?>/admin/system/logs" target="_blank" rel="noopener"><?= gettext('system logs') ?></a>
              <?= gettext('for errors around the time this happened, and download and attach the relevant log file to your GitHub issue — it helps us fix the problem without asking follow-up questions.') ?>
            </div>
            <?php } ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
            <button type="button" class="btn btn-primary" id="submitIssue">
              <i class="fa-brands fa-github me-1"></i><?= gettext('Open GitHub Issue') ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- End Issue Report Modal -->

  <?php
  // Initialize window.CRM and body scripts
  $tableSizeSetting = $currentUser->getSetting("ui.table.size");
  if (empty($tableSizeSetting)) {
      $tableSize = 10;
  } else {
      $tableSize = $tableSizeSetting->getValue();
  }
  ?>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
      window.CRM.applyPageConfig(<?= InputUtils::jsonEncodeForScript([
          'root' => SystemURLs::getRootPath(),
          'fullURL' => SystemURLs::getURL(),
          'lang' => $localeInfo->getLanguageCode(),
          'isRTL' => $localeInfo->isRTL(),
          'userId' => (string) $currentUser->getId(),
          'userName' => $currentUser->getPerson()?->getFullName() ?? '',
          'version' => $_SESSION['sSoftwareInstalledVersion'] ?? 'unknown',
          'systemLocale' => $localeInfo->getSystemLocale(),
          'locale' => $localeInfo->getLocale(),
          'shortLocale' => $localeInfo->getShortLocale(),
          'timeZone' => SystemConfig::getValue('sTimeZone'),
          'maxUploadSize' => SystemService::getMaxUploadFileSize(true),
          'maxUploadSizeBytes' => (string) SystemService::getMaxUploadFileSize(false),
          'datePickerformat' => DateTimeUtils::datePickerJsFormat(),
          'churchWebSite' => SystemConfig::getValue('sChurchWebSite'),
          'systemConfigs' => [
              'sDateTimeFormat' => json_decode(DateTimeUtils::getDateTimeFormatForJs(), true),
          ],
          'comm' => [
              'emailSendingEnabled' => SystemConfig::isEmailEnabled(),
              'emailSignature' => \ChurchCRM\Service\EmailComposerService::defaultSignature($currentUser),
              'vonageEnabled' => PluginManager::getPlugin('vonage')?->isConfigured() ?? false,
              'defaultEmailToAddress' => $currentUser->isEmailEnabled() ? SystemConfig::getValue('sToEmailAddress') : '',
              'emailHistoryURL' => match (true) {
                  $currentUser->isAdmin()        => SystemURLs::getRootPath() . '/admin/email/log',
                  $currentUser->isEmailEnabled() => SystemURLs::getRootPath() . '/people/email',
                  default                        => '',
              },
          ],
          'plugins' => $pluginsClientConfig ?: new stdClass(),
          'bEnableGravatarPhotos' => $pluginsClientConfig['gravatar']['enabled'] ?? false,
          'permissions' => [
              'addRecords' => $currentUser->isAddRecordsEnabled(),
              'editRecords' => $currentUser->isEditRecordsEnabled(),
          ],
          'personDeleteBlocked' => (object) PersonService::getLoginDeletionBlockedReasons(),
          'PageName' => $_SERVER['REQUEST_URI'] ?? '',
          'telemetry' => [
              'level' => TelemetryService::getLevel(),
              'properties' => array_diff_key(TelemetryService::baseProperties(), ['$lib' => true]),
              'install_profile' => TelemetryService::installProfile(),
              'key' => TelemetryService::isEnabled() ? TelemetryService::POSTHOG_KEY : '',
              'endpoint' => TelemetryService::POSTHOG_ENDPOINT,
              'distinctID' => SystemIdentity::current(),
          ],
          'currency' => CurrencyFormatter::toArray(),
          'dataTablePageLength' => (int) $tableSize,
          'dataTableLanguageUrl' => SystemURLs::getRootPath() . '/locale/vendor/datatables/' . $localeInfo->getDataTables() . '.json',
          'localeConfig' => $localeInfo->getLocaleConfigArray(),
      ]) ?>);
  </script>
  <script src="<?= SystemURLs::assetVersioned('/skin/js/CRMJSOM.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/js/CommunicationUtils.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/v2/sidebar-toggle.min.js') ?>" defer></script>

  <!-- ============================================================ -->
  <!-- Sidebar (Tabler vertical navbar)                              -->
  <!-- ============================================================ -->
  <?php if (!$setupMode): ?>
  <aside class="navbar navbar-vertical navbar-expand-xl d-print-none<?= $currentUser->getSettingValue(UserSetting::UI_SIDEBAR_FOLDED) === '1' ? ' navbar-folded-hover' : '' ?>" id="sidebar">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
              aria-controls="sidebar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle navigation') ?>">
        <span class="navbar-toggler-icon"></span>
      </button>
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-xl-3">
          <?php MenuRenderer::renderMenu(); ?>
        </ul>
      </div>
    </div>
  </aside>
  <?php endif; ?>

  <!-- ============================================================ -->
  <!-- Topbar                                                        -->
  <!-- ============================================================ -->
  <header class="navbar navbar-expand-md d-print-none sticky-top order-first order-xl-0" id="topbar">
    <div class="container-xl">

      <?php if (!$setupMode): ?>
      <button type="button" id="menu-toggle"
              class="btn btn-ghost-secondary d-inline-flex d-xl-none align-items-center me-2"
              data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
              aria-controls="sidebar-menu" aria-expanded="false"
              aria-label="<?= InputUtils::escapeAttribute(gettext('Toggle navigation')) ?>">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
      </button>
      <?php endif; ?>

      <?php if (!$setupMode): ?>
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#navbar-menu"
              aria-controls="navbar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle search') ?>">
        <i class="fa-solid fa-search"></i>
      </button>
      <?php endif; ?>

      <?php if (!$setupMode): ?>
      <button type="button" id="desktop-sidebar-toggle"
              class="btn btn-ghost-secondary d-none d-xl-inline-flex align-items-center me-2"
              aria-controls="sidebar" aria-expanded="true"
              aria-label="<?= InputUtils::escapeAttribute(gettext('Collapse sidebar')) ?>"
              title="<?= InputUtils::escapeAttribute(gettext('Collapse sidebar')) ?>"
              data-collapse-label="<?= InputUtils::escapeAttribute(gettext('Collapse sidebar')) ?>"
              data-expand-label="<?= InputUtils::escapeAttribute(gettext('Expand sidebar')) ?>">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
      </button>
      <?php endif; ?>

      <?php if ($setupMode): ?>
      <?php include __DIR__ . '/partials/brand.php'; ?>
      <?php endif; ?>

      <!-- Right-side nav items -->
      <div class="navbar-nav flex-row order-md-last ms-auto">

        <!-- System Update Notification -->
        <?php
        $showUpdateMenu = isset($_SESSION['systemUpdateAvailable']) && $_SESSION['systemUpdateAvailable'] === true;
        $updateVersion  = $_SESSION['systemUpdateVersion'] ?? null;
        ?>
        <div class="nav-item dropdown <?= $showUpdateMenu ? '' : 'd-none' ?>" id="systemUpdateMenuItem">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#"
             id="upgradeMenu" title="<?= gettext('New Release') ?>">
            <i class="fa-solid fa-download"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <?php if ($currentUser->isAdmin()) { ?>
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="dropdown-item"
               title="<?= gettext('New Release') ?>">
              <i class="fa-solid fa-champagne-glasses me-2"></i><?= gettext('New Release') ?>
              <?php if ($updateVersion) { ?>
                <span id="upgradeToVersion" class="ms-1">
                  <?= InputUtils::escapeHTML($updateVersion->MAJOR . '.' . $updateVersion->MINOR . '.' . $updateVersion->PATCH) ?>
                </span>
              <?php } ?>
            </a>
            <?php } ?>
            <a href="https://github.com/ChurchCRM/CRM/releases/latest" target="_blank"
               class="dropdown-item" title="<?= gettext('Release Notes') ?>">
              <i class="fa-solid fa-book me-2"></i><?= gettext('Release Notes') ?>
            </a>
          </div>
        </div>


        <!-- Locale: flag links directly to the localization tab on the profile page -->
        <?php
        $flagCode    = $localeInfo->getCountryFlagCode();
        $nativeName  = $localeInfo->getNativeName();
        $englishName = $localeInfo->getName();
        $hasNative   = $nativeName !== '' && $nativeName !== $englishName;
        $localeUrl   = SystemURLs::getRootPath() . '/account#tab-localization';
        ?>
        <?php if ($setupMode): ?>
        <div class="nav-item ms-1">
          <select class="form-select form-select-sm" id="setupLanguage" aria-label="<?= gettext('Language') ?>">
            <?php foreach (LocaleService::getSupportedLocales() as $_localeName => $_locale): ?>
              <option value="<?= InputUtils::escapeAttribute($_locale['locale']) ?>"<?= $_locale['locale'] === SystemConfig::getValue('sLanguage') ? ' selected' : '' ?>>
                <?= InputUtils::escapeHTML($_locale['nativeName'] ?: $_localeName) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?>
        <div class="nav-item ms-1">
          <a class="nav-link px-0" href="<?= InputUtils::escapeAttribute($localeUrl) ?>"
             title="<?= InputUtils::escapeAttribute($hasNative ? $nativeName . ' — ' . $englishName : $englishName) ?>">
            <span class="flag flag-xs flag-country-<?= InputUtils::escapeAttribute($flagCode) ?>"></span>
          </a>
        </div>

        <!-- Cart -->
        <div class="nav-item dropdown ms-1">
          <a class="nav-link px-0 position-relative" data-bs-toggle="dropdown" href="#">
            <i class="fa-solid fa-cart-shopping"></i>
            <?php if (Cart::countPeople() > 0): ?>
            <span class="badge bg-info position-absolute top-0 end-0 small" id="iconCount"><?= Cart::countPeople() ?></span>
            <?php else: ?>
            <span class="badge bg-info position-absolute top-0 end-0 small d-none" id="iconCount">0</span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <div id="cart-dropdown-menu"></div>
          </div>
        </div>

        <!-- RFC feedback: icon only, count from /api/community-feedback -->
        <div class="nav-item ms-1">
          <a class="nav-link px-0 position-relative" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/v2/community-feedback') ?>"
             title="<?= gettext('Review what we are considering') ?>">
            <i class="fa-solid fa-comments"></i>
            <span class="badge bg-primary position-absolute top-0 end-0 small d-none" id="communityFeedbackCount">0</span>
          </a>
        </div>

        <?php endif; ?>

        <!-- Support -->
        <div class="nav-item dropdown ms-1">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#" id="supportMenu">
            <i class="fa-solid fa-headphones"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <a href="<?= InputUtils::escapeAttribute(SystemURLs::getSupportURL('', (string) ($sPageTitle ?? ''))) ?>" target="help" class="dropdown-item"
               title="<?= gettext('Documentation') ?>">
              <i class="fa-solid fa-book me-2"></i><?= gettext('Documentation') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="#" id="reportIssue" class="dropdown-item"
               data-bs-toggle="modal" data-bs-target="#IssueReportModal"
               title="<?= gettext('Report an Issue') ?>">
              <i class="fa-solid fa-bug me-2"></i><?= gettext('Report an Issue') ?>
            </a>
            <a href="https://discord.gg/tuWyFzj3Nj" target="_blank" class="dropdown-item"
               title="<?= gettext('Discord Chat') ?>">
              <i class="fa-brands fa-discord me-2"></i><?= gettext('Discord Chat') ?>
            </a>
            <a href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/v2/community-feedback') ?>" class="dropdown-item"
               title="<?= gettext('Review what we are considering') ?>">
              <i class="fa-solid fa-comments me-2"></i><?= gettext('Review what we are considering') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= InputUtils::escapeAttribute(SystemURLs::attributed('https://docs.churchcrm.io/contributing', 'help_menu_contributing')) ?>" target="_blank" class="dropdown-item"
               title="<?= gettext('Contributing') ?>">
              <i class="fa-brands fa-github me-2"></i><?= gettext('Contributing') ?>
            </a>
          </div>
        </div>

        <?php
        // Notification center: everything that used to stack up as banners at
        // the top of every page lives in this one dropdown, so the page itself
        // stays clean for the work the user came to do. The center renders
        // only when something actually needs attention.
        $centerNotifications = NotificationService::getNotifications();
        $demoDataLoaded = InstallSetupService::isDemoDataLoaded();
        $twoFAGrace = $currentUser->getTwoFactorGraceStatus() === 'within-grace';
        $twoFADaysLeft = $twoFAGrace ? $currentUser->getTwoFactorGraceDaysRemaining() : 0;
        $telemetryPrompt = $currentUser->isAdmin() && !$setupMode
            && !TelemetryService::capturesPageViews()
            && SystemConfig::getValue('sTelemetryAskedVersion') === '';
        $centerCount = count($centerNotifications) + ($demoDataLoaded ? 1 : 0) + ($twoFAGrace ? 1 : 0) + ($telemetryPrompt ? 1 : 0);
        $centerBadgeClass = ($twoFAGrace && $twoFADaysLeft <= 1) ? 'bg-danger' : 'bg-primary';
        foreach ($centerNotifications as $centerNotification) {
            if ($centerNotification->getType() === 'danger') {
                $centerBadgeClass = 'bg-danger';
                break;
            }
        }
        ?>
        <?php if ($centerCount > 0): ?>
        <div class="nav-item dropdown" id="notificationCenterMenuItem">
          <a class="nav-link px-0 position-relative" data-bs-toggle="dropdown" href="#"
             id="notificationCenterMenu" title="<?= gettext('Notifications') ?>"
             aria-label="<?= gettext('Notifications') ?>">
            <i class="fa-solid fa-inbox" aria-hidden="true"></i>
            <span class="badge <?= $centerBadgeClass ?> position-absolute top-0 end-0 small" id="notificationCenterCount"><?= $centerCount ?></span>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <?php foreach ($centerNotifications as $notification): ?>
            <?php
            // "Header — body" titles read as a header line plus a body line.
            $noticeTitle = $notification->getTitle();
            $noticeBody = $notification->getMessage();
            if (str_contains($noticeTitle, ' — ')) {
                [$noticeTitle, $noticeBody] = explode(' — ', $noticeTitle, 2);
                $noticeBody = mb_strtoupper(mb_substr($noticeBody, 0, 1)) . mb_substr($noticeBody, 1);
            }
            ?>
            <div class="dropdown-item-text crm-notification-item"
                 <?= $notification->getId() ? 'data-notification-id="' . InputUtils::escapeAttribute($notification->getId()) . '"' : '' ?>
                 <?= $notification->getDismissSettingKey() ? 'data-dismiss-key="' . InputUtils::escapeAttribute($notification->getDismissSettingKey()) . '"' : '' ?>>
              <div class="d-flex">
                <div class="crm-notification-icon"><i class="fa-solid fa-<?= InputUtils::escapeHTML($notification->getIcon() ?: 'circle-info') ?> text-<?= InputUtils::escapeHTML($notification->getType()) ?>" aria-hidden="true"></i></div>
                <div class="flex-grow-1 min-w-0">
                  <div class="crm-notification-title small fw-semibold"><?= InputUtils::escapeHTML($noticeTitle) ?></div>
                  <div class="crm-notification-text text-secondary small"><?= InputUtils::escapeHTML($noticeBody) ?></div>
                  <div class="d-flex gap-2 mt-2 crm-notification-actions">
                    <?php if ($notification->getUrl()): ?>
                    <a href="<?= InputUtils::escapeAttribute($notification->getUrl()) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><?= gettext('Learn more') ?></a>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline-primary btn-sm js-notification-more"
                            data-title="<?= InputUtils::escapeAttribute($notification->getTitle()) ?>"
                            data-message="<?= InputUtils::escapeAttribute($notification->getMessage()) ?>"><?= gettext('Read more') ?></button>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($notification->getDismissSettingKey()): ?>
                <button type="button" class="btn-close js-dismiss-notification" data-csrf-token="<?= InputUtils::escapeAttribute(CSRFUtils::generateToken()) ?>" aria-label="<?= gettext('Dismiss') ?>"></button>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if ($demoDataLoaded): ?>
            <div class="dropdown-item-text crm-notification-item" data-notification-id="demo-mode">
              <div class="d-flex">
                <div class="crm-notification-icon"><i class="fa-solid fa-flask text-warning" aria-hidden="true"></i></div>
                <div class="flex-grow-1 min-w-0">
                  <div class="crm-notification-title small fw-semibold"><?= gettext("You're exploring ChurchCRM with demo data.") ?></div>
                  <div class="crm-notification-text text-secondary small"><?= gettext("Everything here is sample data. Start fresh before you enter your church's real information.") ?></div>
                  <?php if ($currentUser->isAdmin()): ?>
                  <div class="d-flex gap-2 mt-2 crm-notification-actions">
                    <button type="button" class="btn btn-outline-warning btn-sm js-start-fresh">
                      <i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i><?= gettext('Start Fresh') ?>
                    </button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($telemetryPrompt): ?>
            <div class="dropdown-item-text crm-notification-item" data-notification-id="telemetry-consent">
              <div class="d-flex">
                <div class="crm-notification-icon"><i class="fa-solid fa-chart-bar text-info" aria-hidden="true"></i></div>
                <div class="flex-grow-1 min-w-0">
                  <div class="crm-notification-title small fw-semibold"><?= gettext('Share which pages are used') ?></div>
                  <div class="crm-notification-text text-secondary small"><?= gettext('Off by default. Helps us improve ChurchCRM. Never includes your church or its people.') ?></div>
                  <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                    <label class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="telemetry-consent-toggle">
                      <span class="form-check-label small"><?= gettext('Enable') ?></span>
                    </label>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug#telemetry" class="small"><?= gettext("See what's shared") ?></a>
                  </div>
                </div>
                <button type="button" class="btn-close js-telemetry-decline" aria-label="<?= gettext('No thanks') ?>" title="<?= gettext('No thanks') ?>"></button>
              </div>
            </div>
            <script src="<?= SystemURLs::assetVersioned('/skin/v2/telemetry-consent.min.js') ?>"></script>
            <?php endif; ?>
            <?php if ($twoFAGrace): ?>
            <div class="dropdown-item-text crm-notification-item" data-notification-id="two-fa-grace">
              <div class="d-flex">
                <div class="crm-notification-icon"><i class="fa-solid fa-shield-halved text-<?= $twoFADaysLeft <= 1 ? 'danger' : 'warning' ?>" aria-hidden="true"></i></div>
                <div class="flex-grow-1 min-w-0">
                  <div class="crm-notification-title small fw-semibold"><?= gettext('Two-factor authentication') ?></div>
                  <div class="crm-notification-text text-secondary small"><?= InputUtils::escapeHTML(sprintf(ngettext('Two-factor authentication is required. You have %d day to enroll.', 'Two-factor authentication is required. You have %d days to enroll.', $twoFADaysLeft), $twoFADaysLeft)) ?></div>
                  <div class="d-flex gap-2 mt-2 crm-notification-actions">
                    <a href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath()) ?>/account/2fa" class="btn btn-outline-primary btn-sm"><?= gettext('Set up now') ?></a>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <div class="modal fade" tabindex="-1" id="notificationDetailPanel" aria-labelledby="notificationDetailTitle" aria-hidden="true">
            <div class="modal-dialog crm-side-panel">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="notificationDetailTitle"></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
                </div>
                <div class="modal-body">
                  <p id="notificationDetailBody" class="mb-3"></p>
                  <a id="notificationDetailLink" class="btn btn-outline-primary btn-sm" href="#" target="_blank" rel="noopener" hidden><?= gettext('Learn more') ?></a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- User -->
        <?php
        $currentUserName = $currentUser->getName();
        $userRole        = $currentUser->isAdmin() ? gettext('Administrator') : gettext('Member');

        // Generate server-side SVG placeholder (data URI) so the avatar shows
        // immediately on page load while the client-side avatar loader runs.
        $nameParts = preg_split('/\s+/', trim($currentUserName));
        if (empty($nameParts) || $nameParts[0] === '') {
          $userInitials = '';
        } elseif (count($nameParts) === 1) {
          $userInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 2));
        } else {
          $userInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
        }

        $avatarColors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'];
        $colorIndex = crc32($currentUserName) % count($avatarColors);
        $avatarColor = $avatarColors[$colorIndex];

        $photo = new Photo('Person', $currentUser->getPersonId());
        $hasUploadedPhoto = $photo->hasUploadedPhoto();
        $personId = $currentUser->getPersonId();
        $avatarApiUrl = SystemURLs::getRootPath() . '/api/person/' . $personId . '/photo';
        ?>
        <div class="nav-item dropdown">
            <a href="#" class="nav-link d-flex align-items-center gap-2 lh-1 text-reset ps-2"
             data-bs-toggle="dropdown" aria-label="<?= gettext('Open user menu') ?>">
            <?php if ($hasUploadedPhoto) { ?>
              <img src="<?= InputUtils::escapeAttribute($avatarApiUrl) ?>" class="avatar photo-small rounded-circle" alt="<?= InputUtils::escapeAttribute($currentUserName) ?>">
            <?php } else { ?>
              <span class="avatar avatar-sm text-white flex-shrink-0" style="background-color: <?= InputUtils::escapeAttribute($avatarColor) ?>;">
                <span class="avatar-title"><?= InputUtils::escapeHTML($userInitials) ?></span>
              </span>
            <?php } ?>
            <div class="d-none d-xl-block ps-2">
              <div><?= InputUtils::escapeHTML($currentUserName) ?></div>
              <div class="mt-1 small text-secondary"><?= InputUtils::escapeHTML($userRole) ?></div>
            </div>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <?php if (!$setupMode): ?>
            <a href="<?= InputUtils::escapeAttribute(Person::getViewURIForId($currentUser->getPersonId())) ?>"
               class="dropdown-item">
              <i class="fa-solid fa-user me-2"></i><?= gettext("Profile") ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/account/password" class="dropdown-item">
              <i class="fa-solid fa-key me-2"></i><?= gettext('Change Password') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/account"
               class="dropdown-item">
              <i class="fa-solid fa-cog me-2"></i><?= gettext('Change Settings') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SystemURLs::getRootPath() ?>/portal/" class="dropdown-item">
              <i class="fa-solid fa-church me-2"></i><?= gettext('Member Portal') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SystemURLs::getRootPath() ?>/account/2fa" class="dropdown-item">
              <i class="fa-solid fa-shield me-2"></i><?= gettext("Manage Two-Factor Authentication") ?>
            </a>
            <div class="dropdown-divider"></div>
            <?php endif; ?>
            <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="dropdown-item">
              <i class="fa-solid fa-arrow-right-from-bracket me-2"></i><?= gettext('Sign out') ?>
            </a>
          </div>
        </div>

      </div><!-- /.navbar-nav.order-md-last -->

      <?php if (!$setupMode): ?>
      <!-- Search -->
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="global-search">
          <div class="input-icon">
            <span class="input-icon-addon">
              <i class="fa-solid fa-search"></i>
            </span>
            <input type="search" id="globalSearch" class="form-control"
                   placeholder="<?= gettext('Search people, families, groups…') ?>"
                   autocomplete="off" spellcheck="false">
            <span class="input-icon-addon">
              <kbd title="<?= gettext('Press ? to focus search') ?>">?</kbd>
            </span>
          </div>
          <div id="globalSearchDropdown" class="dropdown-menu w-100 global-search-dropdown"></div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.container-xl -->
  </header>

  <div class="page-wrapper">
<?php if ($setupMode || ($currentUser->isAdmin() && InstallSetupService::isDemoDataLoaded())): ?>
    <script src="<?= SystemURLs::assetVersioned('/skin/v2/first-run.min.js') ?>"></script>
<?php endif; ?>
<?php
    // Unified page header defaults (backward-compatible)
    $sPageSubtitle = $sPageSubtitle ?? '';
    $aBreadcrumbs = $aBreadcrumbs ?? [];
    $sPageHeaderButtons = $sPageHeaderButtons ?? '';
    ?>
    <div class="page-header">
      <div class="container-xl">
        <?php if (!empty($aBreadcrumbs) || !empty($sPageHeaderButtons)) : ?>
        <div class="row g-2 align-items-center mb-1 d-print-none">
          <div class="col">
            <?php if (!empty($aBreadcrumbs)) : ?>
            <ol class="breadcrumb mb-0" aria-label="breadcrumbs">
              <li class="breadcrumb-item">
                <a href="<?= InputUtils::escapeAttribute($homeUrl) ?>"><?= gettext('Home') ?></a>
              </li>
              <?php foreach ($aBreadcrumbs as $crumb) : ?>
                <?php if (!empty($crumb['active'])) : ?>
              <li class="breadcrumb-item active" aria-current="page"><?= InputUtils::escapeHTML($crumb['label']) ?></li>
                <?php else : ?>
              <li class="breadcrumb-item"><a href="<?= InputUtils::escapeAttribute($crumb['url']) ?>"><?= InputUtils::escapeHTML($crumb['label']) ?></a></li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ol>
            <?php endif; ?>
          </div>
          <?php if (!empty($sPageHeaderButtons)) : ?>
          <div class="col-auto ms-auto">
            <?= $sPageHeaderButtons ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="row g-2 align-items-center">
          <div class="col">
            <h2 class="page-title"><?= InputUtils::escapeHTML($sPageTitle) ?></h2>
            <?php if (!empty($sPageSubtitle)) : ?>
            <div class="text-body-secondary mt-1"><?= InputUtils::escapeHTML($sPageSubtitle) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div><!-- /.page-header -->
    <div class="page-body" id="main-content" tabindex="-1">
      <div class="container-xl">
<?php
require __DIR__ . '/PageTelemetry.php';

if (TelemetryService::isEnabled()):
?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/telemetry.min.js') ?>" defer></script>
<?php endif; ?>
