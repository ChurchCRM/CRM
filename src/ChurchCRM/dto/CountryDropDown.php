<?php

namespace ChurchCRM\dto;

use ChurchCRM\data\Countries;

class CountryDropDown extends Countries
{
    public static function getDropDown($selected = ''): string
    {
        $result = '<select name="Country" class="form-control select2" id="country-input">';
        $optionTags = [
            '<option value="">' . gettext('Unassigned') . '</option>',
            '<option value="" disabled>--------------------</option>',
        ];

        foreach (Countries::getNames() as $country) {
            if ($country == $selected) {
                $selected = 'selected';
            }
            $optionTags[] = '<option value="' . $country . '" ' . $selected . '>' . gettext($country) . '</option>';
            $selected = '';
        }

        $result .= implode('', $optionTags);
        $result .= '</select>';

        return $result;
    }
}
