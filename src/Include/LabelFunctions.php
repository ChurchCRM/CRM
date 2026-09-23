<?php

use ChurchCRM\Utils\MiscUtils;

// This file contains functions specifically related to address labels

function FontSelect($fieldname): void
{
    // Absolute path: the previous relative 'vendor/setasign/fpdf' only resolved
    // when the CWD happened to be the web root, and scandir() failing left the
    // font list silently empty.
    $sFPDF_PATH = __DIR__ . '/../vendor/setasign/fpdf';

    // FPDF shipped core font metrics as .php up to 1.8.x and as .json from
    // 1.9.0. Accept both so the list is not empty on either version, and use
    // pathinfo() rather than a hardcoded length to strip the extension (the
    // old code hardcoded $len - 4, which is wrong for ".json").
    $basenames = [];
    foreach (scandir($sFPDF_PATH . '/font/') ?: [] as $entry) {
        if (preg_match('/\.(php|json)$/i', $entry)) {
            $basenames[] = pathinfo($entry, PATHINFO_FILENAME);
        }
    }

    // Sort explicitly rather than trusting scandir's ordering. The $family
    // tracking below only works if a family's base name ("helvetica") is seen
    // before its variants ("helveticab"), and scandir's sort argument is not
    // dependable across filesystems -- on a Docker bind mount it was observed
    // returning "courierbi, courierb, courieri, courier" even with
    // SCANDIR_SORT_ASCENDING, which yields "Helveticab" instead of
    // "Helvetica Bold".
    sort($basenames, SORT_STRING);

    $fontnames = [];
    $family = ' ';
    foreach ($basenames as $filename) {
        if (mb_substr($filename, 0, strlen($family)) != $family) {
            $family = $filename;
        }
        $fontnames[] = MiscUtils::filenameToFontname($filename, $family);
    }

    $fontnames = array_values(array_unique($fontnames));
    sort($fontnames);

    echo '<tr>';
    echo '<td class="LabelColumn">' . gettext('Font') . ':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\">";
    foreach ($fontnames as $n) {
        $sel = '';
        if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname] == $n) {
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
    $sizes = ['default', 6, 7, 8, 9, 10, 11, 12, 14, 16, 18];
    echo '<tr>';
    echo '<td class="LabelColumn"> ' . gettext('Font Size') . ':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\">";
    foreach ($sizes as $s) {
        $sel = '';
        if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname] == $s) {
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
    $labels = ['Tractor', '5160', '5161', '5162', '5163', '5164', '8600', 'L7163'];
    echo '<tr>';
    echo '<td class="LabelColumn">' . gettext('Label Type') . ':</td>';
    echo '<td class="TextColumn">';
    echo "<select name=\"$fieldname\">";
    foreach ($labels as $l) {
        $sel = '';
        if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname] == $l) {
            $sel = ' selected';
        }
        echo '<option value="' . $l . '"' . $sel . '>' . gettext("$l") . '</option>';
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
}

function LabelGroupSelect($fieldname): void
{
    echo '<tr><td class="LabelColumn">' . gettext('Label Grouping') . '</td>';
    echo '<td class="TextColumn">';
    echo '<div class="form-check">';
    echo "<input class=\"form-check-input\" name=\"$fieldname\" type=\"radio\" value=\"indiv\" id=\"{$fieldname}_indiv\" ";

    if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname] != 'fam') {
        echo 'checked';
    }

    echo '><label class="form-check-label" for="' . $fieldname . '_indiv">' . gettext('All Individuals') . '</label></div>';
    echo '<div class="form-check">';
    echo "<input class=\"form-check-input\" name=\"$fieldname\" type=\"radio\" value=\"fam\" id=\"{$fieldname}_fam\" ";

    if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname] === 'fam') {
        echo 'checked';
    }

    echo '><label class="form-check-label" for="' . $fieldname . '_fam">' . gettext('Grouped by Family') . '</label></div></td></tr>';
}

function ToParentsOfCheckBox($fieldname): void
{
    echo '<tr><td class="LabelColumn">' . gettext('To the parents of') . ':</td>';
    echo '<td class="TextColumn">';
    echo '<div class="form-check">';
    echo "<input class=\"form-check-input\" name=\"$fieldname\" type=\"checkbox\" ";
    echo 'id="ToParent" value="1" ';

    if (array_key_exists($fieldname, $_COOKIE) && $_COOKIE[$fieldname]) {
        echo 'checked';
    }

    echo '></div></td></tr>';
}

function StartRowStartColumn(): void
{
    echo '
    <tr>
    <td class="LabelColumn">' . gettext('Start Row') . ':
    </td>
    <td class="TextColumn">
    <input type="text" name="startrow" id="startrow" maxlength="2" size="3" value="1">
    </td>
    </tr>
    <tr>
    <td class="LabelColumn">' . gettext('Start Column') . ':
    </td>
    <td class="TextColumn">
    <input type="text" name="startcol" id="startcol" maxlength="2" size="3" value="1">
    </td>
    </tr>';
}

function IgnoreIncompleteAddresses(): void
{
    echo '
    <tr>
    <td class="LabelColumn">' . gettext('Ignore Incomplete Addresses') . ':
    </td>
    <td class="TextColumn">
    <input class="form-check-input" type="checkbox" name="onlyfull" id="onlyfull" value="1" checked>
    </td>
    </tr>';
}

function LabelFileType(): void
{
    echo '
    <tr>
        <td class="LabelColumn">' . gettext('File Type') . ':
        </td>
        <td class="TextColumn">
            <select name="filetype">
                <option value="PDF">PDF</option>
                <option value="CSV">CSV</option>
            </select>
        </td>
    </tr>';
}
