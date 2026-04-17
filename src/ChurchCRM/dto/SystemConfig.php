<?php

namespace ChurchCRM\dto;

use ChurchCRM\data\Countries;
use ChurchCRM\model\ChurchCRM\Config;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Utils\InputUtils;
use Exception;
use Monolog\Level;

class   SystemConfig
{
    /**
     * @var \ChurchCRM\model\ChurchCRM\Config[]|null
     */
    private static ?array $configs = null;

    /**
     * @var array<string, string[]>
     */
    private static ?array $categories = null;

    private static function getSupportedLocales(): array
    {
        $localesFile = file_get_contents(SystemURLs::getDocumentRoot() . '/locale/locales.json');
        $locales = json_decode($localesFile, true, 512, JSON_THROW_ON_ERROR);
        $languagesChoices = [];
        foreach ($locales as $key => $value) {
            $languagesChoices[] = gettext($key) . ':' . $value['locale'];
        }

        return ['Choices' => $languagesChoices];
    }

    public static function getMonoLogLevels(): array
    {
        return [
            'Choices' => [
                'DEBUG:' . Level::Debug->value,
                'INFO:' . Level::Info->value,
                'WARNING:' . Level::Warning->value,
                'ERROR:' . Level::Error->value,
            ],
        ];
    }

    private static function getSmtpEncryptionChoices(): array
    {
        return ['Choices' => [gettext('None') . ': ', 'TLS:tls', 'SSL:ssl']];
    }

    private static function getMonthChoices(): array
    {
        return [
            'Choices' => [
                gettext('January') . ':1', gettext('February') . ':2', gettext('March') . ':3',
                gettext('April') . ':4', gettext('May') . ':5', gettext('June') . ':6',
                gettext('July') . ':7', gettext('August') . ':8', gettext('September') . ':9',
                gettext('October') . ':10', gettext('November') . ':11', gettext('December') . ':12',
            ],
        ];
    }

    private static function getMapZoomChoices(): array
    {
        return [
            'Choices' => [
                gettext('Continent') . ':3', gettext('Country') . ':5', gettext('State') . ':7',
                gettext('City') . ':10', gettext('Neighborhood') . ':14', gettext('Street') . ':18',
            ],
        ];
    }

    public static function getNameChoices(): array
    {
        return [
            'Choices' => [
                gettext('Title FirstName MiddleName LastName') . ':0',
                gettext('Title FirstName MiddleInitial. LastName') . ':1',
                gettext('LastName, Title FirstName MiddleName') . ':2',
                gettext('LastName, Title FirstName MiddleInitial') . ':3',
                gettext('FirstName MiddleName LastName') . ':4',
                gettext('Title FirstName LastName') . ':5',
                gettext('LastName, Title FirstName') . ':6',
                gettext('LastName FirstName') . ':7',
                gettext('LastName, FirstName MiddleName') . ':8',
            ],
        ];
    }

    public static function getFamilyRoleChoices(): array
    {
        $roles = [];

        try {
            $familyRoles = ListOptionQuery::create()->getFamilyRoles();

            foreach ($familyRoles as $familyRole) {
                $roles[] = $familyRole->getOptionName() . ':' . $familyRole->getOptionId();
            }
        } catch (Exception $e) {
        }

        return ['Choices' => $roles];
    }

    public static function getInitialStyleChoices(): array
    {
        return [
            'Choices' => [
                gettext('One character from FirstName and one character from LastName') . ':0',
                gettext('Two characters from FirstName') . ':1',
            ],
        ];
    }

    private static function buildConfigs(): array
    {
        return [
            'sLogLevel'                            => new ConfigItem('sLogLevel', 'choice', '200', gettext('Event Log severity to write, used by ORM and App Logs'), '', json_encode(SystemConfig::getMonoLogLevels(), JSON_THROW_ON_ERROR)),
            'sDirClassifications'                  => new ConfigItem('sDirClassifications', 'text', '1,2,4,5', gettext('Include only these classifications in the directory, comma separated')),
            'sDirRoleHead'                         => new ConfigItem('sDirRoleHead', 'choice', '1', gettext('These are the family role numbers designated as head of house'), '', json_encode(SystemConfig::getFamilyRoleChoices(), JSON_THROW_ON_ERROR)),
            'sDirRoleSpouse'                       => new ConfigItem('sDirRoleSpouse', 'choice', '2', gettext('These are the family role numbers designated as spouse'), '', json_encode(SystemConfig::getFamilyRoleChoices(), JSON_THROW_ON_ERROR)),
            'sDirRoleChild'                        => new ConfigItem('sDirRoleChild', 'choice', '3', gettext('These are the family role numbers designated as child'), '', json_encode(SystemConfig::getFamilyRoleChoices(), JSON_THROW_ON_ERROR)),
            'iSessionTimeout'                      => new ConfigItem('iSessionTimeout', 'number', '3600', gettext("Session timeout length in seconds\nSet to zero to disable session timeouts.")),
            'aFinanceQueries'                      => new ConfigItem('aFinanceQueries', 'text', '28,30', gettext('Comma-separated query IDs that require finance permissions to view')),
            'iMinPasswordLength'                   => new ConfigItem('iMinPasswordLength', 'number', '8', gettext('Minimum length a user may set their password to')),
            'iMinPasswordChange'                   => new ConfigItem('iMinPasswordChange', 'number', '4', gettext("Minimum amount that a new password must differ from the old one (# of characters changed)\nSet to zero to disable this feature")),
            'aDisallowedPasswords'                 => new ConfigItem('aDisallowedPasswords', 'text', 'password,god,jesus,church,christian', gettext('A comma-separated list of disallowed (too obvious) passwords.')),
            'iMaxFailedLogins'                     => new ConfigItem('iMaxFailedLogins', 'number', '5', gettext("Maximum number of failed logins to allow before a user account is locked.\nOnce the maximum has been reached, an administrator must re-enable the account.\nThis feature helps to protect against automated password guessing attacks.\nSet to zero to disable this feature.")),
            'iPDFOutputType'                       => new ConfigItem('iPDFOutputType', 'number', '1', gettext("PDF handling mode.\n1 = Save File dialog\n2 = Open in current browser window")),
            'sDefaultCity'                         => new ConfigItem('sDefaultCity', 'text', '', gettext('Default City')),
            'sDefaultState'                        => new ConfigItem('sDefaultState', 'text', '', gettext('Default State - Must be 2-letter abbreviation!')),
            'sDefaultCountry'                      => new ConfigItem('sDefaultCountry', 'choice', '', '', '', json_encode(['Choices' => Countries::getNames()], JSON_THROW_ON_ERROR)),
            'sToEmailAddress'                      => new ConfigItem('sToEmailAddress', 'text', '', gettext('Default account for receiving a copy of all emails')),
            'iSMTPTimeout'                         => new ConfigItem('iSMTPTimeout', 'number', '10', gettext('SMTP Server timeout in sec')),
            'sSMTPHost'                            => new ConfigItem('sSMTPHost', 'text', '', gettext('SMTP Server Address (mail.server.com:25)')),
            'bSMTPAuth'                            => new ConfigItem('bSMTPAuth', 'boolean', '0', gettext('Enable if your SMTP server requires a username and password')),
            'sSMTPUser'                            => new ConfigItem('sSMTPUser', 'text', '', gettext('SMTP Username')),
            'sSMTPPass'                            => new ConfigItem('sSMTPPass', 'password', '', gettext('SMTP Password')),
            'sLanguage'                            => new ConfigItem('sLanguage', 'choice', 'en_US', gettext('Internationalization (I18n) support'), 'https://poeditor.com/join/project?hash=RABdnDSqAt', json_encode(SystemConfig::getSupportedLocales(), JSON_THROW_ON_ERROR)),
            'iFYMonth'                             => new ConfigItem('iFYMonth', 'choice', '1', gettext('The month that starts your organization\'s fiscal year'), '', json_encode(SystemConfig::getMonthChoices(), JSON_THROW_ON_ERROR)),
            'iMapZoom'                             => new ConfigItem('iMapZoom', 'choice', '10', gettext('Initial zoom level when opening the map'), '', json_encode(SystemConfig::getMapZoomChoices(), JSON_THROW_ON_ERROR)),
            'iChurchLatitude'                      => new ConfigItem('iChurchLatitude', 'number', '', ''),
            'iChurchLongitude'                     => new ConfigItem('iChurchLongitude', 'number', '', ''),
            'bHidePersonAddress'                   => new ConfigItem('bHidePersonAddress', 'boolean', '1', gettext('When enabled, hides the address field for people not assigned to a family')),
            'bHideFriendDate'                      => new ConfigItem('bHideFriendDate', 'boolean', '0', gettext('Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.')),
            'bHideFamilyNewsletter'                => new ConfigItem('bHideFamilyNewsletter', 'boolean', '0', gettext('Set true to disable management of newsletter subscriptions in the Family Editor.')),
            'bHideWeddingDate'                     => new ConfigItem('bHideWeddingDate', 'boolean', '0', gettext('Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.')),
            'bHideLatLon'                          => new ConfigItem('bHideLatLon', 'boolean', '0', gettext('When enabled, hides the latitude/longitude fields in the Family Editor. Geocoding still runs in the background.')),
            'bUseDonationEnvelopes'                => new ConfigItem('bUseDonationEnvelopes', 'boolean', '0', gettext('Enable the use of numbered donation envelopes for tracking contributions')),
            'iChecksPerDepositForm'                => new ConfigItem('iChecksPerDepositForm', 'number', '14', gettext('How many checks to print per deposit slip page')),
            'bUseScannedChecks'                    => new ConfigItem('bUseScannedChecks', 'boolean', '0', gettext('Allow scanned check images to be attached to deposit records')),
            'sDistanceUnit'                        => new ConfigItem('sDistanceUnit', 'choice', 'miles', gettext('Unit used to measure distance, miles or km.'), '', '{"Choices":["' . gettext('miles') . '","' . gettext('kilometers') . '"]}'),
            'sTimeZone'                            => new ConfigItem('sTimeZone', 'choice', 'America/New_York', gettext('Time zone'), 'https://www.php.net/manual/en/timezones.php', json_encode(['Choices' => timezone_identifiers_list()], JSON_THROW_ON_ERROR)),
            'bForceUppercaseZip'                   => new ConfigItem('bForceUppercaseZip', 'boolean', '0', gettext('Make user-entered zip/postcodes UPPERCASE when saving to the database.')),
            'bEnableNonDeductible'                 => new ConfigItem('bEnableNonDeductible', 'boolean', '0', gettext('Allow recording of non-tax-deductible payments in the finance module')),
            'bEnableSelfRegistration'              => new ConfigItem('bEnableSelfRegistration', 'boolean', '0', gettext('Allow visitors to create their own family record via the self-registration page')),
            'sPhoneFormat'                         => new ConfigItem('sPhoneFormat', 'text', '(999) 999-9999'),
            'sPhoneFormatWithExt'                  => new ConfigItem('sPhoneFormatWithExt', 'text', '(999) 999-9999 x99999'),
            'sPhoneFormatCell'                     => new ConfigItem('sPhoneFormatCell', 'text', '(999) 999-9999'),
            'sDateFormatLong'                      => new ConfigItem('sDateFormatLong', 'text', 'm/d/Y'),
            'sDateFormatNoYear'                    => new ConfigItem('sDateFormatNoYear', 'text', 'm/d'),
            'sDateTimeFormat'                      => new ConfigItem('sDateTimeFormat', 'text', 'm/d/Y g:i a'),
            'sDateFilenameFormat'                  => new ConfigItem('sDateFilenameFormat', 'text', 'Ymd-Gis'),
            'sDatePickerPlaceHolder'               => new ConfigItem('sDatePickerPlaceHolder', 'text', 'yyyy-mm-dd', gettext('For defining the date in Date-Picker, per default : yyyy-mm-dd, In French : dd/mm/yyyy for example.')),
            'sDatePickerFormat'                    => new ConfigItem('sDatePickerFormat', 'text', 'Y-m-d', gettext('For defining the date in Date-Picker, per default : Y-m-d, In French : d/m/Y for example.')),
            'leftX'                                => new ConfigItem('leftX', 'number', '20', gettext('Left Margin (1 = 1/100th inch)')),
            'incrementY'                           => new ConfigItem('incrementY', 'number', '4', gettext('Line Thickness (1 = 1/100th inch')),
            'sChurchName'                          => new ConfigItem('sChurchName', 'text', '', ''),
            'sChurchAddress'                       => new ConfigItem('sChurchAddress', 'text', '', ''),
            'sChurchCity'                          => new ConfigItem('sChurchCity', 'text', '', ''),
            'sChurchState'                         => new ConfigItem('sChurchState', 'text', '', ''),
            'sChurchZip'                           => new ConfigItem('sChurchZip', 'text', '', ''),
            'sChurchPhone'                         => new ConfigItem('sChurchPhone', 'text', '', ''),
            'sChurchEmail'                         => new ConfigItem('sChurchEmail', 'text', '', ''),
            
            'sTaxReport1'                          => new ConfigItem('sTaxReport1', 'text', 'This letter shows our record of your payments for', gettext('Verbage for top line of tax report. Dates will be appended to the end of this line.')),
            'sTaxReport2'                          => new ConfigItem('sTaxReport2', 'text', 'Thank you for your help in making a difference. We greatly appreciate your gift!', gettext('Verbage for bottom line of tax report.')),
            'sTaxReport3'                          => new ConfigItem('sTaxReport3', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', gettext('Verbage for bottom line of tax report.')),
            'sTaxSigner'                           => new ConfigItem('sTaxSigner', 'text', '', gettext('Tax Report signer')),
            'sReminder1'                           => new ConfigItem('sReminder1', 'text', 'This letter shows our record of your pledge and payments for fiscal year', gettext('Verbage for the pledge reminder report')),
            'sReminderSigner'                      => new ConfigItem('sReminderSigner', 'text', '', gettext('Pledge Reminder Signer')),
            'sReminderNoPledge'                    => new ConfigItem('sReminderNoPledge', 'text', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', gettext('Verbage for the pledge reminder report - No record of a pledge')),
            'sReminderNoPayments'                  => new ConfigItem('sReminderNoPayments', 'text', 'Payments: We do not have record of a pledge for from you for this fiscal year.', gettext('Verbage for the pledge reminder report - No record of payments')),
            'sConfirm1'                            => new ConfigItem('sConfirm1', 'text', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm2'                            => new ConfigItem('sConfirm2', 'text', 'Thank you very much for helping us to update this information.', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm3'                            => new ConfigItem('sConfirm3', 'text', '', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm4'                            => new ConfigItem('sConfirm4', 'text', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm5'                            => new ConfigItem('sConfirm5', 'text', '', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm6'                            => new ConfigItem('sConfirm6', 'text', '', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirmSigner'                       => new ConfigItem('sConfirmSigner', 'text', '', gettext('Database information confirmation and correction report signer')),
            'sPledgeSummary1'                      => new ConfigItem('sPledgeSummary1', 'text', 'Summary of pledges and payments for the fiscal year', gettext('Verbage for the pledge summary report')),
            'sPledgeSummary2'                      => new ConfigItem('sPledgeSummary2', 'text', ' as of', gettext('Verbage for the pledge summary report')),
            'sDirectoryDisclaimer1'                => new ConfigItem('sDirectoryDisclaimer1', 'text', "Every effort was made to ensure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of", gettext('Verbage for the directory report')),
            'sDirectoryDisclaimer2'                => new ConfigItem('sDirectoryDisclaimer2', 'text', ', and the information contained in it may not be used for business or commercial purposes.', gettext('Verbage for the directory report')),
            'bDirLetterHead'                       => new ConfigItem('bDirLetterHead', 'text', '../Images/church_letterhead.jpg', gettext('Church Letterhead path and file')),
            'sZeroGivers'                          => new ConfigItem('sZeroGivers', 'text', 'This letter shows our record of your payments for', gettext('Verbage for top line of tax report. Dates will be appended to the end of this line.')),
            'sZeroGivers2'                         => new ConfigItem('sZeroGivers2', 'text', 'Thank you for your help in making a difference. We greatly appreciate your gift!', gettext('Verbage for bottom line of tax report.')),
            'sZeroGivers3'                         => new ConfigItem('sZeroGivers3', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', gettext('Verbage for bottom line of tax report.')),
            'sChurchChkAcctNum'                    => new ConfigItem('sChurchChkAcctNum', 'text', '', gettext('Church Checking Account Number')),
            'sQBDTSettings'                        => new ConfigItem('sQBDTSettings', 'json', '{"date1":{"x":"12","y":"42"},"date2X":"185","leftX":"64","topY":"7","perforationY":"97","amountOffsetX":"35","lineItemInterval":{"x":"49","y":"7"},"max":{"x":"200","y":"140"},"numberOfItems":{"x":"136","y":"68"},"subTotal":{"x":"197","y":"42"},"topTotal":{"x":"197","y":"68"},"titleX":"85"}', gettext('QuickBooks Deposit Ticket Settings')),
            'sChurchCountry'                       => new ConfigItem('sChurchCountry', 'choice', '', '', '', json_encode(['Choices' => Countries::getNames()], JSON_THROW_ON_ERROR)),
            'sConfirmSincerely'                    => new ConfigItem('sConfirmSincerely', 'text', 'Sincerely', gettext('Used to end a letter before Signer')),
            'sDear'                                => new ConfigItem('sDear', 'text', 'Dear', gettext('Text before name in emails/reports')),
            'sDepositSlipType'                     => new ConfigItem('sDepositSlipType', 'choice', 'QBDT', gettext('Deposit ticket type'), '', '{"Choices":["QBDT (QuickBooks):QBDT"]}'),
            'iPersonNameStyle'                     => new ConfigItem('iPersonNameStyle', 'choice', '4', '', '', json_encode(SystemConfig::getNameChoices(), JSON_THROW_ON_ERROR)),
            'iPersonInitialStyle'                  => new ConfigItem('iPersonInitialStyle', 'choice', '0', '', '', json_encode(SystemConfig::getInitialStyleChoices(), JSON_THROW_ON_ERROR)),
            'bDisplayBillCounts'                   => new ConfigItem('bDisplayBillCounts', 'boolean', '1', gettext('Show a breakdown of bill denominations on the deposit slip report')),
            'sKioskVisibilityTimestamp'            => new ConfigItem('sKioskVisibilityTimestamp', 'text', '', gettext('KioskVisibilityTimestamp')),
            'bEnableLostPassword'                  => new ConfigItem('bEnableLostPassword', 'boolean', '1', gettext('Show/Hide Lost Password Link on the login screen')),
            'sChurchWebSite'                       => new ConfigItem('sChurchWebSite', 'text', '', ''),
            'bEnableExternalCalendarAPI'           => new ConfigItem('bEnableExternalCalendarAPI', 'boolean', '0', gettext('Allow unauthenticated reads of events from the external calendar API')),
            
            'sNewPersonNotificationRecipientIDs'   => new ConfigItem('sNewPersonNotificationRecipientIDs', 'text', '', gettext('Comma Separated list of PersonIDs of people to notify when a new family or person is added')),
            'bSearchIncludePersons'                => new ConfigItem('bSearchIncludePersons', 'boolean', '1', gettext('Search People')),
            'bSearchIncludeFamilies'               => new ConfigItem('bSearchIncludeFamilies', 'boolean', '1', gettext('Search Family')),
            'bSearchIncludeFamilyHOH'              => new ConfigItem('bSearchIncludeFamilyHOH', 'boolean', '1', gettext('Show Family Head of House Names')),
            'bSearchIncludeGroups'                 => new ConfigItem('bSearchIncludeGroups', 'boolean', '1', gettext('Search Groups')),
            'bSearchIncludeDeposits'               => new ConfigItem('bSearchIncludeDeposits', 'boolean', '1', gettext('Search Deposits')),
            'bSearchIncludePayments'               => new ConfigItem('bSearchIncludePayments', 'boolean', '1', gettext('Search Payments')),
            'bSearchIncludeAddresses'              => new ConfigItem('bSearchIncludeAddresses', 'boolean', '1', gettext('Search Addresses')),
            'bSearchIncludePersonsMax'             => new ConfigItem('bSearchIncludePersonsMax', 'text', '15', gettext('Maximum number of People')),
            'bSearchIncludeFamiliesMax'            => new ConfigItem('bSearchIncludeFamiliesMax', 'text', '15', gettext('Maximum number of Families')),
            'bSearchIncludeFamilyHOHMax'           => new ConfigItem('bSearchIncludeFamilyHOHMax', 'text', '15', gettext('Maximum number of Family H.O.H Names')),
            'bSearchIncludeGroupsMax'              => new ConfigItem('bSearchIncludeGroupsMax', 'text', '15', gettext('Maximum number of Groups')),
            'bSearchIncludeDepositsMax'            => new ConfigItem('bSearchIncludeDepositsMax', 'text', '5', gettext('Maximum number of Deposits')),
            'bSearchIncludePaymentsMax'            => new ConfigItem('bSearchIncludePaymentsMax', 'text', '5', gettext('Maximum number of Payments')),
            'bSearchIncludeAddressesMax'           => new ConfigItem('bSearchIncludeAddressesMax', 'text', '15', gettext('Maximum number of Addresses')),
            'iPersonConfessionFatherCustomField'   => new ConfigItem('iPersonConfessionFatherCustomField', 'ajax', '', gettext('Field where Father Of Confession is listed, must be a people of group type'), '', '/api/system/custom-fields/person/?typeId=9'),
            'iPersonConfessionDateCustomField'     => new ConfigItem('iPersonConfessionDateCustomField', 'ajax', '', gettext('Field where last Confession is stored, must be a date type'), '', '/api/system/custom-fields/person/?typeId=2'),
            'iDoNotEmailPropertyId'                => new ConfigItem('iDoNotEmailPropertyId', 'ajax', '', gettext('Person property used to exclude members from email lists'), '', '/api/system/properties/person'),
            'iDoNotSmsPropertyId'                  => new ConfigItem('iDoNotSmsPropertyId', 'ajax', '', gettext('Person property used to exclude members from SMS/text lists'), '', '/api/system/properties/person'),
            'bEnforceCSP'                          => new ConfigItem('bEnforceCSP', 'boolean', '0', gettext('Enforce Content Security Policy (CSP) to help protect against cross-site scripting. When disabled, CSP violations are only reported.')),
            'bPHPMailerAutoTLS'                    => new ConfigItem('bPHPMailerAutoTLS', 'boolean', '0', gettext('Automatically enable SMTP encryption if offered by the relaying server.')),
            'sPHPMailerSMTPSecure'                 => new ConfigItem('sPHPMailerSMTPSecure', 'choice', ' ', gettext('Set the encryption system to use - ssl (deprecated) or tls'), '', json_encode(SystemConfig::getSmtpEncryptionChoices(), JSON_THROW_ON_ERROR)),
            'bEnabledSundaySchool'                 => new ConfigItem('bEnabledSundaySchool', 'boolean', '1', gettext('Show or hide the Sunday School module in the sidebar navigation')),
            'bEnabledFinance'                      => new ConfigItem('bEnabledFinance', 'boolean', '1', gettext('Enable Finance menu')),
            'bEnabledEvents'                       => new ConfigItem('bEnabledEvents', 'boolean', '1', gettext('Show or hide the Events section in the main navigation menu')),
            'bEnabledFundraiser'                   => new ConfigItem('bEnabledFundraiser', 'boolean', '1', gettext('Enable Fundraiser menu.')),
            'bEnabledEmail'                        => new ConfigItem('bEnabledEmail', 'boolean', '1', gettext('Enable email sending from ChurchCRM. Required for password reset, notifications, and email links.')),
            'sEmailPreheader'                      => new ConfigItem('sEmailPreheader', 'text', '', gettext('Optional short summary shown as inbox preview text beside the subject line. Leave blank to let the email client auto-generate from the body. Per-email types (password reset, new member, verification) set their own preheader; this is a fallback.')),
            'sNotificationsURL'                    => new ConfigItem('sNotificationsURL', 'text', 'https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/notifications.json', gettext('ChurchCRM Central Notifications URL')),
            'sGreeterCustomMsg1'                   => new ConfigItem('sGreeterCustomMsg1', 'text', '', gettext('Custom message for church greeter email 1, max 255 characters')),
            'sGreeterCustomMsg2'                   => new ConfigItem('sGreeterCustomMsg2', 'text', '', gettext('Custom message for church greeter email 2, max 255 characters')),
            'IncludeDataInNewPersonNotifications'  => new ConfigItem('IncludeDataInNewPersonNotifications', 'boolean', '0', gettext('Include contact and demographic data in new member email notification body')),
            'bSearchIncludeFamilyCustomProperties' => new ConfigItem('bSearchIncludeFamilyCustomProperties', 'boolean', '0', gettext('Include family custom properties in global search.')),
            'bAllowPrereleaseUpgrade'              => new ConfigItem('bAllowPrereleaseUpgrade', 'boolean', '0', gettext("Allow system upgrades to releases marked as 'pre release' on GitHub")),
            'bSearchIncludeCalendarEvents'         => new ConfigItem('bSearchIncludeCalendarEvents', 'boolean', '1', gettext('Search Calendar Events')),
            'bSearchIncludeCalendarEventsMax'      => new ConfigItem('bSearchIncludeCalendarEventsMax', 'text', '15', gettext('Maximum number of Calendar Events')),
            'bRequire2FA'                          => new ConfigItem('bRequire2FA', 'boolean', '0', gettext('Require all users to enroll in two-factor authentication')),
            's2FAApplicationName'                  => new ConfigItem('s2FAApplicationName', 'text', 'ChurchCRM', gettext('Specify the application name to be displayed in authenticator app')),
            'sTwoFASecretKey'                      => new ConfigItem('sTwoFASecretKey', 'password', '', gettext('Encryption key for storing 2FA secret keys in the database')),
            'bSendUserDeletedEmail'                => new ConfigItem('bSendUserDeletedEmail', 'boolean', '0', gettext('Send an email notifying users when their account has been deleted')),
            'sInactiveClassification'              => new ConfigItem('sInactiveClassification', 'text', '', gettext('Comma separated list of classifications that should appear as inactive')),
            'sDefaultZip'                          => new ConfigItem('sDefaultZip', 'text', '', gettext('Default Zip')),
        ];
    }

    private static function buildCategories(): array
    {
        return [
            gettext('New Members & Greeting') => ['sNewPersonNotificationRecipientIDs', 'IncludeDataInNewPersonNotifications', 'sGreeterCustomMsg1', 'sGreeterCustomMsg2'],    
            gettext('People')              => ['sDirClassifications', 'iPersonNameStyle', 'iPersonInitialStyle', 'bHidePersonAddress', 'bHideFriendDate', 'bHideWeddingDate', 'bForceUppercaseZip', 'sInactiveClassification'],
            gettext('Families')            => ['sDirRoleHead', 'sDirRoleSpouse', 'sDirRoleChild', 'sDefaultCity', 'sDefaultState', 'sDefaultZip', 'sDefaultCountry', 'bHideFamilyNewsletter'],
            gettext('Report Settings')    => ['sQBDTSettings', 'leftX', 'incrementY', 'sTaxReport1', 'sTaxReport2', 'sTaxReport3', 'sTaxSigner', 'sReminder1', 'sReminderSigner', 'sReminderNoPledge', 'sReminderNoPayments', 'sConfirm1', 'sConfirm2', 'sConfirm3', 'sConfirm4', 'sConfirm5', 'sConfirm6', 'sDear', 'sConfirmSincerely', 'sConfirmSigner', 'sPledgeSummary1', 'sPledgeSummary2', 'sDirectoryDisclaimer1', 'sDirectoryDisclaimer2', 'bDirLetterHead', 'sZeroGivers', 'sZeroGivers2', 'sZeroGivers3', 'iPDFOutputType'],
            gettext('Financial Settings') => ['bEnabledFinance', 'bEnabledFundraiser', 'sDepositSlipType', 'iChecksPerDepositForm', 'bDisplayBillCounts', 'bUseScannedChecks', 'bEnableNonDeductible', 'iFYMonth', 'bUseDonationEnvelopes', 'aFinanceQueries'],
            gettext('Quick Search')       => ['bSearchIncludePersons', 'bSearchIncludePersonsMax', 'bSearchIncludeAddresses', 'bSearchIncludeAddressesMax', 'bSearchIncludeFamilies', 'bSearchIncludeFamiliesMax', 'bSearchIncludeFamilyHOH', 'bSearchIncludeFamilyHOHMax', 'bSearchIncludeGroups', 'bSearchIncludeGroupsMax', 'bSearchIncludeDeposits', 'bSearchIncludeDepositsMax', 'bSearchIncludePayments', 'bSearchIncludePaymentsMax', 'bSearchIncludeFamilyCustomProperties', 'bSearchIncludeCalendarEvents', 'bSearchIncludeCalendarEventsMax'],
            gettext('Localization')       => ['sDistanceUnit', 'sPhoneFormat', 'sPhoneFormatWithExt', 'sPhoneFormatCell', 'sDateFormatLong', 'sDateFormatNoYear', 'sDateTimeFormat', 'sDateFilenameFormat', 'sDatePickerFormat', 'sDatePickerPlaceHolder'],
            gettext('Confession')         => ['iPersonConfessionFatherCustomField', 'iPersonConfessionDateCustomField']
        ];
    }

    /**
     * @param \ChurchCRM\model\ChurchCRM\Config[] $configs
     */
    public static function init($configs = null): void
    {
        self::$configs = self::buildConfigs();
        self::$categories = self::buildCategories();
        if (!empty($configs)) {
            self::scrapeDBConfigs($configs);
        }
    }

    public static function isInitialized(): bool
    {
        return isset(self::$configs);
    }

    /**
     * @return array<string, string[]>
     */
    public static function getCategories(): array
    {
        return self::$categories;
    }

    /**
     * @param \ChurchCRM\model\ChurchCRM\Config[] $configs
     */
    private static function scrapeDBConfigs($configs): void
    {
        foreach ($configs as $config) {
            if (isset(self::$configs[$config->getName()])) {
                //if the current config set defined by code contains the current config retrieved from the db, then cache it
                self::$configs[$config->getName()]->setDBConfigObject($config);
            } else {
                //there's a config item in the DB that doesn't exist in the current code.
                //don't delete dynamic keys like plugin.* configs - they're managed by plugins
                if (strpos($config->getName(), 'plugin.') !== 0) {
                    $config->delete();
                }
            }
        }
    }

    public static function getConfigItem(string $name)
    {
        return self::$configs[$name];
    }

    /**
     * Return the tooltip string for a single setting, or '' if not found.
     * Centralises tooltip retrieval so templates don't need per-route arrays.
     */
    public static function getTooltip(string $name): string
    {
        $item = self::$configs[$name] ?? null;
        return $item ? $item->getTooltip() : '';
    }

    /**
     * Return the choices for a 'choice' setting as [{value, label}, ...].
     * Parses the ConfigItem data JSON (format: {"Choices":["Label:value",...]}).
     * Returns an empty array if the setting has no choices.
     */
    public static function getChoices(string $name): array
    {
        $item = self::$configs[$name] ?? null;
        if (!$item || !$item->getData()) {
            return [];
        }
        try {
            $data = json_decode($item->getData(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($data) || !isset($data['Choices'])) {
            return [];
        }
        $choices = [];
        foreach ($data['Choices'] as $entry) {
            if (str_contains($entry, ':')) {
                [$label, $value] = explode(':', $entry, 2);
                $choices[] = ['value' => $value, 'label' => $label];
            } else {
                $choices[] = ['value' => $entry, 'label' => $entry];
            }
        }
        return $choices;
    }

    /**
     * Get settings configuration for a list of setting names
     * @param array $settingNames Array of setting names
     * @return array Array of setting configurations
     */
    public static function getSettingsConfig(array $settingNames): array
    {
        $configurations = [];
        foreach ($settingNames as $settingName) {
            $configItem = self::getConfigItem($settingName);
            if ($configItem) {
                // Use the first line of tooltip as label, full tooltip as tooltip
                $tooltip = $configItem->getTooltip();
                $label = strtok($tooltip, "\n") ?: ucwords(str_replace(['i', 'b', 's', 'a'], '', $settingName));
                
                $entry = [
                    'name' => $settingName,
                    'type' => self::mapConfigTypeToSettingType($configItem->getType()),
                    'label' => $label,
                    'tooltip' => $tooltip
                ];

                $configurations[] = $entry;
            }
        }

        return $configurations;
    }

    /**
     * Map SystemConfig types to settings panel types
     * @param string $configType
     * @return string
     */
    private static function mapConfigTypeToSettingType(string $configType): string
    {
        switch ($configType) {
            case 'number':
                return 'number';
            case 'boolean':
                return 'boolean';
            case 'password':
                return 'password';
            case 'text':
            default:
                return 'text';
        }
    }

    public static function getValue(string $name)
    {
        if (!isset(self::$configs[$name])) {
            // For dynamic config keys (like plugin.*), query the database directly
            if (strpos($name, 'plugin.') === 0) {
                $dbConfig = \ChurchCRM\model\ChurchCRM\ConfigQuery::create()->findOneByName($name);
                return $dbConfig ? $dbConfig->getValue() : '';
            }
            throw new \Exception(gettext('An invalid configuration name has been requested') . ': ' . $name);
        }

        return self::$configs[$name]->getValue();
    }

    /**
     * Returns the config value escaped for use in HTML attributes (value="...", placeholder="...", data-*="...").
     */
    public static function getValueForAttr(string $name): string
    {
        return InputUtils::escapeAttribute(self::getValue($name));
    }

    /**
     * Returns the config value safely encoded for use in JavaScript literals or JSON blobs.
     * Output includes surrounding quotes, e.g.: "United States"
     */
    public static function getValueForJs(string $name): string
    {
        return json_encode(self::getValue($name), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * Returns the config value escaped for use in HTML text content (textarea body, labels, etc.).
     */
    public static function getValueForHtml(string $name): string
    {
        return InputUtils::escapeHTML(self::getValue($name));
    }

    public static function getBooleanValue(string $name): bool
    {
        if (!isset(self::$configs[$name])) {
            // For dynamic config keys (like plugin.*), delegate to getValue() which handles DB lookup
            return boolval(self::getValue($name));
        }

        return self::$configs[$name]->getBooleanValue();
    }

    public static function getIntValue(string $name): int
    {
        if (!isset(self::$configs[$name])) {
            // For dynamic config keys (like plugin.*), delegate to getValue() which handles DB lookup
            return (int) self::getValue($name);
        }

        return (int) self::$configs[$name]->getValue();
    }

    public static function setValue(string $name, $value): void
    {
        if (!isset(self::$configs[$name])) {
            // For dynamic config keys (like plugin.*), handle directly via database
            if (strpos($name, 'plugin.') === 0) {
                // Find or create the config row by name
                $dbConfig = ConfigQuery::create()->findOneByName($name);
                if (!$dbConfig) {
                    $dbConfig = new Config();
                    $dbConfig->setName($name);
                }
                $dbConfig->setValue($value);
                $dbConfig->save();
                return;
            }
            throw new \Exception(gettext('An invalid configuration name has been requested') . ': ' . $name);
        }

        $configItem = self::$configs[$name];

        // If this config item is declared as JSON, validate and sanitize it before saving
        if ($configItem->getType() === 'json') {
            try {
                $decoded = InputUtils::validateJson($value);
            } catch (\InvalidArgumentException $e) {
                throw new \Exception(gettext('Invalid JSON provided for configuration') . ': ' . $name . ' - ' . $e->getMessage());
            }

            // Recursively sanitize any string values to reduce XSS risk
            $sanitized = InputUtils::sanitizeJsonStrings($decoded);

            try {
                $value = json_encode($sanitized, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \Exception(gettext('Unable to re-encode JSON for configuration') . ': ' . $name . ' - ' . $e->getMessage());
            }
        }

        $configItem->setValue($value);
    }

    


    public static function hasValidMailServerSettings(): bool
    {
        $hasValidSettings = true;
        if (empty(self::getValue('sSMTPHost'))) {
            $hasValidSettings = false;
        }

        if (SystemConfig::getBooleanValue('bSMTPAuth') && (empty(self::getValue('sSMTPUser')) || empty(self::getValue('sSMTPPass')))) {
            $hasValidSettings = false;
        }

        return $hasValidSettings;
    }

    /**
     * @deprecated Use PluginManager to check Vonage plugin status instead.
     *             Will be removed in a future version.
     */
    public static function hasValidSMSServerSettings(): bool
    {
        return (!empty(self::getValue('plugin.vonage.apiKey'))) && (!empty(self::getValue('plugin.vonage.apiSecret'))) && (!empty(self::getValue('plugin.vonage.fromNumber')));
    }

    /**
     * @deprecated Use PluginManager to check OpenLP plugin status instead.
     *             Will be removed in a future version.
     */
    public static function hasValidOpenLPSettings(): bool
    {
        return !empty(self::getValue('plugin.openlp.serverUrl'));
    }

    public static function debugEnabled(): bool
    {
        if (intval(self::getValue('sLogLevel')) == Level::Debug->value) {
            return true;
        }

        return false;
    }
}
