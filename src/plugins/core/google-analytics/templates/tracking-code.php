<?php
/**
 * Google Analytics 4 (GA4) tracking code template.
 *
 * Variables available:
 * - $trackingId: The GA4 Measurement ID (G-XXXXXXXXXX)
 */

declare(strict_types=1);

if (empty($trackingId)) {
    return;
}

$trackingId = htmlspecialchars($trackingId, ENT_QUOTES, 'UTF-8');
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $trackingId ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?= $trackingId ?>');
</script>
