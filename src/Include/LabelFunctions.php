<?php

use ChurchCRM\Service\LabelSheetService;

// This file contains functions specifically related to address labels

/**
 * Names of the fonts the label sheet offers ("Helvetica", "Helvetica Bold", ...).
 *
 * @return string[]
 */
function getLabelFontNames(): array
{
    return LabelSheetService::fontNames();
}

/**
 * Label formats known to the label sheet.
 *
 * @return string[]
 */
function getLabelTypes(): array
{
    return LabelSheetService::labelTypes();
}

/**
 * Font sizes offered for labels; 'default' lets the report choose.
 *
 * @return array<int, int|string>
 */
function getLabelFontSizes(): array
{
    return ['default', 6, 7, 8, 9, 10, 11, 12, 14, 16, 18];
}

/**
 * The value the label form last submitted for a field, remembered by the
 * report in a cookie, or null when there is none.
 */
function getLabelFormCookie(string $fieldname): ?string
{
    return array_key_exists($fieldname, $_COOKIE) ? (string) $_COOKIE[$fieldname] : null;
}

function FontSelect($fieldname): void
{
    echo '<tr>';
    echo '<td class="LabelColumn">' . gettext('Font') . ':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\" class=\"form-select\">";
    foreach (getLabelFontNames() as $n) {
        $sel = '';
        if (getLabelFormCookie($fieldname) == $n) {
            $sel = ' selected';
        }
        echo '<option value="' . $n . '"' . $sel . '>' . $n . '</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
}

function FontSizeSelect($fieldname): void
{
    echo '<tr>';
    echo '<td class="LabelColumn"> ' . gettext('Font Size') . ':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\" class=\"form-select\">";
    foreach (getLabelFontSizes() as $s) {
        $sel = '';
        if (getLabelFormCookie($fieldname) == $s) {
            $sel = ' selected';
        }
        echo '<option value="' . $s . '"' . $sel . '>' . gettext("$s") . '</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
}

function LabelSelect($fieldname): void
{
    echo '<tr>';
    echo '<td class="LabelColumn">' . gettext('Label Type') . ':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\" class=\"form-select\">";
    foreach (getLabelTypes() as $l) {
        $sel = '';
        if (getLabelFormCookie($fieldname) == $l) {
            $sel = ' selected';
        }
        echo '<option value="' . $l . '"' . $sel . '>' . gettext("$l") . '</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
}
