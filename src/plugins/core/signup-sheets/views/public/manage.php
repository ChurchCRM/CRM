<?php

/**
 * Signup Sheets — a volunteer's own signup, reached by their personal link.
 *
 * @var array<string, mixed> $claim
 * @var bool                 $cancelled
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/HeaderNotLoggedIn.php';

$cancelAction = SystemURLs::getRootPath()
    . '/external/signup-sheets/manage/'
    . rawurlencode((string) $claim['sgc_manage_token'])
    . '/cancel';
$sheetUrl = empty($claim['sheet_token'])
    ? null
    : SystemURLs::getRootPath() . '/external/signup-sheets/' . rawurlencode((string) $claim['sheet_token']);
?>

<div class="container py-5 signup-claim-manage">
    <div class="card">
        <div class="card-body">
            <h1 class="h4 mb-3"><?= InputUtils::escapeHTML($claim['sheet_title']) ?></h1>

            <?php if ($cancelled) : ?>
                <div class="alert alert-success mb-3">
                    <i class="fa-solid fa-circle-check fa-fw"></i>
                    <?= gettext('Your signup has been cancelled. Thank you for letting us know.') ?>
                </div>
                <p class="text-body-secondary">
                    <?= sprintf(
                        gettext('You were signed up for: %s'),
                        InputUtils::escapeHTML($claim['slot_title'])
                    ) ?>
                </p>
                <?php if ($sheetUrl !== null) : ?>
                    <a class="btn btn-outline-primary" href="<?= InputUtils::escapeAttribute($sheetUrl) ?>">
                        <?= gettext('Back to the signup sheet') ?>
                    </a>
                <?php endif; ?>
            <?php else : ?>
                <dl class="row mb-4">
                    <dt class="col-sm-4"><?= gettext('You signed up for') ?></dt>
                    <dd class="col-sm-8"><?= InputUtils::escapeHTML($claim['slot_title']) ?></dd>

                    <dt class="col-sm-4"><?= gettext('Name') ?></dt>
                    <dd class="col-sm-8"><?= InputUtils::escapeHTML($claim['sgc_name']) ?></dd>

                    <?php if ((int) $claim['sgc_quantity'] > 1) : ?>
                        <dt class="col-sm-4"><?= gettext('How many') ?></dt>
                        <dd class="col-sm-8"><?= (int) $claim['sgc_quantity'] ?></dd>
                    <?php endif; ?>

                    <?php if (!empty($claim['sgc_comment'])) : ?>
                        <dt class="col-sm-4"><?= gettext('Your note') ?></dt>
                        <dd class="col-sm-8"><?= InputUtils::escapeHTML($claim['sgc_comment']) ?></dd>
                    <?php endif; ?>
                </dl>

                <form method="post" action="<?= InputUtils::escapeAttribute($cancelAction) ?>">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa-solid fa-xmark fa-fw"></i> <?= gettext('Cancel my signup') ?>
                        </button>
                        <?php if ($sheetUrl !== null) : ?>
                            <a class="btn btn-outline-secondary" href="<?= InputUtils::escapeAttribute($sheetUrl) ?>">
                                <?= gettext('View the whole sheet') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/FooterNotLoggedIn.php'; ?>
