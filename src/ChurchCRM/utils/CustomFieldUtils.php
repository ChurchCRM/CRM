<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;

/**
 * Utility class for custom field display, form rendering, validation, and SQL generation.
 * Migrated from legacy Functions.php — logic preserved as-is.
 */
class CustomFieldUtils
{
    /**
     * Returns the custom field type ID → label map.
     * Equivalent to the legacy $aPropTypes global from Functions.php.
     * Must be a method (not a constant) so gettext() is called at render time.
     */
    public static function getPropTypes(): array
    {
        return [
            1  => gettext('True / False'),
            2  => gettext('Date'),
            3  => gettext('Text Field (50 char)'),
            4  => gettext('Text Field (100 char)'),
            5  => gettext('Text Field (long)'),
            6  => gettext('Year'),
            7  => gettext('Season'),
            8  => gettext('Number'),
            9  => gettext('Person from Group'),
            10 => gettext('Money'),
            11 => gettext('Phone Number'),
            12 => gettext('Custom Drop-Down List'),
        ];
    }

    /**
     * Formats custom field data for display-only (read-only) output.
     * Migrated from displayCustomField() in Functions.php.
     */
    public static function display($type, ?string $data, $special)
    {
        switch ($type) {
            case 1:
                if ($data === 'true') {
                    return gettext('Yes');
                } elseif ($data === 'false') {
                    return gettext('No');
                }
                break;

            case 2:
                return DateTimeUtils::formatDate($data);

            case 3:
            case 4:
            case 6:
            case 8:
            case 10:
                return $data;

            case 5:
                return $data;

            case 7:
                return ucfirst($data);

            case 9:
                $personId = (int) $data;
                if ($personId <= 0) {
                    return '';
                }

                $person = PersonQuery::create()->findPk($personId);

                return $person ? $person->getFullName() : '';

            case 11:
                return $data;

            case 12:
                $optionId = (int) $data;
                if ($optionId <= 0) {
                    return '';
                }

                $option = ListOptionQuery::create()
                    ->filterById((int) $special)
                    ->filterByOptionId($optionId)
                    ->findOne();

                return $option ? $option->getOptionName() : '';

            default:
                return gettext('Invalid Editor ID!');
        }
    }

    /**
     * Generates an HTML form <input> element for a custom field.
     * Migrated from formCustomField() in Functions.php.
     */
    public static function renderForm($type, string $fieldname, $data, ?string $special, bool $bFirstPassFlag): void
    {
        switch ($type) {
            case 1:
                echo '<div class="mb-3">' .
                '<div class="form-check"><input type="radio" class="form-check-input" id="' . $fieldname . '_yes" name="' . $fieldname . '" value="true"' . ($data === 'true' ? ' checked' : '') . '><label class="form-check-label" for="' . $fieldname . '_yes">' . gettext('Yes') . '</label></div>' .
                '<div class="form-check"><input type="radio" class="form-check-input" id="' . $fieldname . '_no" name="' . $fieldname . '" value="false"' . ($data === 'false' ? ' checked' : '') . '><label class="form-check-label" for="' . $fieldname . '_no">' . gettext('No') . '</label></div>' .
                '<div class="form-check"><input type="radio" class="form-check-input" id="' . $fieldname . '_unknown" name="' . $fieldname . '" value=""' . (strlen($data) === 0 ? ' checked' : '') . '><label class="form-check-label" for="' . $fieldname . '_unknown">' . gettext('Unknown') . '</label></div>' .
                '</div>';
                break;

            case 2:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>' .
                '<input class="form-control date-picker" type="text" id="' . $fieldname . '" name="' . $fieldname . '" value="' . DateTimeUtils::formatForDatePicker($data) . '" placeholder="' . SystemConfig::getValueForAttr("sDatePickerPlaceHolder") . '"> ' .
                '</div>';
                break;

            case 3:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-font"></i></span>' .
                '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="50" value="' . InputUtils::escapeAttribute($data) . '">' .
                '</div>';
                break;

            case 4:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-align-left"></i></span>' .
                '<textarea class="form-control" id="' . $fieldname . '" name="' . $fieldname . '" rows="2" maxlength="100">' . InputUtils::escapeHTML($data) . '</textarea>' .
                '</div>';
                break;

            case 5:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-paragraph"></i></span>' .
                '<textarea class="form-control" id="' . $fieldname . '" name="' . $fieldname . '" rows="4" maxlength="65535">' . InputUtils::escapeHTML($data) . '</textarea>' .
                '</div>';
                break;

            case 6:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>' .
                '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="4" value="' . InputUtils::escapeAttribute($data) . '" placeholder="YYYY">' .
                '</div>';
                break;

            case 7:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-leaf"></i></span>' .
                '<select id="' . $fieldname . '" name="' . $fieldname . '" class="form-select">';
                echo '  <option value="none">' . gettext('Select Season') . '</option>';
                echo '  <option value="winter"';
                if ($data == 'winter') {
                    echo ' selected';
                }
                echo '>' . gettext('Winter') . '</option>';
                echo '  <option value="spring"';
                if ($data == 'spring') {
                    echo ' selected';
                }
                echo '>' . gettext('Spring') . '</option>';
                echo '  <option value="summer"';
                if ($data == 'summer') {
                    echo ' selected';
                }
                echo '>' . gettext('Summer') . '</option>';
                echo '  <option value="fall"';
                if ($data == 'fall') {
                    echo ' selected';
                }
                echo '>' . gettext('Fall') . '</option>';
                echo '</select></div>';
                break;

            case 8:
                echo '<div class="input-group">' .
                '<span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>' .
                '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="11" value="' . InputUtils::escapeAttribute($data) . '">' .
                '</div>';
                break;

            case 9:
                $groupId = (int) $special;
                $groupPeople = Person2group2roleP2g2rQuery::create()
                    ->filterByGroupId($groupId)
                    ->usePersonQuery()
                        ->orderByFirstName()
                    ->endUse()
                    ->joinWithPerson()
                    ->find();

                echo '<div class="input-group">';
                echo '<span class="input-group-text"><i class="fa-solid fa-person-half-dress"></i></span>';
                echo '<select id="' . $fieldname . '" name="' . $fieldname . '" class="form-select">';
                echo '<option value="0"' . ($data <= 0 ? ' selected' : '') . '>' . gettext('Unassigned') . '</option>';
                echo '<option value="" disabled>-----------------------</option>';

                foreach ($groupPeople as $p2g2r) {
                    $person = $p2g2r->getPerson();
                    echo '<option value="' . $person->getId() . '"' . ($data == $person->getId() ? ' selected' : '') . '>' . $person->getFullName() . '</option>';
                }

                echo '</select></div>';
                break;

            case 10:
                echo '<div class="input-group">';
                echo '<span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>';
                echo '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="13" value="' . InputUtils::escapeAttribute($data) . '">';
                echo '</div>';
                break;

            case 11:
                $checked = '';
                if (isset($_POST[$fieldname . 'noformat'])) {
                    $checked = ' checked';
                } elseif (!empty($data)) {
                    $checked = ' checked';
                }

                echo '<div class="input-group">';
                echo '<span class="input-group-text"><i class="fa-solid fa-phone"></i></span>';
                echo '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="30" value="' . InputUtils::escapeAttribute($data) . '" data-phone-mask=\'{"mask": "' . SystemConfig::getValueForAttr('sPhoneFormat') . '"}\'>';
                echo '<div class="input-group-text">';
                echo '<div class="form-check mb-0">';
                echo '<input type="checkbox" class="form-check-input" id="' . $fieldname . 'noformat" name="' . $fieldname . 'noformat" value="1"';
                echo $checked;
                echo '>';
                echo '<label class="form-check-label" for="' . $fieldname . 'noformat">' . gettext('No format') . '</label>';
                echo '</div></div></div>';
                break;

            case 12:
                $listOptions = ListOptionQuery::create()
                    ->filterById((int) $special)
                    ->orderByOptionSequence()
                    ->find();

                echo '<div class="input-group">';
                echo '<span class="input-group-text"><i class="fa-solid fa-list"></i></span>';
                echo '<select class="form-select" id="' . $fieldname . '" name="' . $fieldname . '">';
                echo '<option value="0">' . gettext('Unassigned') . '</option>';
                echo '<option value="" disabled>-----------------------</option>';

                foreach ($listOptions as $option) {
                    echo '<option value="' . $option->getOptionId() . '"' . ($data == $option->getOptionId() ? ' selected' : '') . '>' . $option->getOptionName() . '</option>';
                }

                echo '</select></div>';
                break;

            default:
                echo '<b>' . gettext('Error: Invalid Editor ID!') . '</b>';
                break;
        }
    }

    /**
     * Processes and validates custom field data based on its type.
     * Migrated from validateCustomField() in Functions.php.
     *
     * Returns false if the data is not valid, true otherwise.
     */
    public static function validate($type, &$data, string $col_Name, ?array &$aErrors): bool
    {
        global $aLocaleInfo;
        $bErrorFlag = false;
        $aErrors[$col_Name] = '';

        switch ($type) {
            case 2:
                $data = InputUtils::filterDate($data);
                if (strlen($data) > 0) {
                    $dateString = DateTimeUtils::parseAndValidate($data);
                    if ($dateString === false) {
                        $aErrors[$col_Name] = gettext('Not a valid date');
                        $bErrorFlag = true;
                    } else {
                        $data = $dateString;
                    }
                }
                break;

            case 6:
                if (strlen($data) !== 0) {
                    if (!is_numeric($data) || strlen($data) !== 4 || $data < 0) {
                        $aErrors[$col_Name] = gettext('Invalid Year');
                        $bErrorFlag = true;
                    }
                }
                break;

            case 8:
                if (strlen($data) !== 0) {
                    if ($aLocaleInfo['thousands_sep']) {
                        $data = preg_replace('/' . $aLocaleInfo['thousands_sep'] . '/i', '', $data);
                    }
                    if (!is_numeric($data)) {
                        $aErrors[$col_Name] = gettext('Invalid Number');
                        $bErrorFlag = true;
                    } elseif ($data < -2_147_483_648 || $data > 2_147_483_647) {
                        $aErrors[$col_Name] = gettext('Number too large. Must be between -2147483648 and 2147483647');
                        $bErrorFlag = true;
                    }
                }
                break;

            case 10:
                if (strlen($data) !== 0) {
                    if ($aLocaleInfo['mon_thousands_sep']) {
                        $data = preg_replace('/' . $aLocaleInfo['mon_thousands_sep'] . '/i', '', $data);
                    }
                    if (!is_numeric($data)) {
                        $aErrors[$col_Name] = gettext('Invalid Number');
                        $bErrorFlag = true;
                    } elseif ($data > 999_999_999.99) {
                        $aErrors[$col_Name] = gettext('Money amount too large. Maximum is $999999999.99');
                        $bErrorFlag = true;
                    }
                }
                break;

            default:
                break;
        }

        return !$bErrorFlag;
    }

    /**
     * Generates SQL for a custom field update.
     * Migrated from sqlCustomField() in Functions.php.
     */
    public static function buildSql(string &$sSQL, $type, $data, string $col_Name, $special): void
    {
        switch ($type) {
            case 1:
                switch ($data) {
                    case 'false':
                        $data = "'false'";
                        break;
                    case 'true':
                        $data = "'true'";
                        break;
                    default:
                        $data = 'NULL';
                        break;
                }
                $sSQL .= $col_Name . ' = ' . $data . ', ';
                break;

            case 2:
                if (strlen($data) > 0) {
                    $sSQL .= $col_Name . ' = "' . $data . '", ';
                } else {
                    $sSQL .= $col_Name . ' = NULL, ';
                }
                break;

            case 6:
            case 10:
            case 5:
                if (strlen($data) > 0) {
                    $sSQL .= $col_Name . " = '" . $data . "', ";
                } else {
                    $sSQL .= $col_Name . ' = NULL, ';
                }
                break;

            case 7:
                if ($data != 'none') {
                    $sSQL .= $col_Name . " = '" . $data . "', ";
                } else {
                    $sSQL .= $col_Name . ' = NULL, ';
                }
                break;

            case 8:
            case 9:
            case 12:
                $rawValue = is_string($data) ? trim($data) : $data;
                if ($rawValue === '' || $rawValue === null) {
                    $sSQL .= $col_Name . ' = NULL, ';
                } else {
                    $validatedInt = filter_var($rawValue, FILTER_VALIDATE_INT);
                    if ($validatedInt === false || $validatedInt === 0) {
                        $sSQL .= $col_Name . ' = NULL, ';
                    } else {
                        $sSQL .= $col_Name . " = '" . $validatedInt . "', ";
                    }
                }
                break;

            case 3:
            case 4:
            case 11:
                if (strlen($data) > 0) {
                    $sSQL .= $col_Name . " = '" . $data . "', ";
                } else {
                    $sSQL .= $col_Name . ' = NULL, ';
                }
                break;

            default:
                $sSQL .= $col_Name . " = '" . $data . "', ";
                break;
        }
    }
}
