<?php

use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

/*
 * The Login as User history card (member sign-in design §6.5), on the person's
 * page and on the administrator's user page. Administrators only.
 *
 * Variables: $loginAsUserHistory (LoginAsUserHistoryService rows).
 */
?>
<div class="card mb-3" id="login-as-user-history">
    <div class="card-header">
        <h3 class="card-title m-0"><i class="fa-solid fa-user-secret me-1"></i> <?= gettext('Login as User history') ?></h3>
    </div>
    <?php if ($loginAsUserHistory === []) : ?>
        <div class="card-body text-body-secondary"><?= gettext('No Login as User sessions yet.') ?></div>
    <?php else : ?>
        <div class="list-group list-group-flush">
            <?php foreach ($loginAsUserHistory as $loginAsUserSession) : ?>
                <div class="list-group-item" data-cy="login-as-user-session" data-session-id="<?= (int) $loginAsUserSession['id'] ?>">
                    <div class="fw-medium">
                        <?= InputUtils::escapeHTML(sprintf(
                            /* Translators: %1$s is the administrator, %2$s the person they logged in as. */
                            gettext('%1$s as %2$s'),
                            $loginAsUserSession['actorName'],
                            $loginAsUserSession['personName']
                        )) ?>
                    </div>
                    <div class="small text-secondary">
                        <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($loginAsUserSession['started'], true)) ?>
                        <?php if ($loginAsUserSession['ended'] !== null) : ?>
                            &ndash; <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($loginAsUserSession['ended'], true)) ?>
                            <span class="badge bg-secondary-lt text-secondary ms-1" data-cy="login-as-user-end-reason"><?= InputUtils::escapeHTML((string) $loginAsUserSession['endReason']) ?></span>
                        <?php else : ?>
                            <span class="badge bg-warning-lt text-warning ms-1" data-cy="login-as-user-end-reason"><?= gettext('Not ended') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($loginAsUserSession['actions'] === []) : ?>
                        <div class="small text-secondary mt-1"><?= gettext('No changes made.') ?></div>
                    <?php else : ?>
                        <ul class="list-unstyled small mt-2 mb-0">
                            <?php foreach ($loginAsUserSession['actions'] as $loginAsUserAction) :
                                $loginAsUserStatus = $loginAsUserAction['status'];
                                $loginAsUserStatusClass = $loginAsUserStatus === null ? 'bg-secondary-lt text-secondary'
                                    : ($loginAsUserStatus < 300 ? 'bg-success-lt text-success'
                                    : ($loginAsUserStatus < 400 ? 'bg-azure-lt text-azure' : 'bg-danger-lt text-danger'));
                            ?>
                                <li class="d-flex flex-wrap align-items-baseline gap-2 mb-1" data-cy="login-as-user-action">
                                    <code class="text-break"><?= InputUtils::escapeHTML($loginAsUserAction['method'] . ' ' . $loginAsUserAction['path']) ?></code>
                                    <span class="badge <?= $loginAsUserStatusClass ?>"><?= $loginAsUserStatus === null ? '&ndash;' : (int) $loginAsUserStatus ?></span>
                                    <span class="text-secondary"><?= InputUtils::escapeHTML(DateTimeUtils::formatDate($loginAsUserAction['time'], true)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
