<?php

namespace ChurchCRM\dto;

use ChurchCRM\data\States;
use ChurchCRM\data\Country;
use ChurchCRM\data\Countries;
use ChurchCRM\Utils\LoggerUtils;

class StateDropDown extends States
{
    public function __construct(string $country)
    {
        $Country = Countries::getCountryByName($country);

        if (!$Country instanceof Country) {
            LoggerUtils::getAppLogger()->warning("Lookup of <" . $Country . "> returns value not of Country class?");
        }

        // Must cast to lowercase because ultimately this looks up files with lowercase names
        parent::__construct(strtolower($Country->getCountryCode()));
    }

    public static function getDropDown($selectedState = ''): string
    {
        $result = '<select name="State" class="form-control select2" id="state-input">';
        $optionTags = [
            '<option value="">' . gettext('Unassigned') . '</option>',
            '<option value="" disabled>--------------------</option>',
        ];

        foreach (States::getNames() as $state) {
            $selected = ($state === $selectedState) ? 'selected' : '';
            $optionTags[] = '<option value="' . $state . '" ' . $selected . '>' . gettext($state) . '</option>';
        }

        $result .= implode('', $optionTags);
        $result .= '</select>';

        return $result;
    }
}
