<?php

global $sCountry;

use ChurchCRM\data\Countries;

$optionTags = [
    '<option value="">' . gettext('Unassigned') . '</option>',
    '<option value="" disabled>--------------------</option>',
];
foreach (Countries::getNames() as $country) {
    $selected = '';
    if ($sCountry === $country) {
        $selected = 'selected';
    }
    $optionTags[] = '<option value="' . $country . '" ' . $selected . '>' . gettext($country) . '</option>';
}
$optionsHtmlString = implode('', $optionTags);

echo <<<HTML
<select name="Country" class="form-control select2" id="country-input">
$optionsHtmlString
</select>
HTML;
