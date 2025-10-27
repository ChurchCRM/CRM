<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

echo $this->fetch('cartfunctions.php', $data);
echo $this->fetch('cartlisting.php', $data);
?>
<script nonce="<?= SystemURLs::getCSPNonce() ?>" >
  $(document).ready(function () {
    $("#cart-listing-table").DataTable(window.CRM.plugin.dataTable);

    // Cart event handlers now use CartManager with notifications
    // Empty cart handler is in cart.js
    
    // Remove single person from cart on this page
        $(document).on("click",".RemoveFromPeopleCart", function(e) {
        e.preventDefault();
        var clickedButton = $(this);
        var iPersonId = clickedButton.data("cartpersonid");
        
        // Use CartManager with confirmation
        window.CRM.cartManager.removePerson([iPersonId], {
            confirm: true,
            showNotification: true,
            callback: function() {
                // Reload page to update the cart view
                location.reload();
            }
        });
    });

  });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
