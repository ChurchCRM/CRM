<?php
use ChurchCRM\data\Countries;

if (empty($country)) {
    $country = "Country";
}

?>
<select name="<?= $country ?>" class="form-control select2" id="country-input" style="width:100%">
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
