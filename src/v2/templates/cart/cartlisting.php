<?php

use ChurchCRM\dto\SystemURLs;

$ListTitleText = gettext('Your cart contains') . ' ' . count($cartPeople) . ' ' . gettext('persons from') . ' ' . $iNumFamilies . ' ' . gettext('families');
?>
<!-- BEGIN CART LISTING -->
<div class="card card-primary">
  <div class="card-header with-border">
    <h3 class="card-title"><?= $ListTitleText ?></h3>
  </div>
  <div class="card-body">
    <div class="table-responsive">
    <table class="table table-hover w-100" id="cart-listing-table">
      <thead>
        <tr>
          <th><?= gettext('Actions') ?></th>
          <th><?= gettext('Name') ?></th>
          <th><?= gettext('Address') ?></th>
          <th><?= gettext('Email') ?></th>
          <th><?= gettext('Classification') ?></th>
          <th><?= gettext('Family Role') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php
        /* @var $Person ChurchCRM\Person */
        foreach ($cartPeople as $Person) {
            ?>
          <tr>
            <td>
              <button class="RemoveFromCart btn btn-sm btn-danger" data-cart-id="<?= $Person->getId() ?>" data-cart-type="person" title="<?= gettext('Remove from Cart') ?>">
                <i class="fa-solid fa-shopping-cart"></i>
              </button>
            </td>
            <td>
              <img data-image-entity-type="person" 
                   data-image-entity-id="<?= $Person->getId() ?>" 
                   class="photo-tiny">&nbsp
              <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $Person->getId() ?>"><?= $Person->getFullName() ?></a>
            </td>
            <td><?= $Person->getAddress() ?></td>
            <td><?= $Person->getEmail() ?></td>
            <td><?= $Person->getClassificationName() ?></td>
            <td><?= $Person->getFamilyRoleName() ?></td>
          </tr>
        <?php }
        ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- END CART LISTING -->

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        // Handle remove from cart button clicks in cart listing
        $(document).on('click', '.RemoveFromCart', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const $button = $(this);
            const cartId = $button.data('cart-id');
            const cartType = $button.data('cart-type');
            
            if (window.CRM && window.CRM.cartManager && cartId && cartType) {
                if (cartType === 'person') {
                    window.CRM.cartManager.removePerson(cartId, {
                        confirm: false,
                        reloadPage: true,
                        reloadDelay: 1000,
                        callback: function() {
                            // Page will reload after a brief delay
                        }
                    });
                } else if (cartType === 'family') {
                    window.CRM.cartManager.removeFamily(cartId, {
                        reloadPage: true,
                        reloadDelay: 1000,
                        callback: function() {
                            // Page will reload after a brief delay
                        }
                    });
                }
            }
        });
    });
</script>
