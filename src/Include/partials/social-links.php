<?php

/**
 * ChurchCRM social profile links, shared by the logged-in and logged-out footers.
 *
 * Optional input: $socialLinkSeparator (raw HTML placed between links, default none).
 */

use ChurchCRM\Utils\InputUtils;

$socialLinks = [
    ['url' => 'https://www.facebook.com/getChurchCRM', 'title' => 'Facebook', 'icon' => 'fa-facebook'],
    ['url' => 'https://www.instagram.com/getchurchcrm/', 'title' => 'Instagram', 'icon' => 'fa-instagram'],
    ['url' => 'https://x.com/getChurchCRM', 'title' => 'X', 'icon' => 'fa-x-twitter'],
    ['url' => 'https://www.linkedin.com/company/getchurchcrm/', 'title' => 'LinkedIn', 'icon' => 'fa-linkedin'],
    ['url' => 'https://www.youtube.com/@getChurchCRM', 'title' => 'YouTube', 'icon' => 'fa-youtube'],
];
$socialLinkSeparator ??= '';

foreach ($socialLinks as $index => $socialLink) {
    if ($index > 0) {
        echo $socialLinkSeparator;
    }
    ?>
<a href="<?= InputUtils::escapeAttribute($socialLink['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= InputUtils::escapeAttribute($socialLink['title']) ?>">
  <i class="fa-brands <?= InputUtils::escapeAttribute($socialLink['icon']) ?>"></i>
</a>
    <?php
}
