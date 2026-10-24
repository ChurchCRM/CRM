<?php

use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

/*
 * The Login as Person history card (member sign-in design §6.5), on the person's
 * page and on the administrator's user page. Administrators only.
 *
 * Variables: $loginAsPersonHistory (LoginAsPersonHistoryService rows).
 */
?>
<div class="card mb-3" id="login-as-person-history">
    <div class="card-header">
        <h3 class="card-title m-0"><i class="fa-solid fa-user-secret me-1"></i> <?= gettext('Login as Person history') ?></h3>
    </div>
    <?php if ($loginAsPersonHistory === []) : ?>
        <div class="card-body text-body-secondary"><?= gettext('No Login as Person sessions yet.') ?></div>
    <?php else : ?>
        <div class="list-group list-group-flush">
            <?php foreach ($loginAsPersonHistory as $loginAsPersonSession) : ?>
                <div class="list-group-item" data-cy="login-as-person-session" data-session-id="<?= (int) $loginAsPersonSession['id'] ?>">
                    <div class="fw-medium">
                        <?= InputUtils::escapeHTML(sprintf(
                            /* Translators: %1$s is the administrator, %2$s the person they logged in as. */
                            gettext('%1$s as %2$s'),
                            $loginAsPersonSession['actorName'],
                            $loginAsPersonSession['personName']
                        )) ?>
                    </div>
                    <div class="small text-secondary">
                        <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($loginAsPersonSession['started'], true)) ?>
                        <?php if ($loginAsPersonSession['ended'] !== null) : ?>
                            &ndash; <?= InputUtils::escapeHTML(DateTimeUtils::formatDate($loginAsPersonSession['ended'], true)) ?>
                            <span class="badge bg-secondary-lt text-secondary ms-1" data-cy="login-as-person-end-reason"><?= InputUtils::escapeHTML((string) $loginAsPersonSession['endReason']) ?></span>
                        <?php else : ?>
                            <span class="badge bg-warning-lt text-warning ms-1" data-cy="login-as-person-end-reason"><?= gettext('Not ended') ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($loginAsPersonSession['actions'] === []) : ?>
                        <div class="small text-secondary mt-1"><?= gettext('No changes made.') ?></div>
                    <?php else : ?>
                        <ul class="list-unstyled small mt-2 mb-0">
                            <?php foreach ($loginAsPersonSession['actions'] as $loginAsPersonAction) :
                                $loginAsPersonStatus = $loginAsPersonAction['status'];
                                $loginAsPersonStatusClass = $loginAsPersonStatus === null ? 'bg-secondary-lt text-secondary'
                                    : ($loginAsPersonStatus < 300 ? 'bg-success-lt text-success'
                                    : ($loginAsPersonStatus < 400 ? 'bg-azure-lt text-azure' : 'bg-danger-lt text-danger'));
                            ?>
                                <li class="d-flex flex-wrap align-items-baseline gap-2 mb-1" data-cy="login-as-person-action">
                                    <code class="text-break"><?= InputUtils::escapeHTML($loginAsPersonAction['method'] . ' ' . $loginAsPersonAction['path']) ?></code>
                                    <span class="badge <?= $loginAsPersonStatusClass ?>"><?= $loginAsPersonStatus === null ? '&ndash;' : (int) $loginAsPersonStatus ?></span>
                                    <span class="text-secondary"><?= InputUtils::escapeHTML(DateTimeUtils::formatDate($loginAsPersonAction['time'], true)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
