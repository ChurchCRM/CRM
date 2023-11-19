<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

try {
    //there is a chance this could throw an exception if the DB has not yet been set up
    //catch and hide the exception
    $googleTrackingID = SystemConfig::getValue('sGoogleTrackingID');
    if (!empty($googleTrackingID)) {
        ?>
      <script nonce="<?= SystemURLs::getCSPNonce() ?>">
          (function (i, s, o, g, r, a, m) {
              i['GoogleAnalyticsObject'] = r;
              i[r] = i[r] || function () {
                      (i[r].q = i[r].q || []).push(arguments)
                  }, i[r].l = 1 * new Date();
              a = s.createElement(o),
                  m = s.getElementsByTagName(o)[0];
              a.async = 1;
              a.src = g;
              m.parentNode.insertBefore(a, m)
          })(window, document, 'script', 'https://www.google-analytics.com/analytics.js', 'ga');

          ga('create', '<?= $googleTrackingID ?>', 'auto');
          ga('send', 'pageview');

      </script>
        <?php
    }
} catch (\Exception $ex) {
}
?>
