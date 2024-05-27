<?php

namespace ChurchCRM\dto;

use ChurchCRM\Config;
use ChurchCRM\data\Countries;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use Exception;
use Monolog\Logger;

class SystemConfig
{
    /**
     * @var Config[]|null
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
                gettext('DEBUG') . ':' . Logger::DEBUG,
                gettext('INFO') . ':' . Logger::INFO,
                gettext('NOTICE') . ':' . Logger::NOTICE,
                gettext('WARNING') . ':' . Logger::WARNING,
                gettext('ERROR') . ':' . Logger::ERROR,
                gettext('CRITICAL') . ':' . Logger::CRITICAL,
                gettext('ALERT') . ':' . Logger::ALERT,
                gettext('EMERGENCY') . ':' . Logger::EMERGENCY,
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

    private static function buildConfigs(): array
    {
        return [
            'sLogLevel'                            => new ConfigItem(4, 'sLogLevel', 'choice', '200', gettext('Event Log severity to write, used by ORM and App Logs'), '', json_encode(SystemConfig::getMonoLogLevels(), JSON_THROW_ON_ERROR)),
            'sDirClassifications'                  => new ConfigItem(5, 'sDirClassifications', 'text', '1,2,4,5', gettext('Include only these classifications in the directory, comma separated')),
            'sDirRoleHead'                         => new ConfigItem(6, 'sDirRoleHead', 'choice', '1', gettext('These are the family role numbers designated as head of house'), '', json_encode(SystemConfig::getFamilyRoleChoices(), JSON_THROW_ON_ERROR)),
            'sDirRoleSpouse'                       => new ConfigItem(7, 'sDirRoleSpouse', 'choice', '2', gettext('These are the family role numbers designated as spouse'), '', json_encode(SystemConfig::getFamilyRoleChoices(), JSON_THROW_ON_ERROR)),
            'sDirRoleChild'                        => new ConfigItem(8, 'sDirRoleChild', 'choice', '3', gettext('These are the family role numbers designated as child'), '', json_encode(SystemConfig::getFamilyRoleChoices(), JSON_THROW_ON_ERROR)),
            'iSessionTimeout'                      => new ConfigItem(9, 'iSessionTimeout', 'number', '3600', gettext("Session timeout length in seconds\nSet to zero to disable session timeouts.")),
            'aFinanceQueries'                      => new ConfigItem(10, 'aFinanceQueries', 'text', '30,31,32', gettext('Queries for which user must have finance permissions to use:')),
            'bCSVAdminOnly'                        => new ConfigItem(11, 'bCSVAdminOnly', 'boolean', '1', gettext('Should only administrators have access to the CSV export system and directory report?')),
            'iMinPasswordLength'                   => new ConfigItem(13, 'iMinPasswordLength', 'number', '6', gettext('Minimum length a user may set their password to')),
            'iMinPasswordChange'                   => new ConfigItem(14, 'iMinPasswordChange', 'number', '4', gettext("Minimum amount that a new password must differ from the old one (# of characters changed)\nSet to zero to disable this feature")),
            'aDisallowedPasswords'                 => new ConfigItem(15, 'aDisallowedPasswords', 'text', 'password,god,jesus,church,christian', gettext('A comma-separated list of disallowed (too obvious) passwords.')),
            'iMaxFailedLogins'                     => new ConfigItem(16, 'iMaxFailedLogins', 'number', '5', gettext("Maximum number of failed logins to allow before a user account is locked.\nOnce the maximum has been reached, an administrator must re-enable the account.\nThis feature helps to protect against automated password guessing attacks.\nSet to zero to disable this feature.")),
            'iPDFOutputType'                       => new ConfigItem(20, 'iPDFOutputType', 'number', '1', gettext("PDF handling mode.\n1 = Save File dialog\n2 = Open in current browser window")),
            'sDefaultCity'                         => new ConfigItem(21, 'sDefaultCity', 'text', '', gettext('Default City')),
            'sDefaultState'                        => new ConfigItem(22, 'sDefaultState', 'text', '', gettext('Default State - Must be 2-letter abbreviation!')),
            'sDefaultCountry'                      => new ConfigItem(23, 'sDefaultCountry', 'choice', '', '', '', json_encode(['Choices' => Countries::getNames()], JSON_THROW_ON_ERROR)),
            'sToEmailAddress'                      => new ConfigItem(26, 'sToEmailAddress', 'text', '', gettext('Default account for receiving a copy of all emails')),
            'iSMTPTimeout'                         => new ConfigItem(24, 'iSMTPTimeout', 'number', '10', gettext('SMTP Server timeout in sec')),
            'sSMTPHost'                            => new ConfigItem(27, 'sSMTPHost', 'text', '', gettext('SMTP Server Address (mail.server.com:25)')),
            'bSMTPAuth'                            => new ConfigItem(28, 'bSMTPAuth', 'boolean', '0', gettext('Does your SMTP server require auththentication (username/password)?')),
            'sSMTPUser'                            => new ConfigItem(29, 'sSMTPUser', 'text', '', gettext('SMTP Username')),
            'sSMTPPass'                            => new ConfigItem(30, 'sSMTPPass', 'password', '', gettext('SMTP Password')),
            'bShowFamilyData'                      => new ConfigItem(33, 'bShowFamilyData', 'boolean', '1', gettext("Unavailable person info inherited from assigned family for display?\nThis option causes certain info from a person's assigned family record to be\ndisplayed IF the corresponding info has NOT been entered for that person. ")),
            'sLanguage'                            => new ConfigItem(39, 'sLanguage', 'choice', 'en_US', gettext('Internationalization (I18n) support'), 'https://poeditor.com/join/project?hash=RABdnDSqAt', json_encode(SystemConfig::getSupportedLocales(), JSON_THROW_ON_ERROR)),
            'iFYMonth'                             => new ConfigItem(40, 'iFYMonth', 'choice', '1', gettext('First month of the fiscal year'), '', '{"Choices":["1","2","3","4","5","6","7","8","9","10","11","12"]}'),
            'sGoogleMapsGeocodeKey'                => new ConfigItem(44, 'sGoogleMapsGeocodeKey', 'text', '', gettext('Google Maps API Key used for Geocoding addresses'), 'https://developers.google.com/maps/documentation/javascript/get-api-key'),
            'sBingMapKey'                          => new ConfigItem(10000, 'sBingMapKey', 'text', '', gettext('Bing map API requires a unique key'), 'https://www.microsoft.com/maps/create-a-bing-maps-key.aspx'),
            'iMapZoom'                             => new ConfigItem(10001, 'iMapZoom', 'number', '10', gettext('Google Maps Zoom')),
            'iChurchLatitude'                      => new ConfigItem(45, 'iChurchLatitude', 'number', '', gettext('Latitude of the church, used to center the Google map')),
            'iChurchLongitude'                     => new ConfigItem(46, 'iChurchLongitude', 'number', '', gettext('Longitude of the church, used to center the Google map')),
            'bHidePersonAddress'                   => new ConfigItem(47, 'bHidePersonAddress', 'boolean', '1', gettext('Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor.')),
            'bHideFriendDate'                      => new ConfigItem(48, 'bHideFriendDate', 'boolean', '0', gettext('Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.')),
            'bHideFamilyNewsletter'                => new ConfigItem(49, 'bHideFamilyNewsletter', 'boolean', '0', gettext('Set true to disable management of newsletter subscriptions in the Family Editor.')),
            'bHideWeddingDate'                     => new ConfigItem(50, 'bHideWeddingDate', 'boolean', '0', gettext('Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.')),
            'bHideLatLon'                          => new ConfigItem(51, 'bHideLatLon', 'boolean', '0', gettext('Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed.')),
            'bUseDonationEnvelopes'                => new ConfigItem(52, 'bUseDonationEnvelopes', 'boolean', '0', gettext('Set true to enable use of donation envelopes')),
            'sHeader'                              => new ConfigItem(53, 'sHeader', 'textarea', '', gettext('Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! Note: You must REFRESH YOUR BROWSER A SECOND TIME to view the new header.')),
            'sGeoCoderProvider'                    => new ConfigItem(56, 'sGeoCoderProvider', 'choice', 'GoogleMaps', gettext('Select GeoCoder Provider'), 'https://github.com/geocoder-php/Geocoder/blob/3.x/README.md#address-based-providers', '{"Choices":["GoogleMaps", "BingMaps"]}'),
            'iChecksPerDepositForm'                => new ConfigItem(57, 'iChecksPerDepositForm', 'number', '14', gettext('Number of checks for Deposit Slip Report')),
            'bUseScannedChecks'                    => new ConfigItem(58, 'bUseScannedChecks', 'boolean', '0', gettext('Set true to enable use of scanned checks')),
            'sDistanceUnit'                        => new ConfigItem(64, 'sDistanceUnit', 'choice', 'miles', gettext('Unit used to measure distance, miles or km.'), '', '{"Choices":["' . gettext('miles') . '","' . gettext('kilometers') . '"]}'),
            'sTimeZone'                            => new ConfigItem(65, 'sTimeZone', 'choice', 'America/New_York', gettext('Time zone'), 'http://php.net/manual/en/timezones.php', json_encode(['Choices' => timezone_identifiers_list()], JSON_THROW_ON_ERROR)),
            'sGMapIcons'                           => new ConfigItem(66, 'sGMapIcons', 'text', 'green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue', gettext('Names of markers for Google Maps in order of classification')),
            'bForceUppercaseZip'                   => new ConfigItem(67, 'bForceUppercaseZip', 'boolean', '0', gettext('Make user-entered zip/postcodes UPPERCASE when saving to the database.')),
            'bEnableNonDeductible'                 => new ConfigItem(72, 'bEnableNonDeductible', 'boolean', '0', gettext('Enable non-deductible payments')),
            'bEnableSelfRegistration'              => new ConfigItem(80, 'bEnableSelfRegistration', 'boolean', '0', gettext('Set true to enable family self registration.')),
            'sPhoneFormat'                         => new ConfigItem(100, 'sPhoneFormat', 'text', '(999) 999-9999'),
            'sPhoneFormatWithExt'                  => new ConfigItem(101, 'sPhoneFormatWithExt', 'text', '(999) 999-9999 x99999'),
            'sPhoneFormatCell'                     => new ConfigItem(111, 'sPhoneFormatCell', 'text', '(999) 999-9999'),
            'sDateFormatLong'                      => new ConfigItem(102, 'sDateFormatLong', 'text', 'm/d/Y'),
            'sDateFormatNoYear'                    => new ConfigItem(103, 'sDateFormatNoYear', 'text', 'm/d'),
            'sDateFormatShort'                     => new ConfigItem(104, 'sDateFormatShort', 'text', 'm/d/y'),
            'sDateTimeFormat'                      => new ConfigItem(105, 'sDateTimeFormat', 'text', 'm/d/Y g:i a'),
            'sDateFilenameFormat'                  => new ConfigItem(106, 'sDateFilenameFormat', 'text', 'Ymd-Gis'),
            'sCSVExportDelimiter'                  => new ConfigItem(107, 'sCSVExportDelimiter', 'text', ',', gettext('To export to another For european CharSet use ;')),
            'sCSVExportCharset'                    => new ConfigItem(108, 'sCSVExportCharset', 'text', 'UTF-8', gettext('Default is UTF-8, For european CharSet use Windows-1252 for example for French language.')),
            'sDatePickerPlaceHolder'               => new ConfigItem(109, 'sDatePickerPlaceHolder', 'text', 'yyyy-mm-dd', gettext('For defining the date in Date-Picker, per default : yyyy-mm-dd, In French : dd/mm/yyyy for example.')),
            'sDatePickerFormat'                    => new ConfigItem(110, 'sDatePickerFormat', 'text', 'Y-m-d', gettext('For defining the date in Date-Picker, per default : Y-m-d, In French : d/m/Y for example.')),
            'bRegistered'                          => new ConfigItem(999, 'bRegistered', 'boolean', '0', gettext('ChurchCRM has been registered.  The ChurchCRM team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration.')),
            'leftX'                                => new ConfigItem(1001, 'leftX', 'number', '20', gettext('Left Margin (1 = 1/100th inch)')),
            'incrementY'                           => new ConfigItem(1002, 'incrementY', 'number', '4', gettext('Line Thickness (1 = 1/100th inch')),
            'sChurchName'                          => new ConfigItem(1003, 'sChurchName', 'text', '', gettext('Church Name')),
            'sChurchAddress'                       => new ConfigItem(1004, 'sChurchAddress', 'text', '', gettext('Church Address')),
            'sChurchCity'                          => new ConfigItem(1005, 'sChurchCity', 'text', '', gettext('Church City')),
            'sChurchState'                         => new ConfigItem(1006, 'sChurchState', 'text', '', gettext('Church State')),
            'sChurchZip'                           => new ConfigItem(1007, 'sChurchZip', 'text', '', gettext('Church Zip')),
            'sChurchPhone'                         => new ConfigItem(1008, 'sChurchPhone', 'text', '', gettext('Church Phone')),
            'sChurchEmail'                         => new ConfigItem(1009, 'sChurchEmail', 'text', '', gettext('Church Email')),
            'sHomeAreaCode'                        => new ConfigItem(1010, 'sHomeAreaCode', 'text', '', gettext('Home area code of the church')),
            'sTaxReport1'                          => new ConfigItem(1011, 'sTaxReport1', 'text', 'This letter shows our record of your payments for', gettext('Verbage for top line of tax report. Dates will be appended to the end of this line.')),
            'sTaxReport2'                          => new ConfigItem(1012, 'sTaxReport2', 'text', 'Thank you for your help in making a difference. We greatly appreciate your gift!', gettext('Verbage for bottom line of tax report.')),
            'sTaxReport3'                          => new ConfigItem(1013, 'sTaxReport3', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', gettext('Verbage for bottom line of tax report.')),
            'sTaxSigner'                           => new ConfigItem(1014, 'sTaxSigner', 'text', '', gettext('Tax Report signer')),
            'sReminder1'                           => new ConfigItem(1015, 'sReminder1', 'text', 'This letter shows our record of your pledge and payments for fiscal year', gettext('Verbage for the pledge reminder report')),
            'sReminderSigner'                      => new ConfigItem(1016, 'sReminderSigner', 'text', '', gettext('Pledge Reminder Signer')),
            'sReminderNoPledge'                    => new ConfigItem(1017, 'sReminderNoPledge', 'text', 'Pledges: We do not have record of a pledge for from you for this fiscal year.', gettext('Verbage for the pledge reminder report - No record of a pledge')),
            'sReminderNoPayments'                  => new ConfigItem(1018, 'sReminderNoPayments', 'text', 'Payments: We do not have record of a pledge for from you for this fiscal year.', gettext('Verbage for the pledge reminder report - No record of payments')),
            'sConfirm1'                            => new ConfigItem(1019, 'sConfirm1', 'text', 'This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm2'                            => new ConfigItem(1020, 'sConfirm2', 'text', 'Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm3'                            => new ConfigItem(1021, 'sConfirm3', 'text', 'Email _____________________________________ Password ________________', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm4'                            => new ConfigItem(1022, 'sConfirm4', 'text', '[  ] I no longer want to be associated with the church (check here to be removed from our records).', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm5'                            => new ConfigItem(1023, 'sConfirm5', 'text', '', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirm6'                            => new ConfigItem(1024, 'sConfirm6', 'text', '', gettext('Verbage for the database information confirmation and correction report')),
            'sConfirmSigner'                       => new ConfigItem(1025, 'sConfirmSigner', 'text', '', gettext('Database information confirmation and correction report signer')),
            'sPledgeSummary1'                      => new ConfigItem(1026, 'sPledgeSummary1', 'text', 'Summary of pledges and payments for the fiscal year', gettext('Verbage for the pledge summary report')),
            'sPledgeSummary2'                      => new ConfigItem(1027, 'sPledgeSummary2', 'text', ' as of', gettext('Verbage for the pledge summary report')),
            'sDirectoryDisclaimer1'                => new ConfigItem(1028, 'sDirectoryDisclaimer1', 'text', "Every effort was made to ensure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of", gettext('Verbage for the directory report')),
            'sDirectoryDisclaimer2'                => new ConfigItem(1029, 'sDirectoryDisclaimer2', 'text', ', and the information contained in it may not be used for business or commercial purposes.', gettext('Verbage for the directory report')),
            'bDirLetterHead'                       => new ConfigItem(1030, 'bDirLetterHead', 'text', '../Images/church_letterhead.jpg', gettext('Church Letterhead path and file')),
            'sZeroGivers'                          => new ConfigItem(1031, 'sZeroGivers', 'text', 'This letter shows our record of your payments for', gettext('Verbage for top line of tax report. Dates will be appended to the end of this line.')),
            'sZeroGivers2'                         => new ConfigItem(1032, 'sZeroGivers2', 'text', 'Thank you for your help in making a difference. We greatly appreciate your gift!', gettext('Verbage for bottom line of tax report.')),
            'sZeroGivers3'                         => new ConfigItem(1033, 'sZeroGivers3', 'text', 'If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.', gettext('Verbage for bottom line of tax report.')),
            'sChurchChkAcctNum'                    => new ConfigItem(1034, 'sChurchChkAcctNum', 'text', '', gettext('Church Checking Account Number')),
            'bEnableGravatarPhotos'                => new ConfigItem(1035, 'bEnableGravatarPhotos', 'boolean', '0', gettext('lookup user images on Gravatar when no local image is present')),
            'bEnableExternalBackupTarget'          => new ConfigItem(1036, 'bEnableExternalBackupTarget', 'boolean', '0', gettext('Enable Remote Backups to Cloud Services')),
            'sExternalBackupType'                  => new ConfigItem(1037, 'sExternalBackupType', 'choice', '', gettext('Cloud Service Type (Supported values: WebDAV, Local)'), '', '{"Choices":["' . gettext('WebDAV') . '","' . gettext('Local') . '"]}'),
            'sExternalBackupEndpoint'              => new ConfigItem(1038, 'sExternalBackupEndpoint', 'text', '', gettext('Remote Backup Endpoint.  If WebDAV, this must be url encoded. ')),
            'sExternalBackupUsername'              => new ConfigItem(1039, 'sExternalBackupUsername', 'text', '', gettext('Remote Backup Username')),
            'sExternalBackupPassword'              => new ConfigItem(1040, 'sExternalBackupPassword', 'password', '', gettext('Remote Backup Password')),
            'sExternalBackupAutoInterval'          => new ConfigItem(1041, 'sExternalBackupAutoInterval', 'text', '', gettext('Interval in Hours for Automatic Remote Backups')),
            'sLastBackupTimeStamp'                 => new ConfigItem(1042, 'sLastBackupTimeStamp', 'text', '', gettext('Last Backup Timestamp')),
            'sQBDTSettings'                        => new ConfigItem(1043, 'sQBDTSettings', 'json', '{"date1":{"x":"12","y":"42"},"date2X":"185","leftX":"64","topY":"7","perforationY":"97","amountOffsetX":"35","lineItemInterval":{"x":"49","y":"7"},"max":{"x":"200","y":"140"},"numberOfItems":{"x":"136","y":"68"},"subTotal":{"x":"197","y":"42"},"topTotal":{"x":"197","y":"68"},"titleX":"85"}', gettext('QuickBooks Deposit Ticket Settings')),
            'bEnableIntegrityCheck'                => new ConfigItem(1044, 'bEnableIntegrityCheck', 'boolean', '1', gettext('Enable Integrity Check')),
            'iIntegrityCheckInterval'              => new ConfigItem(1045, 'iIntegrityCheckInterval', 'number', '168', gettext('Interval in Hours for Integrity Check')),
            'sLastIntegrityCheckTimeStamp'         => new ConfigItem(1046, 'sLastIntegrityCheckTimeStamp', 'text', '', gettext('Last Integrity Check Timestamp')),
            'sChurchCountry'                       => new ConfigItem(1047, 'sChurchCountry', 'choice', '', '', '', json_encode(['Choices' => Countries::getNames()], JSON_THROW_ON_ERROR)),
            'sConfirmSincerely'                    => new ConfigItem(1048, 'sConfirmSincerely', 'text', 'Sincerely', gettext('Used to end a letter before Signer')),
            'sDear'                                => new ConfigItem(1049, 'sDear', 'text', 'Dear', gettext('Text before name in emails/reports')),
            'sGoogleTrackingID'                    => new ConfigItem(1050, 'sGoogleTrackingID', 'text', '', gettext('Google Analytics Tracking Code')),
            'sMailChimpApiKey'                     => new ConfigItem(2000, 'sMailChimpApiKey', 'text', '', '', 'http://kb.mailchimp.com/accounts/management/about-api-keys'),
            'sDepositSlipType'                     => new ConfigItem(2001, 'sDepositSlipType', 'choice', 'QBDT', gettext('Deposit ticket type.  QBDT - Quickbooks'), '', '{"Choices":["QBDT"]}'),
            'bAllowEmptyLastName'                  => new ConfigItem(2010, 'bAllowEmptyLastName', 'boolean', '0', gettext('Set true to allow empty lastname in Person Editor.  Set false to validate last name and inherit from family when left empty.')),
            'iPersonNameStyle'                     => new ConfigItem(2020, 'iPersonNameStyle', 'choice', '4', '', '', json_encode(SystemConfig::getNameChoices(), JSON_THROW_ON_ERROR)),
            'bDisplayBillCounts'                   => new ConfigItem(2002, 'bDisplayBillCounts', 'boolean', '1', gettext('Display bill counts on deposit slip')),
            'sCloudURL'                            => new ConfigItem(2003, 'sCloudURL', 'text', 'http://demo.churchcrm.io/', gettext('ChurchCRM Cloud Access URL')),
            'sNexmoAPIKey'                         => new ConfigItem(2012, 'sNexmoAPIKey', 'text', '', gettext('Nexmo SMS API Key')),
            'sNexmoAPISecret'                      => new ConfigItem(2005, 'sNexmoAPISecret', 'password', '', gettext('Nexmo SMS API Secret')),
            'sNexmoFromNumber'                     => new ConfigItem(2006, 'sNexmoFromNumber', 'text', '', gettext('Nexmo SMS From Number')),
            'sOLPURL'                              => new ConfigItem(2007, 'sOLPURL', 'text', 'http://192.168.1.1:4316', gettext('OpenLP URL')),
            'sOLPUserName'                         => new ConfigItem(2008, 'sOLPUserName', 'text', '', gettext('OpenLP Username')),
            'sOLPPassword'                         => new ConfigItem(2009, 'sOLPPassword', 'password', '', gettext('OpenLP Password')),
            'sKioskVisibilityTimestamp'            => new ConfigItem(2011, 'sKioskVisibilityTimestamp', 'text', '', gettext('KioskVisibilityTimestamp')),
            'bEnableLostPassword'                  => new ConfigItem(2004, 'bEnableLostPassword', 'boolean', '1', gettext('Show/Hide Lost Password Link on the login screen')),
            'sChurchWebSite'                       => new ConfigItem(2013, 'sChurchWebSite', 'text', '', gettext("Your Church's Website")),
            'sChurchFB'                            => new ConfigItem(2014, 'sChurchFB', 'text', '', gettext("Your Church's Facebook Page")),
            'sChurchTwitter'                       => new ConfigItem(2015, 'sChurchTwitter', 'text', '', gettext("Your Church's X Page")),
            'bEnableGooglePhotos'                  => new ConfigItem(2016, 'bEnableGooglePhotos', 'boolean', '1', gettext('lookup user images on Google when no local image is present')),
            'sNewPersonNotificationRecipientIDs'   => new ConfigItem(2018, 'sNewPersonNotificationRecipientIDs', 'text', '', gettext('Comma Separated list of PersonIDs of people to notify when a new family or person is added')),
            'bEnableExternalCalendarAPI'           => new ConfigItem(2017, 'bEnableExternalCalendarAPI', 'boolean', '0', gettext('Allow unauthenticated reads of events from the external calendar API')),
            'bSearchIncludePersons'                => new ConfigItem(2019, 'bSearchIncludePersons', 'boolean', '1', gettext('Search People')),
            'bSearchIncludeFamilies'               => new ConfigItem(2021, 'bSearchIncludeFamilies', 'boolean', '1', gettext('Search Family')),
            'bSearchIncludeFamilyHOH'              => new ConfigItem(2022, 'bSearchIncludeFamilyHOH', 'boolean', '1', gettext('Show Family Head of House Names')),
            'bSearchIncludeGroups'                 => new ConfigItem(2023, 'bSearchIncludeGroups', 'boolean', '1', gettext('Search Groups')),
            'bSearchIncludeDeposits'               => new ConfigItem(2024, 'bSearchIncludeDeposits', 'boolean', '1', gettext('Search Deposits')),
            'bSearchIncludePayments'               => new ConfigItem(2025, 'bSearchIncludePayments', 'boolean', '1', gettext('Search Payments')),
            'bSearchIncludeAddresses'              => new ConfigItem(2026, 'bSearchIncludeAddresses', 'boolean', '1', gettext('Search Addresses')),
            'bSearchIncludePersonsMax'             => new ConfigItem(2027, 'bSearchIncludePersonsMax', 'text', '15', gettext('Maximum number of People')),
            'bSearchIncludeFamiliesMax'            => new ConfigItem(2028, 'bSearchIncludeFamiliesMax', 'text', '15', gettext('Maximum number of Families')),
            'bSearchIncludeFamilyHOHMax'           => new ConfigItem(2029, 'bSearchIncludeFamilyHOHMax', 'text', '15', gettext('Maximum number of Family H.O.H Names')),
            'bSearchIncludeGroupsMax'              => new ConfigItem(2030, 'bSearchIncludeGroupsMax', 'text', '15', gettext('Maximum number of Groups')),
            'bSearchIncludeDepositsMax'            => new ConfigItem(2031, 'bSearchIncludeDepositsMax', 'text', '5', gettext('Maximum number of Deposits')),
            'bSearchIncludePaymentsMax'            => new ConfigItem(2032, 'bSearchIncludePaymentsMax', 'text', '5', gettext('Maximum number of Payments')),
            'bSearchIncludeAddressesMax'           => new ConfigItem(20233, 'bSearchIncludeAddressesMax', 'text', '15', gettext('Maximum number of Addresses')),
            'iPhotoHeight'                         => new ConfigItem(2034, 'iPhotoHeight', 'number', '400', gettext('Height to use for images')),
            'iPhotoWidth'                          => new ConfigItem(2035, 'iPhotoWidth', 'number', '400', gettext('Width to use for images')),
            'iThumbnailWidth'                      => new ConfigItem(2036, 'iPhotoWidth', 'number', '100', gettext('Width to use for thumbnails')),
            'iInitialsPointSize'                   => new ConfigItem(2037, 'iInitialsPointSize', 'number', '150', gettext('Point size to use for initials thumbnails')),
            'iPhotoClientCacheDuration'            => new ConfigItem(2038, 'iPhotoClientCacheDuration', 'number', '3600', gettext('Client cache seconds for images')),
            'iRemotePhotoCacheDuration'            => new ConfigItem(2039, 'iRemotePhotoCacheDuration', 'text', '72 hours', gettext('Server cache time for remote images')),
            'iPersonConfessionFatherCustomField'   => new ConfigItem(2040, 'iPersonConfessionFatherCustomField', 'ajax', '', gettext('Field where Father Of Confession is listed, must be a people of group type'), '', '/api/system/custom-fields/person/?typeId=9'),
            'iPersonConfessionDateCustomField'     => new ConfigItem(2041, 'iPersonConfessionDateCustomField', 'ajax', '', gettext('Field where last Confession is stored, must be a date type'), '', '/api/system/custom-fields/person/?typeId=2'),
            'bHSTSEnable'                          => new ConfigItem(20142, 'bHSTSEnable', 'boolean', '0', gettext('Require that this ChurchCRM Database is accessed over HTTPS')),
            'bEventsOnDashboardPresence'           => new ConfigItem(2042, 'bEventsOnDashboardPresence', 'boolean', '1', gettext('Show Birthdates Anniversaries on start up of the CRM')),
            'iEventsOnDashboardPresenceTimeOut'    => new ConfigItem(2043, 'iEventsOnDashboardPresenceTimeOut', 'number', '10', gettext('Number of seconds after page load until the banner disappears, default 10 seconds')),
            'bPHPMailerAutoTLS'                    => new ConfigItem(2045, 'bPHPMailerAutoTLS', 'boolean', '0', gettext('Automatically enable SMTP encryption if offered by the relaying server.')),
            'sPHPMailerSMTPSecure'                 => new ConfigItem(2046, 'sPHPMailerSMTPSecure', 'choice', ' ', gettext('Set the encryption system to use - ssl (deprecated) or tls'), '', '{"Choices":["None: ","TLS:tls","SSL:ssl"]}'),
            'iDashboardServiceIntervalTime'        => new ConfigItem(2047, 'iDashboardServiceIntervalTime', 'number', '60', gettext('Dashboard Service dynamic asynchronous refresh interval, default 60 second')),
            'iProfilePictureListSize'              => new ConfigItem(2048, 'iProfilePictureListSize', 'number', '85', gettext('Set the standard profile picture icon size in pixels to be used in people lists, default 85 pixels.')),
            'bEnabledMenuLinks'                    => new ConfigItem(2050, 'bEnabledMenuLinks', 'boolean', '0', gettext('Show custom links on the left menu.')),
            'bEnabledSundaySchool'                 => new ConfigItem(2051, 'bEnabledSundaySchool', 'boolean', '1', gettext('Enable Sunday School left menu.')),
            'bEnabledFinance'                      => new ConfigItem(2052, 'bEnabledFinance', 'boolean', '1', gettext('Enable Finance menu')),
            'bEnabledEvents'                       => new ConfigItem(2053, 'bEnabledEvents', 'boolean', '1', gettext('Enable Events menu.')),
            'bEnabledCalendar'                     => new ConfigItem(2054, 'bEnabledCalendar', 'boolean', '1', gettext('Enable Calendar menu.')),
            'bEnabledFundraiser'                   => new ConfigItem(2055, 'bEnabledFundraiser', 'boolean', '1', gettext('Enable Fundraiser menu.')),
            'bEnabledEmail'                        => new ConfigItem(2056, 'bEnabledEmail', 'boolean', '1', gettext('Enable Email menu.')),
            'sNotificationsURL'                    => new ConfigItem(2057, 'sNotificationsURL', 'text', 'https://raw.githubusercontent.com/ChurchCRM/CRM/Notifications/notifications.json', gettext('ChurchCRM Central Notifications URL')),
            'sGreeterCustomMsg1'                   => new ConfigItem(2058, 'sGreeterCustomMsg1', 'text', '', gettext('Custom message for church greeter email 1, max 255 characters')),
            'sGreeterCustomMsg2'                   => new ConfigItem(2059, 'sGreeterCustomMsg2', 'text', '', gettext('Custom message for church greeter email 2, max 255 characters')),
            'IncludeDataInNewPersonNotifications'  => new ConfigItem(2060, 'IncludeDataInNewPersonNotifications', 'boolean', '0', gettext('Include contact and demographic data in new member email notification body')),
            'bSearchIncludeFamilyCustomProperties' => new ConfigItem(2061, 'bSearchIncludeFamilyCustomProperties', 'boolean', '0', gettext('Include family custom properties in global search.')),
            'bBackupExtraneousImages'              => new ConfigItem(2062, 'bBackupExtraneousImages', 'boolean', '0', gettext('Include initials image files, remote image files (gravatar), and thumbnails in backup.  These files are generally able to be reproduced after a restore and add very little value to the backup archive at a large expense of execution time and storage')),
            'iSoftwareUpdateCheckInterval'         => new ConfigItem(2063, 'iSoftwareUpdateCheckInterval', 'number', '24', gettext('Interval in Hours for software update check')),
            'sLastSoftwareUpdateCheckTimeStamp'    => new ConfigItem(2064, 'sLastSoftwareUpdateCheckTimeStamp', 'text', '', gettext('Last Software Update Check Timestamp')),
            'bAllowPrereleaseUpgrade'              => new ConfigItem(2065, 'bAllowPrereleaseUpgrade', 'boolean', '0', gettext("Allow system upgrades to release marked as 'pre release' on GitHub")),
            'bSearchIncludeCalendarEvents'         => new ConfigItem(2066, 'bSearchIncludeCalendarEvents', 'boolean', '1', gettext('Search Calendar Events')),
            'bSearchIncludeCalendarEventsMax'      => new ConfigItem(2067, 'bSearchIncludeCalendarEventsMax', 'text', '15', gettext('Maximum number of Calendar Events')),
            'bEnable2FA'                           => new ConfigItem(2068, 'bEnable2FA', 'boolean', '1', gettext('Allow users to self-enroll in 2 factor authentication')),
            'bRequire2FA'                          => new ConfigItem(2069, 'bRequire2FA', 'boolean', '0', gettext('Requires users to self-enroll in 2 factor authentication')),
            's2FAApplicationName'                  => new ConfigItem(2070, 's2FAApplicationName', 'text', gettext('ChurchCRM'), gettext('Specify the application name to be displayed in authenticator app')),
            'bSendUserDeletedEmail'                => new ConfigItem(2071, 'bSendUserDeletedEmail', 'boolean', '0', gettext('Send an email notifying users when their account has been deleted')),
            'sGoogleMapsRenderKey'                 => new ConfigItem(2072, 'sGoogleMapsRenderKey', 'text', '', gettext('Google Maps API Key used for rendering maps in browser'), 'https://developers.google.com/maps/documentation/javascript/get-api-key'),
            'sInactiveClassification'              => new ConfigItem(2073, 'sInactiveClassification', 'text', '', gettext('Comma separated list of classifications that should appear as inactive')),
            'sDefaultZip'                          => new ConfigItem(2074, 'sDefaultZip', 'text', '', gettext('Default Zip')),
        ];
    }

    private static function buildCategories(): array
    {
        return [
            gettext('Church Information') => ['sChurchName', 'sChurchAddress', 'sChurchCity', 'sChurchState', 'sChurchZip', 'sChurchCountry', 'sChurchPhone', 'sChurchEmail', 'sHomeAreaCode', 'sTimeZone', 'iChurchLatitude', 'iChurchLongitude', 'sChurchWebSite', 'sChurchFB', 'sChurchTwitter'],
            gettext('User Setup')         => ['iMinPasswordLength', 'iMinPasswordChange', 'iMaxFailedLogins', 'iSessionTimeout', 'aDisallowedPasswords', 'bEnableLostPassword', 'bEnable2FA', 'bRequire2FA', 's2FAApplicationName', 'bSendUserDeletedEmail'],
            gettext('Email Setup')        => ['sSMTPHost', 'bSMTPAuth', 'sSMTPUser', 'sSMTPPass', 'iSMTPTimeout', 'sToEmailAddress', 'bPHPMailerAutoTLS', 'sPHPMailerSMTPSecure'],
            gettext('People Setup')       => ['sDirClassifications', 'sDirRoleHead', 'sDirRoleSpouse', 'sDirRoleChild', 'sDefaultCity', 'sDefaultState', 'sDefaultZip', 'sDefaultCountry', 'bShowFamilyData', 'bHidePersonAddress', 'bHideFriendDate', 'bHideFamilyNewsletter', 'bHideWeddingDate', 'bHideLatLon', 'bForceUppercaseZip', 'bEnableSelfRegistration', 'bAllowEmptyLastName', 'iPersonNameStyle', 'iProfilePictureListSize', 'sNewPersonNotificationRecipientIDs', 'IncludeDataInNewPersonNotifications', 'sGreeterCustomMsg1', 'sGreeterCustomMsg2', 'sInactiveClassification'],
            gettext('Enabled Features')   => ['bEnabledFinance', 'bEnabledSundaySchool', 'bEnabledEvents', 'bEnabledCalendar', 'bEnabledFundraiser', 'bEnabledEmail', 'bEnabledMenuLinks'],
            gettext('Map Settings')       => ['sGeoCoderProvider', 'sGoogleMapsGeocodeKey', 'sGoogleMapsRenderKey', 'sBingMapKey', 'sGMapIcons', 'iMapZoom'],
            gettext('Report Settings')    => ['sQBDTSettings', 'leftX', 'incrementY', 'sTaxReport1', 'sTaxReport2', 'sTaxReport3', 'sTaxSigner', 'sReminder1', 'sReminderSigner', 'sReminderNoPledge', 'sReminderNoPayments', 'sConfirm1', 'sConfirm2', 'sConfirm3', 'sConfirm4', 'sConfirm5', 'sConfirm6', 'sDear', 'sConfirmSincerely', 'sConfirmSigner', 'sPledgeSummary1', 'sPledgeSummary2', 'sDirectoryDisclaimer1', 'sDirectoryDisclaimer2', 'bDirLetterHead', 'sZeroGivers', 'sZeroGivers2', 'sZeroGivers3', 'iPDFOutputType'],
            gettext('Financial Settings') => ['sDepositSlipType', 'iChecksPerDepositForm', 'bDisplayBillCounts', 'bUseScannedChecks', 'bEnableNonDeductible', 'iFYMonth', 'bUseDonationEnvelopes', 'aFinanceQueries'],
            gettext('Quick Search')       => ['bSearchIncludePersons', 'bSearchIncludePersonsMax', 'bSearchIncludeAddresses', 'bSearchIncludeAddressesMax', 'bSearchIncludeFamilies', 'bSearchIncludeFamiliesMax', 'bSearchIncludeFamilyHOH', 'bSearchIncludeFamilyHOHMax', 'bSearchIncludeGroups', 'bSearchIncludeGroupsMax', 'bSearchIncludeDeposits', 'bSearchIncludeDepositsMax', 'bSearchIncludePayments', 'bSearchIncludePaymentsMax', 'bSearchIncludeFamilyCustomProperties', 'bSearchIncludeCalendarEvents', 'bSearchIncludeCalendarEventsMax'],
            gettext('Localization')       => ['sLanguage', 'sDistanceUnit', 'sPhoneFormat', 'sPhoneFormatWithExt', 'sPhoneFormatCell', 'sDateFormatLong', 'sDateFormatNoYear', 'sDateFormatShort', 'sDateTimeFormat', 'sDateFilenameFormat', 'sCSVExportDelimiter', 'sCSVExportCharset', 'sDatePickerFormat', 'sDatePickerPlaceHolder'],
            gettext('Integration')        => ['sMailChimpApiKey', 'sGoogleTrackingID', 'bEnableGravatarPhotos', 'bEnableGooglePhotos', 'iRemotePhotoCacheDuration', 'sNexmoAPIKey', 'sNexmoAPISecret', 'sNexmoFromNumber', 'sOLPURL', 'sOLPUserName', 'sOLPPassword'],
            gettext('Church Services')    => ['iPersonConfessionFatherCustomField', 'iPersonConfessionDateCustomField'],
            gettext('Events')             => ['bEnableExternalCalendarAPI', 'bEventsOnDashboardPresence', 'iEventsOnDashboardPresenceTimeOut'],
            gettext('Backup')             => ['sLastBackupTimeStamp', 'bEnableExternalBackupTarget', 'sExternalBackupType', 'sExternalBackupAutoInterval', 'sExternalBackupEndpoint', 'sExternalBackupUsername', 'sExternalBackupPassword', 'bBackupExtraneousImages'],
            gettext('System Settings')    => ['sLogLevel', 'bRegistered', 'bCSVAdminOnly', 'sHeader', 'bEnableIntegrityCheck', 'iIntegrityCheckInterval', 'sLastIntegrityCheckTimeStamp', 'iPhotoClientCacheDuration', 'bHSTSEnable', 'iDashboardServiceIntervalTime', 'iSoftwareUpdateCheckInterval', 'sLastSoftwareUpdateCheckTimeStamp', 'bAllowPrereleaseUpgrade'],
        ];
    }

    /**
     * @param Config[] $configs
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
     * @param Config[] $configs
     */
    private static function scrapeDBConfigs($configs): void
    {
        foreach ($configs as $config) {
            if (isset(self::$configs[$config->getName()])) {
                //if the current config set defined by code contains the current config retrieved from the db, then cache it
                self::$configs[$config->getName()]->setDBConfigObject($config);
            } else {
                //there's a config item in the DB that doesn't exist in the current code.
                //delete it
                $config->delete();
            }
        }
    }

    public static function getConfigItem(string $name)
    {
        return self::$configs[$name];
    }

    public static function getValue(string $name)
    {
        if (!isset(self::$configs[$name])) {
            throw new \Exception(gettext('An invalid configuration name has been requested') . ': ' . $name);
        }

        return self::$configs[$name]->getValue();
    }

    public static function getBooleanValue(string $name): bool
    {
        if (!isset(self::$configs[$name])) {
            throw new \Exception(gettext('An invalid configuration name has been requested') . ': ' . $name);
        }

        return self::$configs[$name]->getBooleanValue();
    }

    public static function setValue(string $name, $value): void
    {
        if (!isset(self::$configs[$name])) {
            throw new \Exception(gettext('An invalid configuration name has been requested') . ': ' . $name);
        }

        self::$configs[$name]->setValue($value);
    }

    public static function setValueById(string $Id, $value): void
    {
        $success = false;
        foreach (self::$configs as $configItem) {
            if ($configItem->getId() == $Id) {
                $configItem->setValue($value);
                $success = true;
            }
        }
        if (!$success) {
            throw new \Exception(gettext('An invalid configuration id has been requested') . ': ' . $Id);
        }
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

    public static function hasValidSMSServerSettings(): bool
    {
        return (!empty(self::getValue('sNexmoAPIKey'))) && (!empty(self::getValue('sNexmoAPISecret'))) && (!empty(self::getValue('sNexmoFromNumber')));
    }

    public static function hasValidOpenLPSettings(): bool
    {
        return !empty(self::getValue('sOLPURL'));
    }

    public static function debugEnabled(): bool
    {
        if (self::getValue('sLogLevel') == Logger::DEBUG) {
            return true;
        }

        return false;
    }
}
