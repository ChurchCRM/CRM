<?php
/**
 * Common error page partial (4xx / 5xx)
 * Expects variables:
 *  - $code (int)
 *  - $title (string)
 *  - $message (string)
 *  - $returnUrl (string)
 *  - $returnText (string)
 *  - $bStandalone (bool) - true when rendered without Header.php/Footer.php
 *    (e.g. SlimUtils::registerDefaultJsonErrorHandler(), used by apps that
 *    can't safely assume an authenticated Header.php will render, such as
 *    session/index.php). Wraps the partial in a minimal HTML document that
 *    loads the CSS bundle itself, since nothing else will.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\VersionUtils;

$code = $code ?? 500;
$title = $title ?? gettext('Error');
$message = $message ?? gettext('An unexpected error occurred.');
$returnUrl = $returnUrl ?? (SystemURLs::getRootPath() . '/v2/dashboard');
$returnText = $returnText ?? gettext('Return to Dashboard');
// Optional raw HTML block to render after the message (internal use only)
$extraHtml = $extraHtml ?? '';
$bStandalone = $bStandalone ?? false;

// Last-resort fallback if the #reportIssue header link/modal isn't present on
// this page (e.g. this partial rendered without Header.php) — a prefilled
// GitHub issue, not SystemURLs::getSupportURL()'s generic docs site, since a
// crash report belongs on the tracker, not in user documentation. Mirrors the
// pattern already used by src/errors/template.php.
try {
    $sAppVersion = VersionUtils::getInstalledVersion();
} catch (\Throwable $e) {
    $sAppVersion = 'Unknown';
}
$sIssueBody = "**Error:** $message\n"
    . '**Page:** ' . ($_SERVER['REQUEST_URI'] ?? 'Unknown') . "\n"
    . "**ChurchCRM Version:** $sAppVersion\n"
    . '**PHP Version:** ' . phpversion();
$sGithubIssueUrl = 'https://github.com/ChurchCRM/CRM/issues/new'
    . '?title=' . rawurlencode('[Error ' . $code . '] ' . $title)
    . '&body=' . rawurlencode($sIssueBody);

?>
<?php if ($bStandalone) { ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= htmlspecialchars($title) ?></title>
  <link rel="icon" href="<?= SystemURLs::getRootPath() ?>/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/churchcrm.min.css') ?>">
</head>
<body>
<?php } ?>

<div class="page-body">
  <div class="container-xl">
    <div class="row justify-content-center">
      <div class="col-lg-6 col-md-8">
        <div class="card shadow-sm mt-5">
          <div class="card-body text-center py-5">
            <div class="mb-3">
              <span class="h1 fw-bold text-<?= ($code >= 500) ? 'danger' : 'warning' ?>"><?= htmlspecialchars($code) ?></span>
            </div>
            <div class="mb-3">
              <i class="fa-solid fa-circle-exclamation" style="font-size:3rem;"></i>
            </div>
            <h3 class="mb-2"><?= htmlspecialchars($title) ?></h3>
            <p class="text-body-secondary mb-4"><?= htmlspecialchars($message) ?></p>
            <?php if (!empty($extraHtml)) { echo $extraHtml; } ?>

                      <div class="d-flex justify-content-center gap-2">
                        <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-primary btn-lg">
                          <?= htmlspecialchars($returnText) ?>
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-lg" id="errorReportBtn">
                          <?= gettext('Report an issue') ?>
                        </button>
                      </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
  // Wire the Report button to open the issue modal if available and set page URL
  document.getElementById('errorReportBtn')?.addEventListener('click', function (ev) {
    ev.preventDefault();
    // Ensure the pageName hidden input is populated with the current full URL so server-side issue body includes it
    try {
      var pageInput = document.querySelector('input[name="pageName"]');
      if (pageInput) {
        pageInput.value = window.location.pathname + window.location.search;
      }
    } catch (e) {
      console.warn('Could not set pageName input for IssueReporter', e);
    }

    // Preferred: trigger the header `#reportIssue` link which already has data-bs-toggle/data-bs-target
    var trigger = document.getElementById('reportIssue');
    if (trigger) {
      try {
        trigger.click();
        setTimeout(function () {
          var ta = document.getElementById('issueDescription');
          if (ta) ta.focus();
        }, 100);
        return;
      } catch (e) {
        console.warn('Triggering #reportIssue failed', e);
      }
    }

    // Fallback: try bootstrap modal API if available on window
    var modal = document.getElementById('IssueReportModal');
    if (modal && window.bootstrap && window.bootstrap.Modal) {
      try {
        var bsModal = window.bootstrap.Modal.getOrCreateInstance(modal);
        bsModal.show();
        setTimeout(function () {
          var ta = document.getElementById('issueDescription');
          if (ta) ta.focus();
        }, 100);
        return;
      } catch (e) {
        console.warn('bootstrap modal show failed', e);
      }
    }

    // Final fallback: neither the header's issue-reporter trigger nor its
    // modal are present on this page — open a prefilled GitHub issue instead.
    window.open(<?= InputUtils::jsonEncodeForScript($sGithubIssueUrl) ?>, '_blank');
  });
</script>
<?php if ($bStandalone) { ?>
</body>
</html>
<?php } ?>
