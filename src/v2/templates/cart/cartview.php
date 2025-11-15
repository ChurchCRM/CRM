<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

echo $this->fetch('cartfunctions.php', $data);
echo $this->fetch('cartlisting.php', $data);
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
    $("#cart-listing-table").DataTable(window.CRM.plugin.dataTable);
    // CartManager handles all cart operations generically via data-cart-id and data-cart-type attributes
  });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
