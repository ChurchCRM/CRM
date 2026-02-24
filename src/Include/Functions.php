<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Cart;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\PersonService;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Service\FinancialService;
use ChurchCRM\Utils\FiscalYearUtils;
use ChurchCRM\Utils\FunctionsUtils;
use ChurchCRM\Utils\InputUtils;

$personService = new PersonService();
$systemService = new SystemService();

// Basic security checks:
if (empty($bSuppressSessionTests)) {  // This is used for the login page only.
    AuthenticationManager::ensureAuthentication();
}

// Constants
$aPropTypes = [
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

$sGlobalMessageClass = 'success';

// Handle session-based messages (from redirects)
if (isset($_SESSION['sGlobalMessage'])) {
    $sGlobalMessage = $_SESSION['sGlobalMessage'];
    $sGlobalMessageClass = $_SESSION['sGlobalMessageClass'] ?? 'success';
    unset($_SESSION['sGlobalMessage']);
    unset($_SESSION['sGlobalMessageClass']);
}
// Handle query parameter messages (for same-page operations, legacy pattern)
elseif (isset($_GET['Registered'])) {
    $sGlobalMessage = gettext('Thank you for registering your ChurchCRM installation.');
    $sGlobalMessageClass = 'success';
}

if (isset($_GET['PDFEmailed'])) {
    if ($_GET['PDFEmailed'] == 1) {
        $sGlobalMessage = gettext('PDF successfully emailed to family members.');
        $sGlobalMessageClass = 'success';
    } else {
        $sGlobalMessage = gettext('Failed to email PDF to family members.');
        $sGlobalMessageClass = 'danger';
    }
}

// Are they adding an entire group to the cart?
// Note: AddGroupToPeopleCart is legacy - cart now managed through API routes
if (isset($_GET['AddGroupToPeopleCart'])) {
    // Legacy function call removed - functionality now handled by cart API
    // AddGroupToPeopleCart(InputUtils::legacyFilterInput($_GET['AddGroupToPeopleCart'], 'int'));
    $sGlobalMessage = gettext('Group successfully added to the Cart.');
    $sGlobalMessageClass = 'success';
}

// Are they removing an entire group from the Cart?
// Note: RemoveGroupFromPeopleCart is legacy - cart now managed through API routes
if (isset($_GET['RemoveGroupFromPeopleCart'])) {
    // Legacy function call removed - functionality now handled by cart API
    // RemoveGroupFromPeopleCart(InputUtils::legacyFilterInput($_GET['RemoveGroupFromPeopleCart'], 'int'));
    $sGlobalMessage = gettext('Group successfully removed from the Cart.');
    $sGlobalMessageClass = 'success';
}

if (isset($_GET['ProfileImageDeleted'])) {
    $sGlobalMessage = gettext('Profile Image successfully removed.');
    $sGlobalMessageClass = 'success';
}

if (isset($_GET['ProfileImageUploaded'])) {
    $sGlobalMessage = gettext('Profile Image successfully updated.');
    $sGlobalMessageClass = 'success';
}

if (isset($_GET['ProfileImageUploadedError'])) {
    $sGlobalMessage = gettext('Profile Image upload Error.');
    $sGlobalMessageClass = 'danger';
}

// Are they removing a person from the Cart?
// Note: RemoveFromPeopleCart is legacy - cart now managed through API routes
if (isset($_GET['RemoveFromPeopleCart'])) {
    // Legacy function call removed - functionality now handled by cart API
    // RemoveFromPeopleCart(InputUtils::legacyFilterInput($_GET['RemoveFromPeopleCart'], 'int'));
    $sGlobalMessage = gettext('Selected record successfully removed from the Cart.');
    $sGlobalMessageClass = 'success';
}

if (isset($_POST['BulkAddToCart'])) {
    $aItemsToProcess = explode(',', $_POST['BulkAddToCart']);

    if (isset($_POST['AndToCartSubmit'])) {
        if (isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = array_intersect($_SESSION['aPeopleCart'], $aItemsToProcess);
        }
    } elseif (isset($_POST['NotToCartSubmit'])) {
        if (isset($_SESSION['aPeopleCart'])) {
            $_SESSION['aPeopleCart'] = array_diff($_SESSION['aPeopleCart'], $aItemsToProcess);
        }
    } else {
        for ($iCount = 0; $iCount < count($aItemsToProcess); $iCount++) {
            Cart::addPerson(str_replace(',', '', $aItemsToProcess[$iCount]));
        }
        $sGlobalMessage = sprintf(ngettext('%d Person added to the Cart.', '%d People added to the Cart.', $iCount), $iCount);
        $sGlobalMessageClass = 'success';
    }
}

//
// Some very basic functions that all scripts use
//

// PrintFYIDSelect: make a fiscal year selection menu.
function PrintFYIDSelect(string $selectName, ?int $iFYID = null): void
{
    echo sprintf('<select class="form-control" name="%s">', $selectName);

    $hasSelected = false;
    $selectableOptions = [];
    for ($fy = 1; $fy < FiscalYearUtils::getCurrentFiscalYearId() + 2; $fy++) {
        $selectedTag = '';
        if ($iFYID === $fy) {
            $hasSelected = true;
            $selectedTag = ' selected';
        }

        $selectableOptions[] = sprintf('<option value="%s"', $fy) . $selectedTag . '>' . MakeFYString((int) $fy) . '</option>';
    }

    $selectableOptions = [
        '<option disabled value="0"' . (!$hasSelected ? ' selected' : '') . '>' . gettext('Select Fiscal Year') . '</option>',
        ...$selectableOptions
    ];

    echo implode('', $selectableOptions);

    echo '</select>';
}

// Formats a fiscal year string
function MakeFYString(int|string|null $iFYID): string
{
    if ($iFYID === null || $iFYID === '') {
        return '';
    }

    // Delegate to FinancialService to centralize fiscal year formatting logic.
    return FinancialService::formatFiscalYear((int) $iFYID);
}

// Runs an SQL query.  Returns the result resource.
// By default stop on error, unless a second (optional) argument is passed as false.
// Delegates to ChurchCRM\Utils\FunctionsUtils::runQuery() to avoid code duplication.
function RunQuery(string $sSQL, bool $bStopOnError = true)
{
    return FunctionsUtils::runQuery($sSQL, $bStopOnError);
}

function convertCartToString(array $aCartArray): string
{
    // Implode the array
    $sCartString = implode(',', $aCartArray);

    // Make sure the comma is chopped off the end
    if (mb_substr($sCartString, strlen($sCartString) - 1, 1) == ',') {
        $sCartString = mb_substr($sCartString, 0, strlen($sCartString) - 1);
    }

    // Make sure there are no duplicate commas
    $sCartString = str_replace(',,', '', $sCartString);

    return $sCartString;
}

function change_date_for_place_holder(?string $string = null): string
{
    $string ??= '';
    $timestamp = strtotime($string);

    if ($timestamp !== false) {
        return date(SystemConfig::getValue("sDatePickerFormat"), $timestamp);
    }

    return '';
}

// Reinstated by Todd Pillars for Event Listing
// Takes MYSQL DateTime
// bWithtime 1 to be displayed
function FormatDate($dDate, bool $bWithTime = false): string
{
    // Handle empty or invalid dates
    if ($dDate == '' || $dDate == '0000-00-00 00:00:00' || $dDate == '0000-00-00' || $dDate === null) {
        return '';
    }

    try {
        // Parse the date string into a DateTime object
        $dateObj = new DateTime($dDate);
    } catch (Exception $e) {
        // Return empty string for invalid dates
        return '';
    }

    // Get the date format from system config
    $dateFormat = SystemConfig::getValue("sDateFormatLong");
    
    // Convert format to DateTime format (from strftime-style)
    // d = day, m = month name, Y = year
    $dateFormat = str_replace("d", "d", $dateFormat);
    $dateFormat = str_replace("m", "F", $dateFormat);  // F = full month name
    $dateFormat = str_replace("Y", "Y", $dateFormat);
    $dateFormat = str_replace("/", " ", $dateFormat);
    $dateFormat = str_replace("-", " ", $dateFormat);

    if ($bWithTime) {
        // Add time format (g:i A = 12-hour format with am/pm)
        $formattedDate = $dateObj->format($dateFormat . ' g:i A');
    } else {
        $formattedDate = $dateObj->format($dateFormat);
    }

    return $formattedDate;
}

// Returns a string of a person's full name, formatted as specified by $Style
// $Style = 0  :  "Title FirstName MiddleName LastName, Suffix"
// $Style = 1  :  "Title FirstName MiddleInitial. LastName, Suffix"
// $Style = 2  :  "LastName, Title FirstName MiddleName, Suffix"
// $Style = 3  :  "LastName, Title FirstName MiddleInitial., Suffix"
//
function FormatFullName(?string $Title, ?string $FirstName, ?string $MiddleName, ?string $LastName, ?string $Suffix, $Style): string
{
    $nameString = '';

    switch ($Style) {
        case 0:
            if ($Title) {
                $nameString .= $Title . ' ';
            }
            $nameString .= $FirstName;
            if ($MiddleName) {
                $nameString .= ' ' . $MiddleName;
            }
            if ($LastName) {
                $nameString .= ' ' . $LastName;
            }
            if ($Suffix) {
                $nameString .= ', ' . $Suffix;
            }
            break;

        case 1:
            if ($Title) {
                $nameString .= $Title . ' ';
            }
            $nameString .= $FirstName;
            if ($MiddleName) {
                $nameString .= ' ' . mb_strtoupper(mb_substr($MiddleName, 0, 1)) . '.';
            }
            if ($LastName) {
                $nameString .= ' ' . $LastName;
            }
            if ($Suffix) {
                $nameString .= ', ' . $Suffix;
            }
            break;

        case 2:
            if ($LastName) {
                $nameString .= $LastName . ', ';
            }
            if ($Title) {
                $nameString .= $Title . ' ';
            }
            $nameString .= $FirstName;
            if ($MiddleName) {
                $nameString .= ' ' . $MiddleName;
            }
            if ($Suffix) {
                $nameString .= ', ' . $Suffix;
            }
            break;

        case 3:
            if ($LastName) {
                $nameString .= $LastName . ', ';
            }
            if ($Title) {
                $nameString .= $Title . ' ';
            }
            $nameString .= $FirstName;
            if ($MiddleName) {
                $nameString .= ' ' . mb_strtoupper(mb_substr($MiddleName, 0, 1)) . '.';
            }
            if ($Suffix) {
                $nameString .= ', ' . $Suffix;
            }
            break;
    }

    return $nameString;
}

// Generate a nicely formatted string for "FamilyName - Address / City, State" with available data
function FormatAddressLine(?string $Address, ?string $City, ?string $State): string
{
    $sText = '';

    if ($Address != '' || $City != '' || $State != '') {
        $sText = ' - ';
    }
    $sText .= $Address;
    if ($Address != '' && ($City != '' || $State != '')) {
        $sText .= ' / ';
    }
    $sText .= $City;
    if ($City != '' && $State != '') {
        $sText .= ', ';
    }

    return $sText . $State;
}

//
// Formats the data for a custom field for display-only uses
//
function displayCustomField($type, ?string $data, $special)
{
    global $cnInfoCentral;

    switch ($type) {
    // Handler for boolean fields
        case 1:
            if ($data == 'true') {
                return gettext('Yes');
            } elseif ($data == 'false') {
                return gettext('No');
            }
            break;

    // Handler for date fields
        case 2:
            return FormatDate($data);
    // Handler for text fields, years, seasons, numbers, money
        case 3:
        case 4:
        case 6:
        case 8:
        case 10:
            return $data;

    // Handler for extended text fields (MySQL type TEXT, Max length: 2^16-1)
        case 5:
          /*if (strlen($data) > 100) {
              return mb_substr($data, 0, 100) . "...";
          }else{
              return $data;
          }
          */
            return $data;

    // Handler for season.  Capitalize the word for nicer display.
        case 7:
            return ucfirst($data);

    // Handler for "person from group"
        case 9:
            if ($data > 0) {
                $sSQL = 'SELECT per_FirstName, per_LastName FROM person_per WHERE per_ID =' . $data;
                $rsTemp = RunQuery($sSQL);
                extract(mysqli_fetch_array($rsTemp));

                return $per_FirstName . ' ' . $per_LastName;
            } else {
                return '';
            }

    // Handler for phone numbers
        case 11:
            // Display raw stored phone value (no formatting)
            return $data;

    // Handler for custom lists
        case 12:
            if ($data > 0) {
                $sSQL = "SELECT lst_OptionName FROM list_lst WHERE lst_ID = $special AND lst_OptionID = $data";
                $rsTemp = RunQuery($sSQL);
                extract(mysqli_fetch_array($rsTemp));

                return $lst_OptionName;
            } else {
                return '';
            }

    // Otherwise, display error for debugging.
        default:
            return gettext('Invalid Editor ID!');
    }
}

//
// Generates an HTML form <input> line for a custom field
//
function formCustomField($type, string $fieldname, $data, ?string $special, bool $bFirstPassFlag): void
{
    global $cnInfoCentral;

    switch ($type) {
    // Handler for boolean fields
        case 1:
            echo '<div class="form-group">' .
            '<div class="custom-control custom-radio"><input type="radio" class="custom-control-input" id="' . $fieldname . '_yes" name="' . $fieldname . '" value="true"' . ($data == 'true' ? ' checked' : '') . '><label class="custom-control-label" for="' . $fieldname . '_yes">' . gettext('Yes') . '</label></div>' .
            '<div class="custom-control custom-radio"><input type="radio" class="custom-control-input" id="' . $fieldname . '_no" name="' . $fieldname . '" value="false"' . ($data == 'false' ? ' checked' : '') . '><label class="custom-control-label" for="' . $fieldname . '_no">' . gettext('No') . '</label></div>' .
            '<div class="custom-control custom-radio"><input type="radio" class="custom-control-input" id="' . $fieldname . '_unknown" name="' . $fieldname . '" value=""' . (strlen($data) === 0 ? ' checked' : '') . '><label class="custom-control-label" for="' . $fieldname . '_unknown">' . gettext('Unknown') . '</label></div>' .
            '</div>';
            break;
    // Handler for date fields
        case 2:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-calendar"></i></span>' .
            '</div>' .
            '<input class="form-control date-picker" type="text" id="' . $fieldname . '" name="' . $fieldname . '" value="' . change_date_for_place_holder($data) . '" placeholder="' . SystemConfig::getValue("sDatePickerPlaceHolder") . '"> ' .
            '</div>';
            break;

    // Handler for 50 character max. text fields
        case 3:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-font"></i></span>' .
            '</div>' .
            '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="50" value="' . InputUtils::escapeAttribute($data) . '">' .
            '</div>';
            break;

    // Handler for 100 character max. text fields
        case 4:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-align-left"></i></span>' .
            '</div>' .
            '<textarea class="form-control" id="' . $fieldname . '" name="' . $fieldname . '" rows="2" maxlength="100">' . InputUtils::escapeHTML($data) . '</textarea>' .
            '</div>';
            break;

    // Handler for extended text fields (MySQL type TEXT, Max length: 2^16-1)
        case 5:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-paragraph"></i></span>' .
            '</div>' .
            '<textarea class="form-control" id="' . $fieldname . '" name="' . $fieldname . '" rows="4" maxlength="65535">' . InputUtils::escapeHTML($data) . '</textarea>' .
            '</div>';
            break;

    // Handler for 4-digit year
        case 6:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-calendar-days"></i></span>' .
            '</div>' .
            '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="4" value="' . InputUtils::escapeAttribute($data) . '" placeholder="YYYY">' .
            '</div>';
            break;

    // Handler for season (drop-down selection)
        case 7:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-leaf"></i></span>' .
            '</div>' .
            '<select id="' . $fieldname . '" name="' . $fieldname . '" class="form-control">';
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

    // Handler for integer numbers
        case 8:
            echo '<div class="input-group">' .
            '<div class="input-group-prepend">' .
            '<span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>' .
            '</div>' .
            '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="11" value="' . InputUtils::escapeAttribute($data) . '">' .
            '</div>';
            break;

    // Handler for "person from group"
        case 9:
          // ... Get First/Last name of everyone in the group, plus their person ID ...
          // In this case, prop_Special is used to store the Group ID for this selection box
          // This allows the group special-property designer to allow selection from a specific group

            $sSQL = 'SELECT person_per.per_ID, person_per.per_FirstName, person_per.per_LastName
                        FROM person2group2role_p2g2r
                        LEFT JOIN person_per ON person2group2role_p2g2r.p2g2r_per_ID = person_per.per_ID
                        WHERE p2g2r_grp_ID = ' . $special . ' ORDER BY per_FirstName';

            $rsGroupPeople = RunQuery($sSQL);

            echo '<div class="input-group">';
            echo '<div class="input-group-prepend">';
            echo '<span class="input-group-text"><i class="fa-solid fa-user"></i></span>';
            echo '</div>';
            echo '<select id="' . $fieldname . '" name="' . $fieldname . '" class="form-control">';
            echo '<option value="0"';
            if ($data <= 0) {
                echo ' selected';
            }
            echo '>' . gettext('Unassigned') . '</option>';
            echo '<option value="" disabled>-----------------------</option>';

            while ($aRow = mysqli_fetch_array($rsGroupPeople)) {
                extract($aRow);

                echo '<option value="' . $per_ID . '"';
                if ($data == $per_ID) {
                    echo ' selected';
                }
                echo '>' . $per_FirstName . '&nbsp;' . $per_LastName . '</option>';
            }

            echo '</select></div>';
            break;

    // Handler for money amounts
        case 10:
            echo '<div class="input-group">';
            echo '<div class="input-group-prepend">';
            echo '<span class="input-group-text"><i class="fa-solid fa-dollar-sign"></i></span>';
            echo '</div>';
            echo '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="13" value="' . InputUtils::escapeAttribute($data) . '">';
            echo '</div>';
            break;

    // Handler for phone numbers
        case 11:
          // This is silly. Perhaps ExpandPhoneNumber before this function is called!
          // this business of overloading the special field is really troublesome when trying to follow the code.
                        // No expansion/formatting on display; keep raw stored value in $data
            
            // Determine checkbox state:
            // - If field has data: check "No format" (preserve stored value)
            // - If field is empty: uncheck "No format" (allow mask for new entry)
            $checked = '';
            if (isset($_POST[$fieldname . 'noformat'])) {
                // POST takes precedence (user just submitted the form)
                $checked = ' checked';
            } elseif (!empty($data)) {
                // Field has data - check "No format" to preserve it
                $checked = ' checked';
            }
            // If field is empty, leave unchecked so mask can be applied

            echo '<div class="input-group">';
            echo '<div class="input-group-prepend">';
            echo '<span class="input-group-text"><i class="fa-solid fa-phone"></i></span>';
            echo '</div>';
            // Note: using data-phone-mask instead of data-inputmask to prevent auto-initialization
            echo '<input class="form-control" type="text" id="' . $fieldname . '" name="' . $fieldname . '" maxlength="30" value="' . InputUtils::escapeAttribute($data) . '" data-phone-mask=\'{"mask": "' . SystemConfig::getValue('sPhoneFormat') . '"}\'>'; 
            echo '<div class="input-group-append">';
            echo '<div class="input-group-text">';
            echo '<div class="custom-control custom-checkbox mb-0">';
            echo '<input type="checkbox" class="custom-control-input" id="' . $fieldname . 'noformat" name="' . $fieldname . 'noformat" value="1"';
            echo $checked;
            echo '>';
            echo '<label class="custom-control-label" for="' . $fieldname . 'noformat">' . gettext('No format') . '</label>';
            echo '</div></div></div></div>';
            break;

    // Handler for custom lists
        case 12:
            $sSQL = "SELECT * FROM list_lst WHERE lst_ID = $special ORDER BY lst_OptionSequence";
            $rsListOptions = RunQuery($sSQL);

            echo '<div class="input-group">';
            echo '<div class="input-group-prepend">';
            echo '<span class="input-group-text"><i class="fa-solid fa-list"></i></span>';
            echo '</div>';
            echo '<select class="form-control" id="' . $fieldname . '" name="' . $fieldname . '">';
            echo '<option value="0">' . gettext('Unassigned') . '</option>';
            echo '<option value="" disabled>-----------------------</option>';

            while ($aRow = mysqli_fetch_array($rsListOptions)) {
                extract($aRow);
                echo '<option value="' . $lst_OptionID . '"';
                if ($data == $lst_OptionID) {
                    echo ' selected';
                }
                echo '>' . $lst_OptionName . '</option>';
            }

            echo '</select></div>';
            break;

    // Otherwise, display error for debugging.
        default:
            echo '<b>' . gettext('Error: Invalid Editor ID!') . '</b>';
            break;
    }
}

function assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut = 'future')
{
    // This function takes a year, month and day from parseAndValidateDate.  On success this
    // function returns a string in the form "YYYY-MM-DD".  It returns FALSE on failure.
    // The year can be either 2 digit or 4 digit.  If a 2 digit year is passed the $passfut
    // indicates whether to return a 4 digit year in the past or the future.  The parameter
    // $passfut is not needed for the current year.  If unspecified it assumes the two digit year
    // is either this year or one of the next 99 years.

    // Parse the year
    // Take a 2 or 4 digit year and return a 4 digit year.  Use $pasfut to determine if
    // two digit year maps to past or future 4 digit year.
    if (strlen($sYear) === 2) {
        $thisYear = date('Y');
        $twoDigit = mb_substr($thisYear, 2, 2);
        if ($sYear == $twoDigit) {
            // Assume 2 digit year is this year
            $sYear = mb_substr($thisYear, 0, 4);
        } elseif ($pasfut == 'future') {
            // Assume 2 digit year is in next 99 years
            if ($sYear > $twoDigit) {
                $sYear = mb_substr($thisYear, 0, 2) . $sYear;
            } else {
                $sNextCentury = $thisYear + 100;
                $sYear = mb_substr($sNextCentury, 0, 2) . $sYear;
            }
        } else {
            // Assume 2 digit year was is last 99 years
            if ($sYear < $twoDigit) {
                $sYear = mb_substr($thisYear, 0, 2) . $sYear;
            } else {
                $sLastCentury = $thisYear - 100;
                $sYear = mb_substr($sLastCentury, 0, 2) . $sYear;
            }
        }
    }
    // If the $sYear is not YYYY, return false.
    if (strlen($sYear) !== 4) {
        return false;
    }

    // Parse the Month
    // Take a one or two character month and return a two character month
    if (strlen($sMonth) === 1) {
        $sMonth = '0' . $sMonth;
    }
    // If the $sMonth is not MM, return false.
    if (strlen($sMonth) !== 2) {
        return false;
    }

    // Parse the Day
    // Take a one or two character day and return a two character day
    if (strlen($sDay) === 1) {
        $sDay = '0' . $sDay;
    }
    // If the $sDay is not DD, return false.
    if (strlen($sDay) !== 2) {
        return false;
    }

    $sScanString = $sYear . '-' . $sMonth . '-' . $sDay;
    [$iYear, $iMonth, $iDay] = sscanf($sScanString, '%04d-%02d-%02d');

    if (checkdate($iMonth, $iDay, $iYear)) {
        return $sScanString;
    } else {
        return false;
    }
}

function parseAndValidateDate($data, $locale = 'US', $pasfut = 'future')
{
    // This function was written because I had no luck finding a PHP
    // function that would reliably parse a human entered date string for
    // dates before 1/1/1970 or after 1/19/2038 on any Operating System.
    //
    // This function has hooks for US English M/D/Y format as well as D/M/Y.  The
    // default is M/D/Y for date.  To change to D/M/Y use anything but "US" for
    // $locale.
    //
    // Y-M-D is allowed if the delimiter is "-" instead of "/"
    //
    // In order to help this function guess a two digit year a "past" or "future" flag is
    // passed to this function.  If no flag is passed the function assumes that two digit
    // years are in the future (or the current year).
    //
    // Month and day may be either 1 character or two characters (leading zeroes are not
    // necessary)

    // Determine if the delimiter is "-" or "/".  The delimiter must appear
    // twice or a FALSE will be returned.

    if (mb_substr_count($data, '-') === 2) {
        // Assume format is Y-M-D
        $iFirstDelimiter = strpos($data, '-');
        $iSecondDelimiter = strpos($data, '-', $iFirstDelimiter + 1);

        // Parse the year.
        $sYear = mb_substr($data, 0, $iFirstDelimiter);

        // Parse the month
        $sMonth = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);

        // Parse the day
        $sDay = mb_substr($data, $iSecondDelimiter + 1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
    } elseif ((mb_substr_count($data, '/') == 2) && ($locale == 'US')) {
        // Assume format is M/D/Y
        $iFirstDelimiter = strpos($data, '/');
        $iSecondDelimiter = strpos($data, '/', $iFirstDelimiter + 1);

        // Parse the month
        $sMonth = mb_substr($data, 0, $iFirstDelimiter);

        // Parse the day
        $sDay = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);

        // Parse the year
        $sYear = mb_substr($data, $iSecondDelimiter + 1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
    } elseif (mb_substr_count($data, '/') == 2) {
        // Assume format is D/M/Y
        $iFirstDelimiter = strpos($data, '/');
        $iSecondDelimiter = strpos($data, '/', $iFirstDelimiter + 1);

        // Parse the day
        $sDay = mb_substr($data, 0, $iFirstDelimiter);

        // Parse the month
        $sMonth = mb_substr($data, $iFirstDelimiter + 1, $iSecondDelimiter - $iFirstDelimiter - 1);

        // Parse the year
        $sYear = mb_substr($data, $iSecondDelimiter + 1);

        // Put into YYYY-MM-DD form
        return assembleYearMonthDay($sYear, $sMonth, $sDay, $pasfut);
    }

    // If we made it this far it means the above logic was unable to parse the date.
    // Now try to parse using the function strtotime().  The strtotime() function does
    // not gracefully handle dates outside the range 1/1/1970 to 1/19/2038.  For this
    // reason consider strtotime() as a function of last resort.
    $timeStamp = strtotime($data);
    if ($timeStamp == false || $timeStamp <= 0) {
        // Some Operating Systems and older versions of PHP do not gracefully handle
        // negative timestamps.  Bail if the timestamp is negative.
        return false;
    }

    // Now use the date() function to convert timestamp into YYYY-MM-DD
    $dateString = date('Y-m-d', $timeStamp);

    if (strlen($dateString) !== 10) {
        // Common sense says we have a 10 character string.  If not, something is wrong
        // and it's time to bail.
        return false;
    }

    if ($dateString > '1970-01-01' && $dateString < '2038-01-19') {
        // Success!
        return $dateString;
    }

    // Should not have made it this far.  Something is wrong so bail.
    return false;
}

// Processes and Validates custom field data based on its type.
//
// Returns false if the data is not valid, true otherwise.
//
function validateCustomField($type, &$data, $col_Name, ?array &$aErrors): bool
{
    global $aLocaleInfo;
    $bErrorFlag = false;
    $aErrors[$col_Name] = '';

    switch ($type) {
    // Validate a date field
        case 2:
            // this part will work with each date format
            $data = InputUtils::filterDate($data);

            if (strlen($data) > 0) {
                $dateString = parseAndValidateDate($data);
                if ($dateString === false) {
                    $aErrors[$col_Name] = gettext('Not a valid date');
                    $bErrorFlag = true;
                } else {
                    $data = $dateString;
                }
            }
            break;

    // Handler for 4-digit year
        case 6:
            if (strlen($data) !== 0) {
                if (!is_numeric($data) || strlen($data) !== 4 || $data < 0) {
                    $aErrors[$col_Name] = gettext('Invalid Year');
                    $bErrorFlag = true;
                }
            }
            break;

    // Handler for integer numbers
        case 8:
            if (strlen($data) !== 0) {
                if ($aLocaleInfo['thousands_sep']) {
                    $data = preg_replace('/' . $aLocaleInfo['thousands_sep'] . '/i', '', $data);  // remove any thousands separators
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

    // Handler for money amounts
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

    // Otherwise ignore.. some types do not need validation or filtering
        default:
            break;
    }

    return !$bErrorFlag;
}

// Generates SQL for custom field update
//
// $special is currently only used for the phone country and the list ID for custom drop-down choices.
//
function sqlCustomField(string &$sSQL, $type, $data, string $col_Name, $special): void
{
    switch ($type) {
    // boolean
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

    // date
        case 2:
            if (strlen($data) > 0) {
                $sSQL .= $col_Name . ' = "' . $data . '", ';
            } else {
                $sSQL .= $col_Name . ' = NULL, ';
            }
            break;

    // year
        case 6:
        case 10:
        case 5:
            if (strlen($data) > 0) {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            } else {
                $sSQL .= $col_Name . ' = NULL, ';
            }
            break;

    // season
        case 7:
            if ($data != 'none') {
                $sSQL .= $col_Name . " = '" . $data . "', ";
            } else {
                $sSQL .= $col_Name . ' = NULL, ';
            }
            break;

    // integer, money
        case 8:
    // list selects
        case 9:
        case 12:
            // PHP 8 changed loose comparison: '' != 0 is now true (was false in PHP 7)
            // Validate as proper integer to avoid truncation (e.g. '12abc' -> 12)
            $rawValue = is_string($data) ? trim($data) : $data;
            if ($rawValue === '' || $rawValue === null) {
                $sSQL .= $col_Name . ' = NULL, ';
            } else {
                $validatedInt = filter_var($rawValue, FILTER_VALIDATE_INT);
                if ($validatedInt === false || $validatedInt === 0) {
                    // Invalid integer or zero: treat as no value (NULL) to preserve legacy semantics
                    $sSQL .= $col_Name . ' = NULL, ';
                } else {
                    $sSQL .= $col_Name . " = '" . $validatedInt . "', ";
                }
            }
            break;

    // strings
        case 3:
        case 4:
    // phone
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

function FilenameToFontname(string $filename, string $family): string
{
    if ($filename == $family) {
        return ucfirst($family);
    } else {
        if (strlen($filename) - strlen($family) === 2) {
            return ucfirst($family) . gettext(' Bold Italic');
        } else {
            if (mb_substr($filename, strlen($filename) - 1) === 'i') {
                return ucfirst($family) . gettext(' Italic');
            } else {
                return ucfirst($family) . gettext(' Bold');
            }
        }
    }
}

function FontFromName(string $fontname)
{
    $fontinfo = explode(' ', $fontname);
    switch (count($fontinfo)) {
        case 1:
            return [$fontinfo[0], ''];
        case 2:
            return [$fontinfo[0], mb_substr($fontinfo[1], 0, 1)];
        case 3:
            return [$fontinfo[0], mb_substr($fontinfo[1], 0, 1) . mb_substr($fontinfo[2], 0, 1)];
    }
}

// Figure out the class ID for "Member", should be one (1) unless they have been playing with the
// classification manager.
function FindMemberClassID()
{
    //Get Classifications
    $sSQL = 'SELECT * FROM list_lst WHERE lst_ID = 1 ORDER BY lst_OptionSequence';
    $rsClassifications = RunQuery($sSQL);

    while ($aRow = mysqli_fetch_array($rsClassifications)) {
        extract($aRow);
        if ($lst_OptionName == gettext('Member')) {
            return $lst_OptionID;
        }
    }

    return 1; // Should not get here, but if we do get here use the default value.
}

//Function to check email
//From http://www.tienhuis.nl/php-email-address-validation-with-verify-probe
//Functions checkndsrr and getmxrr are not enabled on windows platforms & therefore are disabled
//Future use may be to enable a Admin option to enable these options
//domainCheck verifies domain is valid using dns, verify uses SMTP to verify actual account exists on server

function checkEmail($email, $domainCheck = false, $verify = false, $return_errors = false)
{
    global $checkEmailDebug;
    if ($checkEmailDebug) {
        echo '<pre>';
    }
    // Check syntax with regex
    if (preg_match('/^([a-zA-Z0-9\._\+-]+)\@((\[?)[a-zA-Z0-9\-\.]+\.([a-zA-Z]{2,7}|[0-9]{1,3})(\]?))$/', $email, $matches)) {
        $user = $matches[1];
        $domain = $matches[2];
        // Check availability of DNS MX records
        if ($domainCheck && function_exists('checkdnsrr')) {
            // Construct array of available mailservers
            if (getmxrr($domain, $mxhosts, $mxweight)) {
                for ($i = 0; $i < count($mxhosts); $i++) {
                    $mxs[$mxhosts[$i]] = $mxweight[$i];
                }
                asort($mxs);
                $mailers = array_keys($mxs);
            } elseif (checkdnsrr($domain, 'A')) {
                $mailers[0] = gethostbyname($domain);
            } else {
                $mailers = [];
            }
            $total = count($mailers);
            // Query each mailserver
            if ($total > 0 && $verify) {
                // Check if mailers accept mail
                for ($n = 0; $n < $total; $n++) {
                    // Check if socket can be opened
                    if ($checkEmailDebug) {
                        echo "Checking server $mailers[$n]...\n";
                    }
                    $connect_timeout = 2;
                    $errno = 0;
                    $errstr = 0;
                    $probe_address = SystemConfig::getValue('sToEmailAddress');
                    // Try to open up socket
                    if ($sock = @fsockopen($mailers[$n], 25, $errno, $errstr, $connect_timeout)) {
                        $response = fgets($sock);
                        if ($checkEmailDebug) {
                            echo "Opening up socket to $mailers[$n]... Success!\n";
                        }
                        stream_set_timeout($sock, 5);
                        $meta = stream_get_meta_data($sock);
                        if ($checkEmailDebug) {
                            echo "$mailers[$n] replied: $response\n";
                        }
                        $cmds = [
                        'HELO ' . SystemConfig::getValue('sSMTPHost'), // Be sure to set this correctly!
                        "MAIL FROM: <$probe_address>",
                        "RCPT TO: <$email>",
                        'QUIT',
                        ];
                        // Hard error on connect -> break out
                        if (!$meta['timed_out'] && !preg_match('/^2\d\d[ -]/', $response)) {
                            $error = "Error: $mailers[$n] said: $response\n";
                            break;
                        }
                        foreach ($cmds as $cmd) {
                            $before = microtime(true);
                            fwrite($sock, "$cmd\r\n");
                            $response = fgets($sock, 4096);
                            $t = 1000 * (microtime(true) - $before);
                            if ($checkEmailDebug) {
                                echo htmlentities("$cmd\n$response") . '(' . sprintf('%.2f', $t) . " ms)\n";
                            }
                            if (!$meta['timed_out'] && preg_match('/^5\d\d[ -]/', $response)) {
                                $error = "Unverified address: $mailers[$n] said: $response";
                                break 2;
                            }
                        }
                        fclose($sock);
                        if ($checkEmailDebug) {
                            echo "Successful communication with $mailers[$n], no hard errors, assuming OK";
                        }
                        break;
                    } elseif ($n == $total - 1) {
                        $error = "None of the mailservers listed for $domain could be contacted";
                    }
                }
            } elseif ($total <= 0) {
                $error = "No usable DNS records found for domain '$domain'";
            }
        }
    } else {
        $error = 'Address syntax not correct';
    }
    if ($checkEmailDebug) {
        echo '</pre>';
    }
    //echo "</pre>";
    if ($return_errors) {
        // Give back details about the error(s).
        // Return FALSE if there are no errors.
        // Keep this in mind when using it like:
        // if(checkEmail($addr)) {
        // Because of this strange behaviour this
        // is not default ;-)
        if (isset($error)) {
            return htmlentities($error);
        } else {
            return false;
        }
    } else {
        // 'Old' behaviour, simple to understand
        if (isset($error)) {
            return false;
        } else {
            return true;
        }
    }
}



function genGroupKey(string $methodSpecificID, string $famID, string $fundIDs, string $date)
{
    $uniqueNum = 0;
    while (1) {
        $GroupKey = $methodSpecificID . '|' . $uniqueNum . '|' . $famID . '|' . $fundIDs . '|' . $date;
        $sSQL = "SELECT COUNT(plg_GroupKey) FROM pledge_plg WHERE plg_PledgeOrPayment='Payment' AND plg_GroupKey='" . $GroupKey . "'";
        $rsResults = RunQuery($sSQL);
        [$numGroupKeys] = mysqli_fetch_row($rsResults);
        if ($numGroupKeys) {
            ++$uniqueNum;
        } else {
            return $GroupKey;
        }
    }
}

function random_color(): string
{
    return bin2hex(random_bytes(3));
}

function generateGroupRoleEmailDropdown(array $roleEmails, string $href): void
{
    $sMailtoDelimiter = AuthenticationManager::getCurrentUser()->getUserConfigString("sMailtoDelimiter");
    foreach ($roleEmails as $role => $Email) {
        if (SystemConfig::getValue('sToEmailAddress') != '' && !stristr($Email, (string) SystemConfig::getValue('sToEmailAddress'))) {
            $Email .= $sMailtoDelimiter . SystemConfig::getValue('sToEmailAddress');
        }
        $Email = urlencode($Email);  // Mailto should comply with RFC 2368
        ?>
      <a class="dropdown-item" href="<?= $href . mb_substr($Email, 0, -3) ?>"><?=$role?></a>
        <?php
    }
}

?>
