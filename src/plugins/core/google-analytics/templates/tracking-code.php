<?php
/**
 * Google Analytics 4 (GA4) tracking code template.
 *
 * Variables available:
 * - $trackingId: The GA4 Measurement ID (G-XXXXXXXXXX)
 */

declare(strict_types=1);

use ChurchCRM\Utils\InputUtils;

if (empty($trackingId)) {
    return;
}

// Use InputUtils for HTML attribute escaping, json_encode for JS string
$trackingIdAttr = InputUtils::escapeAttribute($trackingId);
$trackingIdJs = json_encode($trackingId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($trackingIdJs === false) {
    return; // Invalid tracking ID
}
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $trackingIdAttr ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', <?= $trackingIdJs ?>);
</script>
