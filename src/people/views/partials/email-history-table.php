<?php

use ChurchCRM\Service\EmailLogService;
use ChurchCRM\Utils\InputUtils;

/**
 * Email history table partial.
 *
 * Expects:
 *   $emailHistoryRows      array of rows from EmailLogService::toArray()
 *   $emailHistoryShowTo    bool, show the recipient address column (family / admin views)
 * Each subject is a link that opens #email-history-modal (see email-history-modal.php).
 */
$emailHistoryRows = $emailHistoryRows ?? [];
$emailHistoryShowTo = $emailHistoryShowTo ?? false;
$emailHistoryShowError = $emailHistoryShowError ?? false;
$emailHistoryLinkPerson = $emailHistoryLinkPerson ?? false;
?>
<?php if (empty($emailHistoryRows)) : ?>
    <div class="text-center text-body-secondary py-4" data-email-history-empty>
        <i class="fa-solid fa-envelope-open fs-3 d-block mb-2"></i>
        <?= gettext('No emails have been sent to this record yet.') ?>
    </div>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-vcenter card-table table-hover mb-0" data-email-history-table>
            <thead>
                <tr>
                    <th class="w-1 text-nowrap"><?= gettext('Date') ?></th>
                    <th class="w-1 text-nowrap"><?= gettext('Type') ?></th>
                    <th><?= gettext('Subject') ?></th>
                    <?php if ($emailHistoryShowTo) : ?><th><?= gettext('To') ?></th><?php endif; ?>
                    <th class="w-1 text-nowrap"><?= gettext('Status') ?></th>
                    <?php if ($emailHistoryShowError) : ?><th><?= gettext('Server error') ?></th><?php endif; ?>
                    <th class="w-1 text-nowrap d-none d-md-table-cell"><?= gettext('Sent by') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($emailHistoryRows as $row) :
                $status = (string) ($row['status'] ?? '');
                $statusClass = match ($status) {
                    'sent'              => 'bg-success-lt text-success',
                    'failed'            => 'bg-danger-lt text-danger',
                    'queued', 'sending' => 'bg-azure-lt text-azure',
                    default             => 'bg-secondary-lt text-secondary',
                };
                $statusLabel = EmailLogService::statusLabel($status);
                ?>
                <tr data-email-log-row="<?= (int) $row['id'] ?>" data-email-status="<?= InputUtils::escapeAttribute($status) ?>">
                    <td class="text-nowrap text-body-secondary"><?= InputUtils::escapeHTML(substr((string) $row['dateSent'], 0, 16)) ?></td>
                    <td class="text-nowrap"><span class="badge bg-blue-lt text-blue"><?= InputUtils::escapeHTML($row['kindLabel']) ?></span></td>
                    <td>
                        <a href="#" class="email-history-open fw-semibold"
                           data-email-log-id="<?= (int) $row['id'] ?>"
                           title="<?= gettext('View this email') ?>"><?= InputUtils::escapeHTML($row['subject'] !== '' ? $row['subject'] : gettext('(no subject)')) ?></a>
                    </td>
                    <?php if ($emailHistoryShowTo) : ?>
                        <td class="text-body-secondary small">
                            <?php if ($emailHistoryLinkPerson && !empty($row['personId'])) : ?>
                                <a href="<?= SystemURLs::getRootPath() ?>/people/view/<?= (int) $row['personId'] ?>"><?= InputUtils::escapeHTML($row['address']) ?></a>
                            <?php else : ?>
                                <?= InputUtils::escapeHTML($row['address']) ?>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td class="text-nowrap">
                        <span class="badge <?= $statusClass ?>" <?php if (!empty($row['error'])) : ?>title="<?= InputUtils::escapeAttribute($row['error']) ?>"<?php endif; ?>><?= InputUtils::escapeHTML($statusLabel) ?></span>
                    </td>
                    <?php if ($emailHistoryShowError) : ?>
                        <td class="text-danger small" data-email-failure-error>
                            <?= InputUtils::escapeHTML(!empty($row['error']) ? $row['error'] : gettext('No server error recorded')) ?>
                        </td>
                    <?php endif; ?>
                    <td class="text-nowrap text-body-secondary d-none d-md-table-cell"><?= $row['sentBy'] !== null ? InputUtils::escapeHTML($row['sentBy']) : '<span class="text-secondary">' . gettext('Automatic') . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
