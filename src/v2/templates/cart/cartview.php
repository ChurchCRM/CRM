<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

echo $this->fetch('cartfunctions.php', $data);
echo $this->fetch('cartlisting.php', $data);
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
    $("#cart-listing-table").DataTable(window.CRM.plugin.dataTable);

    $(document).on("click", ".emptyCart", function (e) {
      window.CRM.cart.empty(function () {
        document.location.reload();
      });
    });

    $(document).on("click", ".RemoveFromPeopleCart", function (e) {
      clickedButton = $(this);
      e.stopPropagation();
      window.CRM.cart.removePerson([clickedButton.data("personid")], function () {
        document.location.reload();
      });
    });

  });
</script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
