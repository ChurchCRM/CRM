<?php

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * Render the bundled ChurchCRM mark in both brand treatments.
 *
 * Light UI (default / no data-bs-theme): ink + blue.
 * Dark UI (html[data-bs-theme="dark"]): paper + blue.
 *
 * $kind is "symbol" (sidebar) or "full" (login / setup / email-adjacent pages).
 */
function churchcrm_render_brand_logo(string $kind = 'symbol', ?string $alt = null): void
{
    $root = SystemURLs::getRootPath();
    $altText = $alt ?? (ChurchMetaData::getChurchName() ?: 'ChurchCRM');
    $altAttr = InputUtils::escapeAttribute($altText);

    if ($kind === 'full') {
        $light = $root . '/Images/churchcrm-logo-ink-blue.svg';
        $dark = $root . '/Images/churchcrm-logo-paper-blue.svg';
        $class = 'crm-brand-logo';
    } else {
        $light = $root . '/Images/churchcrm-symbol-ink-blue.svg';
        $dark = $root . '/Images/churchcrm-symbol-paper-blue.svg';
        $class = 'navbar-brand-image crm-brand-logo';
    }

    echo '<img src="' . InputUtils::escapeAttribute($light) . '" alt="' . $altAttr . '" class="' . $class . ' crm-brand-logo-light" />';
    echo '<img src="' . InputUtils::escapeAttribute($dark) . '" alt="' . $altAttr . '" class="' . $class . ' crm-brand-logo-dark" />';
}
