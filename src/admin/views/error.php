<?php

use ChurchCRM\dto\SystemURLs;

include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="page-body py-5">
  <div class="container-xl">
    <div class="row justify-content-center">
      <div class="col-lg-6 col-md-8">
        <div class="card shadow-sm">
          <div class="card-body text-center py-5">
            <div class="mb-3">
              <span class="h1 fw-bold text-danger">500</span>
            </div>
            <div class="mb-3">
              <i class="ti ti-alert-circle" style="font-size:3rem;"></i>
            </div>
            <h3 class="mb-2"><?= gettext('An Error Occurred') ?></h3>
            <p class="text-muted mb-4"><?= gettext('An unexpected error occurred while processing your request. Please contact your administrator for assistance.') ?></p>

            <?php if (!empty($errorDetails) && ($errorDetails['displayErrorDetails'] ?? false)): ?>
            <div class="mb-4">
              <details class="card card-outline border-secondary">
                <summary class="card-header cursor-pointer">
                  <i class="ti ti-code"></i>
                  <?= gettext('Technical Details') ?> (Development Mode)
                </summary>
                <div class="card-body">
                  <pre class="mb-0"><code><?= htmlspecialchars($errorDetails['message'] ?? '') ?></code></pre>
                </div>
              </details>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-center gap-2 flex-wrap">
              <a href="<?= SystemURLs::getRootPath() ?>/admin/" class="btn btn-primary btn-lg">
                <?= gettext('Back to Admin Dashboard') ?>
              </a>
              <a href="<?= SystemURLs::getRootPath() ?>/home" class="btn btn-outline-secondary btn-lg">
                <?= gettext('Back to Home') ?>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
include SystemURLs::getDocumentRoot() . '/Include/Footer.php';
