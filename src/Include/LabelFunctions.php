<?php

use ChurchCRM\Utils\MiscUtils;

// This file contains functions specifically related to address labels

/**
 * Names of the fonts FPDF ships metrics for, in the form fontFromName() parses
 * ("Helvetica", "Helvetica Bold", ...).
 *
 * @return string[]
 */
function getLabelFontNames(): array
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

    return $fontnames;
}

/**
 * Label formats known to PdfLabel.
 *
 * @return string[]
 */
function getLabelTypes(): array
{
    return ['Tractor', '5160', '5161', '5162', '5163', '5164', '8600', 'L7163'];
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
