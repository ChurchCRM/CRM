<?php

use ChurchCRM\data\Countries;

?>
<select name="Country" class="form-control select2" id="country-input">
  <option value=""><?= gettext('Unassigned') ?></option>
  <option value="" disabled>--------------------</option>
  <?php foreach (Countries::getNames() as $county) {
    ?>
  <option value="<?= $county ?>" <?php if ($sCountry == $county) {
        echo 'selected';
    } ?>><?= gettext($county) ?>
    <?php
} ?>
</select>
