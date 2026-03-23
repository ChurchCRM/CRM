<?php

use ChurchCRM\dto\SystemURLs;

$ListTitleText = gettext('Your cart contains') . ' ' . count($cartPeople) . ' ' . gettext('people from') . ' ' . $iNumFamilies . ' ' . gettext('families');
?>
<!-- BEGIN CART LISTING -->
<div class="card">
  <div class="card-header d-flex align-items-center">
    <h3 class="card-title"><?= $ListTitleText ?></h3>
  </div>
  <div class="card-body">
    <div class="table-responsive">
    <table class="table table-hover w-100" id="cart-listing-table">
      <thead>
        <tr>
          <th class="no-export"><?= gettext('Actions') ?></th>
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
              <div class="d-flex align-items-center gap-2">
                <?php 
                    if ($Person->getPhoto()->hasUploadedPhoto()) {
                        echo '<img class="avatar avatar-sm rounded-circle view-person-photo" data-person-id="' . $Person->getId() . '" src="' . $Person->getPhoto()->getPhotoURL() . '" alt="" />';
                    } else {
                        $fullName = $Person->getFullName();
                        $parts = explode(' ', trim($fullName));
                        $initials = '';
                        if (count($parts) >= 2) {
                            $initials = strtoupper($parts[0][0] . $parts[count($parts)-1][0]);
                        } else if (count($parts) === 1) {
                            $initials = strtoupper(substr($parts[0], 0, 2));
                        }
                        $colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#00f2fe', '#43e97b', '#fa709a', '#fee140'];
                        $hash = array_sum(array_map('ord', str_split($fullName))) % count($colors);
                        $color = $colors[$hash];
                        echo '<span class="avatar avatar-sm rounded-circle view-person-photo" data-person-id="' . $Person->getId() . '" style="background-color: ' . $color . '; cursor: pointer;" title="' . gettext('View Photo') . '"><span class="avatar-title fs-6 fw-bold">' . $initials . '</span></span>';
                    }
                ?>
                <a href="<?= SystemURLs::getRootPath()?>/PersonView.php?PersonID=<?= $Person->getId() ?>"><?= $Person->getFullName() ?></a>
              </div>
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

<script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
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
