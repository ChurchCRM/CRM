<?php

use ChurchCRM\dto\ChurchMetaData;

$contactPhone = ChurchMetaData::getChurchPhone();
$contactEmail = ChurchMetaData::getChurchEmail();
$contactWebsite = ChurchMetaData::getChurchWebSite();

if ($contactPhone || $contactEmail || $contactWebsite) : ?>
<div class="login-contact-footer">
  <?php if ($contactPhone): ?>
    <a href="tel:<?= htmlspecialchars($contactPhone) ?>">
      <i class="fa-solid fa-phone" aria-hidden="true"></i>
      <?= htmlspecialchars($contactPhone) ?>
    </a>
  <?php endif; ?>
  <?php if ($contactEmail): ?>
    <a href="mailto:<?= htmlspecialchars($contactEmail) ?>">
      <i class="fa-solid fa-envelope" aria-hidden="true"></i>
      <?= htmlspecialchars($contactEmail) ?>
    </a>
  <?php endif; ?>
  <?php if ($contactWebsite): ?>
    <a href="<?= htmlspecialchars($contactWebsite) ?>" target="_blank" rel="noopener noreferrer">
      <i class="fa-solid fa-globe" aria-hidden="true"></i>
      <?= htmlspecialchars($contactWebsite) ?>
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>
