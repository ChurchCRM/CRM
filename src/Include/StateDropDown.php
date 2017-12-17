<?php
  use ChurchCRM\data\States;

if (empty($state)) {
    $state = "State";
}
?>

<select name="<?= $state ?>" class="form-control select2" id="state-input" style="width:100%">
  <option value=""><?= gettext('Unassigned') ?></option>
  <option value="" disabled>--------------------</option>
  <?php foreach (States::getNames() as $the_state) {
    ?>
  <option value="<?= $county ?>" <?php if ($sState == $the_state) {
        echo 'selected';
    } ?>><?= gettext($the_state) ?>
    <?php
} ?>
</select>
