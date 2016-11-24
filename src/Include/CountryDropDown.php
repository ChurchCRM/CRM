<?php
use ChurchCRM\data\Countries;
?>
<select name="Country" class="form-control">
  <option value=""><?= gettext("Unassigned") ?></option>
  <option value="" disabled>--------------------</option>
  <?php foreach (Countries::getNames() as $county) { ?>
  <option value="<?= $county ?>" <?php if ($sCountry == $county) { echo "selected"; } ?>><?= $county ?>
    <?php } ?>
</select>
