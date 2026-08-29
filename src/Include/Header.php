<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Bootstrapper;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\NotificationService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Service\TelemetryService;
use ChurchCRM\Utils\CurrencyFormatter;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\MenuRenderer;

$localeInfo = Bootstrapper::getCurrentLocale();

// Turn ON output buffering
ob_start();

require_once __DIR__ . '/Header-Security.php';

// Initialize plugin system for logged-in users
$pluginsPath = SystemURLs::getDocumentRoot() . '/plugins';
PluginManager::init($pluginsPath);

// Resolve theme attributes from user settings
$_themeUser = AuthenticationManager::getCurrentUser();
$_themeMode = $_themeUser->getThemeMode(); // 'auto' | 'default' | 'dark'
$_themeAttrs = '';
// Explicit dark: stamp data-bs-theme on <html> server-side for FOWT-safe rendering
// without JS. Auto mode is handled by the inline <head> script below.
if ($_themeMode === 'dark') {
    $_themeAttrs .= ' data-bs-theme="dark"';
}
$_themePrimary = $_themeUser->getSettingValue('ui.theme.primary');
if ($_themePrimary !== '') {
    $_themeAttrs .= ' data-bs-theme-primary="' . InputUtils::escapeAttribute($_themePrimary) . '"';
}
// Top level menu index counter
$MenuFirst = 1;
// Currency substrate — data attribute on <html> + CSS custom property via <style> block.
// The symbol is JSON-encoded so any char (including '"' and backslash) produces
// a valid CSS string literal without breaking the declaration.
$_currencyAttrs     = ' data-currency-position="' . InputUtils::escapeAttribute(CurrencyFormatter::position()) . '"';
$_currencySymbolCss = json_encode(CurrencyFormatter::symbol(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html<?= $localeInfo->isRTL() ? ' dir="rtl"' : '' ?><?= $_themeAttrs ?><?= $_currencyAttrs ?>>
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <!-- Theme controller: must run synchronously before page paint to prevent flash-of-wrong-theme. -->
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
    (function () {
      // Ensure window.CRM exists; body script will Object.assign more properties later.
      window.CRM = window.CRM || {};

      var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
      var _listening = false;

      function _applyDark() {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
      }
      function _applyLight() {
        document.documentElement.removeAttribute('data-bs-theme');
      }
      function _onChange(e) {
        if (e.matches) { _applyDark(); } else { _applyLight(); }
      }
      // Delegate to _onChange so both paths share the same dark/light logic.
      // Falls back to light when matchMedia is unavailable (mql === null).
      function _applySystem() {
        _onChange({ matches: mql ? mql.matches : false });
      }

      /**
       * Apply a theme mode and manage the matchMedia listener lifecycle.
       * mode: 'auto' | 'default' | 'dark'
       * Called once on page load (below) and by user.js when the user toggles the setting.
       */
      window.CRM.theme = {
        setMode: function (mode) {
          if (mode === 'auto') {
            _applySystem();
            if (mql && !_listening) {
              mql.addEventListener('change', _onChange);
              _listening = true;
            }
          } else {
            if (_listening) {
              mql.removeEventListener('change', _onChange);
              _listening = false;
            }
            if (mode === 'dark') { _applyDark(); } else { _applyLight(); }
          }
        }
      };

      // Apply the server-resolved theme mode immediately (FOWT prevention).
      window.CRM.theme.setMode(<?= json_encode($_themeMode) ?>);
    }());
  </script>
  <?php require_once __DIR__ . '/Header-HTML-Scripts.php'; ?>
  <?= PluginManager::getPluginHeadContent() ?>
  <style nonce="<?= SystemURLs::getCSPNonce() ?>">:root { --currency-symbol: <?= $_currencySymbolCss ?>; }</style>

</head>

<body class="antialiased">
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
  $currentUser = AuthenticationManager::getCurrentUser();
  $tableSizeSetting = $currentUser->getSetting("ui.table.size");
  if (empty($tableSizeSetting)) {
      $tableSize = 10;
  } else {
      $tableSize = $tableSizeSetting->getValue();
  }
  ?>
  <script nonce="<?= SystemURLs::getCSPNonce() ?>">
      // Initialize window.CRM if not already created by webpack bundles
      if (!window.CRM) {
          window.CRM = {};
      }

      // Extend window.CRM with server-side configuration (preserving existing properties like notify)
      Object.assign(window.CRM, {
          root:"<?= SystemURLs::getRootPath() ?>",
          fullURL:"<?= SystemURLs::getURL() ?>",
          lang:"<?= $localeInfo->getLanguageCode() ?>",
          isRTL:<?= $localeInfo->isRTL() ? 'true' : 'false' ?>,
          userId:"<?= AuthenticationManager::getCurrentUser()->getId() ?>",
          userName:<?= json_encode(AuthenticationManager::getCurrentUser()->getPerson()?->getFullName() ?? '') ?>,
          version:"<?= $_SESSION['sSoftwareInstalledVersion'] ?? 'unknown' ?>",
          systemLocale:"<?= $localeInfo->getSystemLocale() ?>",
          locale:"<?= $localeInfo->getLocale() ?>",
          shortLocale:"<?= $localeInfo->getShortLocale() ?>",
          timeZone:<?= SystemConfig::getValueForJs('sTimeZone') ?>,
          maxUploadSize:"<?= SystemService::getMaxUploadFileSize(true) ?>",
          maxUploadSizeBytes:"<?= SystemService::getMaxUploadFileSize(false) ?>",
          datePickerformat:<?= SystemConfig::getValueForJs('sDatePickerPlaceHolder') ?>,
          churchWebSite:<?= SystemConfig::getValueForJs('sChurchWebSite') ?>,
          systemConfigs: {
            sDateTimeFormat:<?= DateTimeUtils::getDateTimeFormatForJs() ?>,
          },
          comm: {
            smtpConfigured: <?= json_encode(SystemConfig::hasValidMailServerSettings()) ?>,
            vonageEnabled: <?= json_encode(PluginManager::getPlugin('vonage')?->isConfigured() ?? false) ?>,
            // Church default "to" address (sToEmailAddress); exposed only to email-enabled
            // users. The email composer offers it as a removable default recipient.
            defaultEmailToAddress: <?= AuthenticationManager::getCurrentUser()->isEmailEnabled() ? SystemConfig::getValueForJs('sToEmailAddress') : json_encode('') ?>,
          },
          // Plugin configs from active plugins (via getClientConfig())
          plugins: <?= json_encode(PluginManager::getPluginsClientConfig(), JSON_FORCE_OBJECT) ?>,
          // Legacy: keep bEnableGravatarPhotos for backward compatibility with existing JS
          bEnableGravatarPhotos: <?= json_encode(PluginManager::getPluginsClientConfig()['gravatar']['enabled'] ?? false) ?>,
          plugin: {
              dataTable : {
"pageLength": <?= $tableSize ?>,
"lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100,"All"]],
"language": {
"url":"<?= SystemURLs::getRootPath() ?>/locale/vendor/datatables/<?= $localeInfo->getDataTables() ?>.json"
                  },
                  responsive: true,
                  layout: {
                      topStart: 'search',
                      topEnd: 'buttons',
                      bottomStart: 'pageLength',
                      bottomEnd: ['info', 'paging']
                  },
                  buttons: [
                      {
                          extend: 'csv',
                          text: '<i class="fa-solid fa-table"></i>',
                          titleAttr: 'Export CSV',
                          exportOptions: {
                              columns: ':not(.no-export)'
                          }
                      },
                      {
                          extend: 'print',
                          text: '<i class="fa-solid fa-print"></i>',
                          titleAttr: 'Print',
                          exportOptions: {
                              columns: ':not(.no-export)'
                          }
                      }
                  ]
              }
          },
          permissions: {
              addRecords: <?= json_encode($currentUser->isAddRecordsEnabled()) ?>,
              editRecords: <?= json_encode($currentUser->isEditRecordsEnabled()) ?>,
          },
          PageName:<?= json_encode($_SERVER['REQUEST_URI'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>,
          telemetry: <?= json_encode([
              'level'      => TelemetryService::getLevel(),
              'key'        => TelemetryService::isEnabled() ? TelemetryService::POSTHOG_KEY : '',
              'endpoint'   => TelemetryService::POSTHOG_ENDPOINT,
              'distinctID' => SystemConfig::getValue('sSystemID'),
          ]) ?>,
          currency: <?= json_encode(CurrencyFormatter::toArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
      });
      // Attach format() to window.CRM.currency so JS callers (DataTables, Chart.js)
      // can render localised money via window.CRM.currency.format(amount [, decimals]).
      window.CRM.currency.format = function (amount, decimals) {
          if (decimals === undefined) decimals = 2;
          var val = parseFloat(amount);
          if (isNaN(val)) return '';          // match PHP empty-string fallback for non-numeric input
          var parts = val.toFixed(decimals).split('.');
          parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousand);
          var formatted = parts[0] + (decimals > 0 ? this.decimal + parts[1] : '');
          var sym = this.symbol.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
          return this.position === 'after'
              ? formatted + '\u00A0' + sym
              : sym + '\u00A0' + formatted;
      };
      // Initialize moment locale if available
      if (typeof moment !== 'undefined' && window.CRM.shortLocale) {
          moment.locale(window.CRM.shortLocale);
      }
  </script>
  <script src="<?= SystemURLs::assetVersioned('/skin/js/CRMJSOM.js') ?>"></script>
  <script src="<?= SystemURLs::assetVersioned('/skin/js/CommunicationUtils.js') ?>"></script>

  <!-- ============================================================ -->
  <!-- Sidebar (Tabler vertical navbar)                              -->
  <!-- ============================================================ -->
  <aside class="navbar navbar-vertical navbar-expand-xl d-print-none" id="sidebar">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#sidebar-menu"
              aria-controls="sidebar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle navigation') ?>">
        <span class="navbar-toggler-icon"></span>
      </button>
      <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard" class="navbar-brand py-2">
        <img src="<?= SystemURLs::getRootPath() ?>/Images/CRM_50x50.png"
             alt="<?= InputUtils::escapeAttribute(ChurchMetaData::getChurchName() ?: 'ChurchCRM') ?>"
             class="navbar-brand-image rounded"
             style="height: 42px; width: auto;">
        <span class="navbar-brand-text ps-2 fs-4 fw-bold">
          <?= InputUtils::escapeHTML(ChurchMetaData::getChurchName() ?: 'ChurchCRM') ?>
        </span>
      </a>
      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-xl-3">
          <?php MenuRenderer::renderMenu(); ?>
        </ul>
      </div>
    </div>
  </aside>

  <!-- ============================================================ -->
  <!-- Page wrapper                                                  -->
  <!-- ============================================================ -->
  <div class="page-wrapper">

  <!-- ============================================================ -->
  <!-- Topbar                                                        -->
  <!-- ============================================================ -->
  <header class="navbar navbar-expand-md d-print-none sticky-top">
    <div class="container-xl">

      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#navbar-menu"
              aria-controls="navbar-menu" aria-expanded="false"
              aria-label="<?= gettext('Toggle search') ?>">
        <i class="fa-solid fa-search"></i>
      </button>

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
            <?php if (AuthenticationManager::getCurrentUser()->isAdmin()) { ?>
            <a href="<?= SystemURLs::getRootPath() ?>/admin/system/upgrade" class="dropdown-item"
               title="<?= gettext('New Release') ?>">
              <i class="fa-solid fa-party-horn me-2"></i><?= gettext('New Release') ?>
              <?php if ($updateVersion) { ?>
                <span id="upgradeToVersion" class="ms-1">
                  <?= $updateVersion->MAJOR ?>.<?= $updateVersion->MINOR ?>.<?= $updateVersion->PATCH ?>
                </span>
              <?php } ?>
            </a>
            <?php } ?>
            <a href="https://github.com/ChurchCRM/CRM/releases/latest" target="_blank"
               class="dropdown-item" title="<?= gettext('Release Notes') ?>">
              <i class="fa-solid fa-notebook me-2"></i><?= gettext('Release Notes') ?>
            </a>
          </div>
        </div>

        <!-- Locale: flag links directly to the localization tab on the profile page -->
        <?php
        $flagCode    = $localeInfo->getCountryFlagCode();
        $nativeName  = $localeInfo->getNativeName();
        $englishName = $localeInfo->getName();
        $hasNative   = $nativeName !== '' && $nativeName !== $englishName;
        $localeUrl   = SystemURLs::getRootPath() . '/v2/user/' . AuthenticationManager::getCurrentUser()->getId() . '#tab-localization';
        ?>
        <div class="nav-item ms-1">
          <a class="nav-link px-0" href="<?= $localeUrl ?>"
             title="<?= InputUtils::escapeAttribute($hasNative ? $nativeName . ' — ' . $englishName : $englishName) ?>">
            <i class="fi fi-<?= $flagCode ?> fi-squared"></i>
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
            <span id="cart-dropdown-menu"></span>
          </div>
        </div>

        <!-- Support -->
        <div class="nav-item dropdown ms-1">
          <a class="nav-link px-0" data-bs-toggle="dropdown" href="#" id="supportMenu">
            <i class="fa-solid fa-headphones"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <a href="<?= SystemURLs::getSupportURL() ?>" target="help" class="dropdown-item"
               title="<?= gettext('Documentation') ?>">
              <i class="fa-solid fa-book me-2"></i><?= gettext('Documentation') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="#" id="reportIssue" class="dropdown-item"
               data-bs-toggle="modal" data-bs-target="#IssueReportModal"
               title="<?= gettext('Report an issue') ?>">
              <i class="fa-solid fa-bug me-2"></i><?= gettext('Report an issue') ?>
            </a>
            <a href="https://discord.gg/tuWyFzj3Nj" target="_blank" class="dropdown-item"
               title="<?= gettext('Discord Chat') ?>">
              <i class="fa-brands fa-discord me-2"></i><?= gettext('Discord Chat') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="https://docs.churchcrm.io/contributing" target="_blank" class="dropdown-item"
               title="<?= gettext('Contributing') ?>">
              <i class="fa-brands fa-github me-2"></i><?= gettext('Documentation') ?>
            </a>
          </div>
        </div>

        <!-- User -->
        <?php
        $currentUser     = AuthenticationManager::getCurrentUser();
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
              <img src="<?= $avatarApiUrl ?>" class="avatar photo-small rounded-circle" alt="<?= htmlspecialchars($currentUserName) ?>">
            <?php } else { ?>
              <span class="avatar avatar-sm" style="background-color: <?= $avatarColor ?>; color: #fff; flex-shrink: 0;">
                <span class="avatar-title"><?= htmlspecialchars($userInitials) ?></span>
              </span>
            <?php } ?>
            <div class="d-none d-xl-block ps-2">
              <div><?= htmlspecialchars($currentUserName) ?></div>
              <div class="mt-1 small text-secondary"><?= $userRole ?></div>
            </div>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <a href="<?= Person::getViewURIForId($currentUser->getPersonId()) ?>"
               class="dropdown-item">
              <i class="fa-solid fa-user me-2"></i><?= gettext("Profile") ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/changepassword" class="dropdown-item">
              <i class="fa-solid fa-key me-2"></i><?= gettext('Change Password') ?>
            </a>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/<?= $currentUser->getPersonId() ?>"
               class="dropdown-item">
              <i class="fa-solid fa-cog me-2"></i><?= gettext('Change Settings') ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SystemURLs::getRootPath() ?>/v2/user/current/manage2fa" class="dropdown-item">
              <i class="fa-solid fa-shield me-2"></i><?= gettext("Manage Two-Factor Authentication") ?>
            </a>
            <div class="dropdown-divider"></div>
            <a href="<?= SystemURLs::getRootPath() ?>/session/end" class="dropdown-item">
              <i class="fa-solid fa-arrow-right-from-bracket me-2"></i><?= gettext('Sign out') ?>
            </a>
          </div>
        </div>

      </div><!-- /.navbar-nav.order-md-last -->

      <!-- Search -->
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div style="position: relative; width: min(480px, 100%);">
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
          <div id="globalSearchDropdown" class="dropdown-menu w-100"
               style="top: calc(100% + 2px); left: 0; position: absolute;"></div>
        </div>
      </div>

    </div><!-- /.container-xl -->
  </header>

<?php
    // Unified page header defaults (backward-compatible)
    $sPageSubtitle = $sPageSubtitle ?? '';
    $aBreadcrumbs = $aBreadcrumbs ?? [];
    $sPageHeaderButtons = $sPageHeaderButtons ?? '';
    $sSettingsCollapseId = $sSettingsCollapseId ?? '';
    ?>
    <div class="page-header">
      <div class="container-xl">
        <?php if (!empty($aBreadcrumbs) || !empty($sPageHeaderButtons)) : ?>
        <div class="row g-2 align-items-center mb-1 d-print-none">
          <div class="col">
            <?php if (!empty($aBreadcrumbs)) : ?>
            <ol class="breadcrumb mb-0" aria-label="breadcrumbs">
              <li class="breadcrumb-item">
                <a href="<?= SystemURLs::getRootPath() ?>/v2/dashboard"><?= gettext('Home') ?></a>
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
    <?php if (!empty($sSettingsCollapseId)) : ?>
    <div class="container-xl">
      <div class="collapse mb-3" id="<?= $sSettingsCollapseId ?>"></div>
    </div>
    <?php endif; ?>
    <div class="page-body">
      <div class="container-xl">
<?php
// Hydrate registry from session-cached remote notifications (no HTTP calls)
NotificationService::loadSessionNotifications();

// Render all active notifications as dismissible alerts
foreach (NotificationService::getNotifications() as $notification) {
?>
      <div class="alert alert-<?= InputUtils::escapeHTML($notification->getType()) ?> alert-dismissible"
           role="alert"
           <?= $notification->getId() ? 'data-notification-id="' . InputUtils::escapeAttribute($notification->getId()) . '"' : '' ?>
           <?= $notification->getDismissSettingKey() ? 'data-dismiss-key="' . InputUtils::escapeAttribute($notification->getDismissSettingKey()) . '"' : '' ?>>
        <div class="d-flex">
          <div><i class="fa-solid fa-<?= InputUtils::escapeHTML($notification->getIcon()) ?> me-2"></i></div>
          <div>
            <strong><?= InputUtils::escapeHTML($notification->getTitle()) ?></strong>
            <?php if ($notification->getMessage()): ?>
              <div class="text-secondary"><?= InputUtils::escapeHTML($notification->getMessage()) ?></div>
            <?php endif; ?>
            <?php if ($notification->getUrl()): ?>
              <a href="<?= InputUtils::escapeAttribute($notification->getUrl()) ?>" class="alert-link">
                <?= gettext('Learn more') ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($notification->getDismissSettingKey()): ?>
          <button type="button" class="btn-close js-dismiss-notification" data-bs-dismiss="alert" aria-label="<?= gettext('Dismiss') ?>"></button>
        <?php endif; ?>
      </div>
<?php } ?>
<?php
// Server-side page view telemetry for legacy (non-API) pages.
// Strip query string so no record IDs reach PostHog.
$_telemetryRoute = explode('?', $_SERVER['PHP_SELF'] ?? 'unknown', 2)[0];
TelemetryService::capturePageView($_telemetryRoute);

if (TelemetryService::isEnabled()):
?>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/telemetry.min.js') ?>" defer></script>
<?php endif; ?>
