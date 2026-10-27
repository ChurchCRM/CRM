<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * @var array{person_id: int, person_name: string, family_id: int, family_name: string} $donor
 */
$donorRoot = SystemURLs::getRootPath();
?>
<?php if ($donor['person_id'] > 0): ?>
    <a href="<?= $donorRoot ?>/people/view/<?= (int) $donor['person_id'] ?>"><?= InputUtils::escapeHTML($donor['person_name']) ?></a>
    <?php if ($donor['family_id'] > 0): ?>
    <a class="d-block small fw-normal text-body-secondary" href="<?= $donorRoot ?>/people/family/<?= (int) $donor['family_id'] ?>"><?= InputUtils::escapeHTML($donor['family_name']) ?></a>
    <?php endif; ?>
<?php else: ?>
    <a href="<?= $donorRoot ?>/people/family/<?= (int) $donor['family_id'] ?>"><?= InputUtils::escapeHTML($donor['family_name']) ?></a>
<?php endif; ?>
