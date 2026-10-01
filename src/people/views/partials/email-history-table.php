<?php

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
                    <th class="w-1 text-nowrap d-none d-md-table-cell"><?= gettext('Sent by') ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($emailHistoryRows as $row) :
                $status = (string) ($row['status'] ?? '');
                $statusClass = match ($status) {
                    'sent'    => 'bg-success-lt text-success',
                    'failed'  => 'bg-danger-lt text-danger',
                    default   => 'bg-secondary-lt text-secondary',
                };
                $statusLabel = match ($status) {
                    'sent'    => gettext('Sent'),
                    'failed'  => gettext('Failed'),
                    'skipped' => gettext('Skipped'),
                    default   => $status,
                };
                ?>
                <tr data-email-log-row="<?= (int) $row['id'] ?>">
                    <td class="text-nowrap text-body-secondary"><?= InputUtils::escapeHTML(substr((string) $row['dateSent'], 0, 16)) ?></td>
                    <td class="text-nowrap"><span class="badge bg-blue-lt text-blue"><?= InputUtils::escapeHTML($row['kindLabel']) ?></span></td>
                    <td>
                        <a href="#" class="email-history-open fw-semibold"
                           data-email-log-id="<?= (int) $row['id'] ?>"
                           title="<?= gettext('View this email') ?>"><?= InputUtils::escapeHTML($row['subject'] !== '' ? $row['subject'] : gettext('(no subject)')) ?></a>
                    </td>
                    <?php if ($emailHistoryShowTo) : ?>
                        <td class="text-body-secondary small"><?= InputUtils::escapeHTML($row['address']) ?></td>
                    <?php endif; ?>
                    <td class="text-nowrap">
                        <span class="badge <?= $statusClass ?>" <?php if (!empty($row['error'])) : ?>title="<?= InputUtils::escapeAttribute($row['error']) ?>"<?php endif; ?>><?= $statusLabel ?></span>
                    </td>
                    <td class="text-nowrap text-body-secondary d-none d-md-table-cell"><?= $row['sentBy'] !== null ? InputUtils::escapeHTML($row['sentBy']) : '<span class="text-secondary">' . gettext('Automatic') . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
