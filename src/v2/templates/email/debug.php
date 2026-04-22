<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<?php if ($configError !== null): ?>
    <!-- Can't even attempt the send — tell the admin what to fix and send
         them to the page where the missing setting actually lives. -->
    <div class="card border-warning mb-3">
        <div class="card-status-top bg-warning"></div>
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    <h4 class="mb-1"><i class="fa fa-triangle-exclamation text-warning me-2"></i><?= gettext('Cannot send test email') ?></h4>
                    <p class="mb-2"><?= InputUtils::escapeHTML($configError) ?></p>
                </div>
                <a href="<?= InputUtils::escapeAttribute($configErrorFixUrl ?? $emailDashboardUrl) ?>" class="btn btn-warning ms-3">
                    <i class="fa fa-cog me-1"></i><?= gettext('Fix Settings') ?>
                </a>
            </div>
        </div>
    </div>
<?php elseif ($sendResult['success']): ?>
    <!-- PHPMailer accepted the message for delivery. Tell the admin to go check
         the inbox — a successful SMTP handshake doesn't guarantee inbox delivery. -->
    <div class="card border-success mb-3">
        <div class="card-status-top bg-success"></div>
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    <h4 class="mb-1"><i class="fa fa-circle-check text-success me-2"></i><?= gettext('Test email accepted by SMTP server') ?></h4>
                    <p class="text-muted mb-2">
                        <?= gettext('Now check the recipient inbox to confirm delivery. Look for the subject:') ?>
                        <br>
                        <strong>&ldquo;<?= gettext('ChurchCRM Test Email') ?>&rdquo;</strong>
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="fa fa-info-circle me-1"></i>
                        <?= gettext('If the message doesn\'t arrive within a few minutes, check the spam/junk folder and the SMTP debug log below.') ?>
                    </p>
                </div>
                <a href="<?= SystemURLs::getRootPath() ?>/admin/system/debug/email" class="btn btn-outline-primary ms-3">
                    <i class="fa fa-rotate me-1"></i><?= gettext('Send Again') ?>
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- SMTP send failed. Surface the error prominently. -->
    <div class="card border-danger mb-3">
        <div class="card-status-top bg-danger"></div>
        <div class="card-body">
            <div class="d-flex align-items-start">
                <div class="flex-grow-1">
                    <h4 class="mb-1"><i class="fa fa-circle-xmark text-danger me-2"></i><?= gettext('Test email failed to send') ?></h4>
                    <?php if (!empty($sendResult['error'])): ?>
                        <p class="mb-2"><strong><?= gettext('Error:') ?></strong> <?= InputUtils::escapeHTML((string) $sendResult['error']) ?></p>
                    <?php endif; ?>
                    <p class="text-muted mb-0"><?= gettext('Review the SMTP debug log below for the handshake details, then adjust your settings.') ?></p>
                </div>
                <a href="<?= InputUtils::escapeAttribute($emailDashboardUrl) ?>" class="btn btn-danger ms-3">
                    <i class="fa fa-cog me-1"></i><?= gettext('Update Settings') ?>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- From / To summary -->
    <?php if ($sendResult['attempted']): ?>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fa fa-paper-plane me-2"></i><?= gettext('Delivery') ?></h4>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width: 80px;"><?= gettext('From') ?></td>
                            <td>
                                <?php if (!empty($sendResult['fromName'])): ?>
                                    <strong><?= InputUtils::escapeHTML($sendResult['fromName']) ?></strong><br>
                                <?php endif; ?>
                                <a href="mailto:<?= InputUtils::escapeAttribute((string) $sendResult['from']) ?>"><?= InputUtils::escapeHTML((string) $sendResult['from']) ?></a>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= gettext('To') ?></td>
                            <td>
                                <a href="mailto:<?= InputUtils::escapeAttribute((string) $sendResult['to']) ?>"><?= InputUtils::escapeHTML((string) $sendResult['to']) ?></a>
                                <small class="text-muted d-block">
                                    <?= gettext('Church email — configured on the') ?>
                                    <a href="<?= SystemURLs::getRootPath() ?>/admin/system/church-info"><?= gettext('Church Information') ?></a>
                                    <?= gettext('page.') ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= gettext('Subject') ?></td>
                            <td><code><?= gettext('ChurchCRM Test Email') ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SMTP configuration summary -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h4 class="mb-0"><i class="fa fa-server me-2"></i><?= gettext('SMTP Configuration') ?></h4>
                <a href="<?= InputUtils::escapeAttribute($emailDashboardUrl) ?>" class="btn btn-sm btn-outline-secondary ms-auto">
                    <i class="fa fa-pen me-1"></i><?= gettext('Edit') ?>
                </a>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted" style="width: 140px;"><?= gettext('Host') ?></td>
                            <td><code><?= InputUtils::escapeHTML($smtpSettings['host']) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= gettext('Encryption') ?></td>
                            <td><code><?= InputUtils::escapeHTML($smtpSettings['secure']) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Auto-TLS</td>
                            <td><?= InputUtils::escapeHTML($smtpSettings['autoTLS']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= gettext('Authentication') ?></td>
                            <td>
                                <?= InputUtils::escapeHTML($smtpSettings['auth']) ?>
                                <?php if (!empty($smtpSettings['username'])): ?>
                                    <span class="text-muted ms-2">(<?= gettext('user:') ?> <code><?= InputUtils::escapeHTML((string) $smtpSettings['username']) ?></code>)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted"><?= gettext('Timeout') ?></td>
                            <td><?= InputUtils::escapeHTML($smtpSettings['timeout']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($sendResult['attempted'] && !empty($sendResult['debugLog'])): ?>
<!-- Raw PHPMailer SMTPDebug output — collapsed by default since it's verbose.
     Admins only need it when chasing a real failure. -->
<div class="card mt-3">
    <div class="card-header p-0" id="headingSmtpDebug">
        <button type="button" class="btn btn-link w-100 text-start text-decoration-none text-reset p-3 m-0" data-bs-toggle="collapse" data-bs-target="#collapseSmtpDebug" aria-expanded="<?= $sendResult['success'] ? 'false' : 'true' ?>" aria-controls="collapseSmtpDebug">
            <span class="h4 mb-0 d-flex align-items-center">
                <i class="fa fa-terminal me-2"></i><?= gettext('SMTP Debug Log') ?>
                <small class="text-muted ms-2"><?= gettext('PHPMailer SMTP session') ?></small>
                <i class="fa fa-chevron-<?= $sendResult['success'] ? 'down' : 'up' ?> ms-auto"></i>
            </span>
        </button>
    </div>
    <div id="collapseSmtpDebug" class="collapse <?= $sendResult['success'] ? '' : 'show' ?>" aria-labelledby="headingSmtpDebug">
        <div class="card-body">
            <?php
                // PHPMailer's Debugoutput='html' emits lines joined by <br>. Capture the
                // visible text only (escaped) and re-introduce line breaks via nl2br so
                // SMTP-server content can't inject markup or break out of the page.
                $debugPlain = trim(html_entity_decode(strip_tags((string) $sendResult['debugLog']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            ?>
            <div class="debug-smtp-log"><?= nl2br(InputUtils::escapeHTML($debugPlain), false) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<style nonce="<?= SystemURLs::getCSPNonce() ?>">
.debug-smtp-log {
    max-height: 400px;
    overflow-y: auto;
    background: var(--tblr-bg-surface-secondary, #f1f5f9);
    padding: 0.75rem 1rem;
    border-radius: 6px;
    font-family: var(--tblr-font-monospace, monospace);
    font-size: 0.82rem;
    line-height: 1.5;
}
.debug-smtp-log br { line-height: 0; }
</style>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
