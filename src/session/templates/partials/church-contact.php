<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Utils\InputUtils;

$contactPhone = ChurchMetaData::getChurchPhone();
$contactEmail = ChurchMetaData::getChurchEmail();
$contactWebsite = ChurchMetaData::getChurchWebSite();
$contactWebsite = filter_var($contactWebsite, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($contactWebsite, PHP_URL_SCHEME)), ['http', 'https'], true) ? $contactWebsite : '';

if ($contactPhone || $contactEmail || $contactWebsite) : ?>
<div class="login-contact-footer">
  <?php if ($contactPhone): ?>
    <a href="tel:<?= InputUtils::escapeAttribute($contactPhone) ?>">
      <i class="fa-solid fa-phone" aria-hidden="true"></i>
      <?= InputUtils::escapeHTML($contactPhone) ?>
    </a>
  <?php endif; ?>
  <?php if ($contactEmail): ?>
    <a href="mailto:<?= InputUtils::escapeAttribute($contactEmail) ?>">
      <i class="fa-solid fa-envelope" aria-hidden="true"></i>
      <?= InputUtils::escapeHTML($contactEmail) ?>
    </a>
  <?php endif; ?>
  <?php if ($contactWebsite): ?>
    <a href="<?= InputUtils::escapeAttribute($contactWebsite) ?>" target="_blank" rel="noopener noreferrer">
      <i class="fa-solid fa-globe" aria-hidden="true"></i>
      <?= InputUtils::escapeHTML($contactWebsite) ?>
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>
