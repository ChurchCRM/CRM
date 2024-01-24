<?php

global $sState;
// Need access to the Country code...
//
use ChurchCRM\data\States;
use ChurchCRM\data\Country;
use ChurchCRM\data\Countries;
use ChurchCRM\Utils\LoggerUtils;

$optionTags = [
    '<option value="">' . gettext('Unassigned') . '</option>',
    '<option value="" disabled>--------------------</option>',
];
LoggerUtils::getAppLogger()->warning("input sCountry is now ".$sCountry);
//$Country=Countries::getCountryByName($sCountry);
$Country=Countries::getCountry("US");
if ($Country instanceof Country) {
	LoggerUtils::getAppLogger()->warning("input mainCountry is now <".$Country->getCountryName()."> with code ".$Country->getCountryCode());
}
else {
	LoggerUtils::getAppLogger()->warning("Lookup of <".$sCountry."> is not part of Country class?");
}
$lowerCountryCode=strtolower($Country->getCountryCode());
$TheStates=new States($lowerCountryCode);
//$TheStates=new States("US");
foreach ($TheStates->getNames() as $state) {
    $selected = '';
    if ($sState === $state) {
        $selected = 'selected';
    }
    $optionTags[] = '<option value="' . $state . '" ' . $selected . '>' . gettext($state) . '</option>';
}
$optionsHtmlString = implode('', $optionTags);

echo <<<HTML
<select name="State" class="form-control select2" id="state-input">
$optionsHtmlString
</select>
HTML;

