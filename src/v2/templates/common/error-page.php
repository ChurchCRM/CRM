<?php
/**
 * Common error page partial (4xx / 5xx)
 * Expects variables:
 *  - $code (int)
 *  - $title (string)
 *  - $message (string)
 *  - $returnUrl (string)
 *  - $returnText (string)
 */

use ChurchCRM\dto\SystemURLs;

$code = $code ?? 500;
$title = $title ?? gettext('Error');
$message = $message ?? gettext('An unexpected error occurred.');
$returnUrl = $returnUrl ?? (SystemURLs::getRootPath() . '/v2/dashboard');
$returnText = $returnText ?? gettext('Return to Dashboard');
// Optional raw HTML block to render after the message (internal use only)
$extraHtml = $extraHtml ?? '';

?>

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
              <i class="ti ti-alert-circle" style="font-size:3rem;"></i>
            </div>
            <h3 class="mb-2"><?= htmlspecialchars($title) ?></h3>
            <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
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

    // Final fallback: open support URL in new tab
    window.open('<?= SystemURLs::getSupportURL() ?>', '_blank');
  });
</script>
