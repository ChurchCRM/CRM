<?php

// Country Code $sCountry is already set when this is invoked
//
use ChurchCRM\data\States;
use ChurchCRM\data\Country;
use ChurchCRM\data\Countries;
use ChurchCRM\Utils\LoggerUtils;

$optionTags = [
    '<option value="">' . gettext('Unassigned') . '</option>',
    '<option value="" disabled>--------------------</option>',
];
$Country = Countries::getCountryByName($sCountry);
if (!$Country instanceof Country) {
    LoggerUtils::getAppLogger()->warning("Lookup of <" . $sCountry . "> returns value not of Country class?");
} else {
    $lowerCountryCode = strtolower($Country->getCountryCode());
    // Must cast to lowercase because ultimately this looks up files with lc names
    $TheStates = new States($lowerCountryCode);
    foreach ($TheStates->getNames() as $state) {
        $selected = '';
        if ($sState === $state) {
            $selected = 'selected';
        }
        $optionTags[] = '<option value="' . $state . '" ' . $selected . '>' . gettext($state) . '</option>';
    }
}
$optionsHtmlString = implode('', $optionTags);

echo <<<HTML
<select name="State" class="form-control select2" id="state-input">
$optionsHtmlString
</select>
HTML;
