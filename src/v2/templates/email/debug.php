<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<pre>
<?php
if (empty($message)) {
    $mailer->send();
} else {
    echo $message;
} ?>
</pre>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
