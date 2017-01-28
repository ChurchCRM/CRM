<?php

namespace ChurchCRM\dto;

use ChurchCRM\Config;
Use ChurchCRM\dto\ConfigItem;

class SystemConfig
{
    /**
   * @var Config[]
   */
  private static $configs;
  private static $categories;
  
  private static function buildConfigs()
  {
    return array(
        "debug" => new ConfigItem(2, "debug", "boolean", "1", "Set debug mode\r\nThis may be helpful for when you're first setting up ChurchCRM, but you should\r\nprobably turn it off for maximum security otherwise.  If you are having trouble,\r\nplease enable this so that you'll know what the errors are.  This is especially\r\nimportant if you need to report a problem on the help forums."),
        "sDirClassifications" => new ConfigItem(5, "sDirClassifications", "text", "1,2,4,5", "Include only these classifications in the directory, comma seperated"),
        "sDirRoleHead" => new ConfigItem(6, "sDirRoleHead", "text", "1", "These are the family role numbers designated as head of house"),
        "sDirRoleSpouse" => new ConfigItem(7, "sDirRoleSpouse", "text", "2", "These are the family role numbers designated as spouse"),
        "sDirRoleChild" => new ConfigItem(8, "sDirRoleChild", "text", "3", "These are the family role numbers designated as child"),
        "sSessionTimeout" => new ConfigItem(9, "sSessionTimeout", "number", "3600", "Session timeout length in seconds\rSet to zero to disable session timeouts."),
        "aFinanceQueries" => new ConfigItem(10, "aFinanceQueries", "text", "30,31,32", "Queries for which user must have finance permissions to use:"),
        "bCSVAdminOnly" => new ConfigItem(11, "bCSVAdminOnly", "boolean", "1", "Should only administrators have access to the CSV export system and directory report?"),
        "sDefault_Pass" => new ConfigItem(12, "sDefault_Pass", "text", "password", "Default password for new users and those with reset passwords"),
        "sMinPasswordLength" => new ConfigItem(13, "sMinPasswordLength", "number", "6", "Minimum length a user may set their password to"),
        "sMinPasswordChange" => new ConfigItem(14, "sMinPasswordChange", "number", "4", "Minimum amount that a new password must differ from the old one (# of characters changed)\rSet to zero to disable this feature"),
        "sDisallowedPasswords" => new ConfigItem(15, "sDisallowedPasswords","text", "password,god,jesus,church,christian", "text", "churchcrm,password,god,jesus,church,christian", "A comma-seperated list of disallowed (too obvious) passwords."),
        "iMaxFailedLogins" => new ConfigItem(16, "iMaxFailedLogins", "number", "5", "Maximum number of failed logins to allow before a user account is locked.\rOnce the maximum has been reached, an administrator must re-enable the account.\rThis feature helps to protect against automated password guessing attacks.\rSet to zero to disable this feature."),
        "iPDFOutputType" => new ConfigItem(20, "iPDFOutputType", "number", "1", "PDF handling mode.\r1 = Save File dialog\r2 = Open in current browser window"),
        "sDefaultCity" => new ConfigItem(21, "sDefaultCity", "text", "", "Default City"),
        "sDefaultState" => new ConfigItem(22, "sDefaultState", "text", "", "Default State - Must be 2-letter abbreviation!"),
        "sDefaultCountry" => new ConfigItem(23, "sDefaultCountry", "country", "United States", "Default Country"),
        "bEmailSend" => new ConfigItem(24, "bEmailSend", "boolean", "", "If you wish to be able to send emails from within ChurchCRM. This requires\reither an SMTP server address to send from or sendmail installed in PHP."),
        "sSendType" => new ConfigItem(25, "sSendType", "choice", "smtp", "The method for sending email. Either 'smtp' or 'sendmail'", '{"Choices":["smtp","SendMail"]}'),
        "sToEmailAddress" => new ConfigItem(26, "sToEmailAddress", "text", "", "Default account for receiving a copy of all emails"),
        "sSMTPHost" => new ConfigItem(27, "sSMTPHost", "text", "", "SMTP Server Address (mail.server.com:25)"),
        "sSMTPAuth" => new ConfigItem(28, "sSMTPAuth", "boolean", "0", "Does your SMTP server require auththentication (username/password)?"),
        "sSMTPUser" => new ConfigItem(29, "sSMTPUser", "text", "", "SMTP Username"),
        "sSMTPPass" => new ConfigItem(30, "sSMTPPass", "text", "", "SMTP Password"),
        "bShowFamilyData" => new ConfigItem(33, "bShowFamilyData", "boolean", "1", "Unavailable person info inherited from assigned family for display?\rThis option causes certain info from a person's assigned family record to be\rdisplayed IF the corresponding info has NOT been entered for that person. "),
        "sGZIPname" => new ConfigItem(36, "sGZIPname", "text", "gzip", ""),
        "sZIPname" => new ConfigItem(37, "sZIPname", "text", "zip", ""),
        "sPGPname" => new ConfigItem(38, "sPGPname", "text", "gpg", ""),
        "sLanguage" => new ConfigItem(39, "sLanguage", "choice", "en_US", "Internationalization (I18n) support", '{"Choices":["English - United States:en_US","English - Canada:en_CA", "English - Australia:en_AU", "English - Great Britain:en_GB", "German - Germany:de_DE", "Spanish - Spain:es_ES", "French - France:fr_FR", "Hungarian:hu_HU", "Italian - Italy:it_IT", "Norwegian:nb_NO", "Dutch - Netherlands:nl_NL", "Polish:pl_PL", "Portuguese - Brazil:pt_BR", "Romanian - Romania:ro_RO", "Russian:ru_RU", "Sami (Northern) (Sweden):se_SE", "Albanian:sq_AL", "Swedish - Sweden:sv_SE", "Vietnamese:vi_VN", "Chinese - China:zh_CN", "Chinese - Taiwan:zh_TW"]}'),
        "iFYMonth" => new ConfigItem(40, "iFYMonth", "number", "1", "First month of the fiscal year"),
        "sXML_RPC_PATH" => new ConfigItem(41, "sXML_RPC_PATH", "text", "XML/RPC.php", "Path to RPC.php, required for Lat/Lon address lookup"),
        "sGeocoderID" => new ConfigItem(42, "sGeocoderID", "text", "", "User ID for rpc.geocoder.us"),
        "sGeocoderPW" => new ConfigItem(43, "sGeocoderPW", "text", "", "Password for rpc.geocoder.us"),
        "sGoogleMapKey" => new ConfigItem(44, "sGoogleMapKey", "text", "", "Google map API requires a unique key from https://developers.google.com/maps/documentation/javascript/get-api-key"),
        "nChurchLatitude" => new ConfigItem(45, "nChurchLatitude", "number", "", "Latitude of the church, used to center the Google map"),
        "nChurchLongitude" => new ConfigItem(46, "nChurchLongitude", "number", "", "Longitude of the church, used to center the Google map"),
        "bHidePersonAddress" => new ConfigItem(47, "bHidePersonAddress", "boolean", "1", "Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor."),
        "bHideFriendDate" => new ConfigItem(48, "bHideFriendDate", "boolean", "0", "Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor."),
        "bHideFamilyNewsletter" => new ConfigItem(49, "bHideFamilyNewsletter", "boolean", "0", "Set true to disable management of newsletter subscriptions in the Family Editor."),
        "bHideWeddingDate" => new ConfigItem(50, "bHideWeddingDate", "boolean", "0", "Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor."),
        "bHideLatLon" => new ConfigItem(51, "bHideLatLon", "boolean", "0", "Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed."),
        "bUseDonationEnvelopes" => new ConfigItem(52, "bUseDonationEnvelopes", "boolean", "0", "Set true to enable use of donation envelopes"),
        "sHeader" => new ConfigItem(53, "sHeader", "textarea", "", "Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! Note: You must REFRESH YOUR BROWSER A SECOND TIME to view the new header."),
        "sISTusername" => new ConfigItem(54, "sISTusername", "text", "username", "Intelligent Search Technolgy, Ltd. CorrectAddress Username for https://www.intelligentsearch.com/Hosted/User"),
        "sISTpassword" => new ConfigItem(55, "sISTpassword", "text", "", "Intelligent Search Technolgy, Ltd. CorrectAddress Password for https://www.intelligentsearch.com/Hosted/User"),
        "bUseGoogleGeocode" => new ConfigItem(56, "bUseGoogleGeocode", "boolean", "1", "Set true to use the Google geocoder.  Set false to use rpc.geocoder.us."),
        "iChecksPerDepositForm" => new ConfigItem(57, "iChecksPerDepositForm", "number", "14", "Number of checks for Deposit Slip Report"),
        "bUseScannedChecks" => new ConfigItem(58, "bUseScannedChecks", "boolean", "0", "Set true to enable use of scanned checks"),
        "sDistanceUnit" => new ConfigItem(64, "sDistanceUnit", "choice", "miles", "Unit used to measure distance, miles or km.", '{"Choices":["miles","kilometers"]}'),
        "sTimeZone" => new ConfigItem(65, "sTimeZone", "text", "America/New_York", "Time zone- see http://php.net/manual/en/timezones.php for valid choices."),
        "sGMapIcons" => new ConfigItem(66, "sGMapIcons","text", "green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue", "text", "red-dot,green-dot,purple,yellow-dot,blue-dot,orange,yellow,green,blue,red,pink,lightblue", "Names of markers for Google Maps in order of classification"),
        "cfgForceUppercaseZip" => new ConfigItem(67, "cfgForceUppercaseZip", "boolean", "0", "Make user-entered zip/postcodes UPPERCASE when saving to the database."),
        "bEnableNonDeductible" => new ConfigItem(72, "bEnableNonDeductible", "boolean", "0", "Enable non-deductible payments"),
        "sElectronicTransactionProcessor" => new ConfigItem(73, "sElectronicTransactionProcessor", "choice", "Vanco", "Electronic Transaction Processor", '{"Choices":["Vanco","Authorize.NET"]}'),
        "sEnableSelfRegistration" => new ConfigItem(80, "sEnableSelfRegistration", "boolean", "0", "Set true to enable family self registration."),
        "sPhoneFormat" => new ConfigItem(100, "sPhoneFormat", "text", "(999) 999-9999", ""),
        "sPhoneFormatWithExt" => new ConfigItem(101, "sPhoneFormatWithExt", "text", "(999) 999-9999 x99999", ""),
        "sDateFormatLong" => new ConfigItem(102, "sDateFormatLong", "text", "yyyy-mm-dd", ""),
        "sDateFormatNoYear" => new ConfigItem(103, "sDateFormatNoYear", "text", "DD/MM", ""),
        "sDateFormatShort" => new ConfigItem(104, "sDateFormatShort", "text", "yy-mm-dd", ""),
        "bRegistered" => new ConfigItem(999, "bRegistered", "boolean", "0", "ChurchCRM has been registered.  The ChurchCRM team uses registration information to track usage.  This information is kept confidential and never released or sold.  If this field is true the registration option in the admin menu changes to update registration."),
        "leftX" => new ConfigItem(1001, "leftX", "number", "20", "Left Margin (1 = 1/100th inch)"),
        "incrementY" => new ConfigItem(1002, "incrementY", "number", "4", "Line Thickness (1 = 1/100th inch"),
        "sChurchName" => new ConfigItem(1003, "sChurchName", "text", "", "Church Name"),
        "sChurchAddress" => new ConfigItem(1004, "sChurchAddress", "text", "", "Church Address"),
        "sChurchCity" => new ConfigItem(1005, "sChurchCity", "text", "", "Church City"),
        "sChurchState" => new ConfigItem(1006, "sChurchState", "text", "", "Church State"),
        "sChurchZip" => new ConfigItem(1007, "sChurchZip", "text", "", "Church Zip"),
        "sChurchPhone" => new ConfigItem(1008, "sChurchPhone", "text", "", "Church Phone"),
        "sChurchEmail" => new ConfigItem(1009, "sChurchEmail", "text", "", "Church Email"),
        "sHomeAreaCode" => new ConfigItem(1010, "sHomeAreaCode", "text", "", "Home area code of the church"),
        "sTaxReport1" => new ConfigItem(1011, "sTaxReport1", "text", "This letter shows our record of your payments for", "Verbage for top line of tax report. Dates will be appended to the end of this line."),
        "sTaxReport2" => new ConfigItem(1012, "sTaxReport2", "text", "Thank you for your help in making a difference. We greatly appreciate your gift!", "Verbage for bottom line of tax report."),
        "sTaxReport3" => new ConfigItem(1013, "sTaxReport3", "text", "If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.", "Verbage for bottom line of tax report."),
        "sTaxSigner" => new ConfigItem(1014, "sTaxSigner", "text", "", "Tax Report signer"),
        "sReminder1" => new ConfigItem(1015, "sReminder1", "text", "This letter shows our record of your pledge and payments for fiscal year", "Verbage for the pledge reminder report"),
        "sReminderSigner" => new ConfigItem(1016, "sReminderSigner", "text", "", "Pledge Reminder Signer"),
        "sReminderNoPledge" => new ConfigItem(1017, "sReminderNoPledge", "text", "Pledges: We do not have record of a pledge for from you for this fiscal year.", "Verbage for the pledge reminder report - No record of a pledge"),
        "sReminderNoPayments" => new ConfigItem(1018, "sReminderNoPayments", "text", "Payments: We do not have record of a pledge for from you for this fiscal year.", "Verbage for the pledge reminder report - No record of payments"),
        "sConfirm1" => new ConfigItem(1019, "sConfirm1", "text", "This letter shows the information we have in our database with respect to your family.  Please review, mark-up as necessary, and return this form to the church office.", "Verbage for the database information confirmation and correction report"),
        "sConfirm2" => new ConfigItem(1020, "sConfirm2", "text", "Thank you very much for helping us to update this information.  If you want on-line access to the church database please provide your email address and a desired password and we will send instructions.", "Verbage for the database information confirmation and correction report"),
        "sConfirm3" => new ConfigItem(1021, "sConfirm3", "text", "Email _____________________________________ Password ________________", "Verbage for the database information confirmation and correction report"),
        "sConfirm4" => new ConfigItem(1022, "sConfirm4", "text", "[  ] I no longer want to be associated with the church (check here to be removed from our records).", "Verbage for the database information confirmation and correction report"),
        "sConfirm5" => new ConfigItem(1023, "sConfirm5", "text", "", "Verbage for the database information confirmation and correction report"),
        "sConfirm6" => new ConfigItem(1024, "sConfirm6", "text", "", "Verbage for the database information confirmation and correction report"),
        "sConfirmSigner" => new ConfigItem(1025, "sConfirmSigner", "text", "", "Database information confirmation and correction report signer"),
        "sPledgeSummary1" => new ConfigItem(1026, "sPledgeSummary1", "text", "Summary of pledges and payments for the fiscal year", "Verbage for the pledge summary report"),
        "sPledgeSummary2" => new ConfigItem(1027, "sPledgeSummary2", "text", " as of", "Verbage for the pledge summary report"),
        "sDirectoryDisclaimer1" => new ConfigItem(1028, "sDirectoryDisclaimer1", "text", "Every effort was made to insure the accuracy of this directory.  If there are any errors or omissions, please contact the church office.\n\nThis directory is for the use of the people of", "Verbage for the directory report"),
        "sDirectoryDisclaimer2" => new ConfigItem(1029, "sDirectoryDisclaimer2", "text", ", and the information contained in it may not be used for business or commercial purposes.", "Verbage for the directory report"),
        "bDirLetterHead" => new ConfigItem(1030, "bDirLetterHead", "text", "../Images/church_letterhead.jpg", "Church Letterhead path and file"),
        "sZeroGivers" => new ConfigItem(1031, "sZeroGivers", "text", "This letter shows our record of your payments for", "Verbage for top line of tax report. Dates will be appended to the end of this line."),
        "sZeroGivers2" => new ConfigItem(1032, "sZeroGivers2", "text", "Thank you for your help in making a difference. We greatly appreciate your gift!", "Verbage for bottom line of tax report."),
        "sZeroGivers3" => new ConfigItem(1033, "sZeroGivers3", "text", "If you have any questions or corrections to make to this report, please contact the church at the above number during business hours, 9am to 4pm, M-F.", "Verbage for bottom line of tax report."),
        "sChurchChkAcctNum" => new ConfigItem(1034, "sChurchChkAcctNum", "text", "", "Church Checking Account Number"),
        "sEnableGravatarPhotos" => new ConfigItem(1035, "sEnableGravatarPhotos", "boolean", "0", "lookup user images on Gravatar when no local image is present"),
        "sEnableExternalBackupTarget" => new ConfigItem(1036, "sEnableExternalBackupTarget", "boolean", "0", "Enable Remote Backups to Cloud Services"),
        "sExternalBackupType" => new ConfigItem(1037, "sExternalBackupType", "choice", "", "Cloud Service Type (Supported values: WebDAV, Local)", "Step5", '{"Choices":["WebDAV","Local"]}'),
        "sExternalBackupEndpoint" => new ConfigItem(1038, "sExternalBackupEndpoint", "text", "", "Remote Backup Endpoint"),
        "sExternalBackupUsername" => new ConfigItem(1039, "sExternalBackupUsername", "text", "", "Remote Backup Username"),
        "sExternalBackupPassword" => new ConfigItem(1040, "sExternalBackupPassword", "text", "", "Remote Backup Password"),
        "sExternalBackupAutoInterval" => new ConfigItem(1041, "sExternalBackupAutoInterval", "text", "", "Interval in Hours for Automatic Remote Backups"),
        "sLastBackupTimeStamp" => new ConfigItem(1042, "sLastBackupTimeStamp", "text", "", "Last Backup Timestamp"),
        "sQBDTSettings" => new ConfigItem(1043, "sQBDTSettings", "json", '{"date1":{"x":"12","y":"42"},"date2X":"185","leftX":"64","topY":"7","perforationY":"97","amountOffsetX":"35","lineItemInterval":{"x":"49","y":"7"},"max":{"x":"200","y":"140"},"numberOfItems":{"x":"136","y":"68"},"subTotal":{"x":"197","y":"42"},"topTotal":{"x":"197","y":"68"},"titleX":"85"}' , "QuickBooks Deposit Ticket Settings"),
        "sEnableIntegrityCheck" => new ConfigItem(1044, "sEnableIntegrityCheck", "boolean", "1", "Enable Integrity Check"),
        "sIntegrityCheckInterval" => new ConfigItem(1045, "sIntegrityCheckInterval", "text", "168", "Interval in Hours for Integrity Check"),
        "sLastIntegrityCheckTimeStamp" => new ConfigItem(1046, "sLastIntegrityCheckTimeStamp", "text", "", "Last Integrity Check Timestamp"),
        "sChurchCountry" => new ConfigItem(1047, "sChurchCountry", "country", "", "Church Country"),
        "sConfirmSincerely" => new ConfigItem(1048, "sConfirmSincerely", "text", "Sincerely", "Used to end a letter before Signer"),
        "googleTrackingID" => new ConfigItem(1050, "googleTrackingID", "text", "", "Google Analytics Tracking Code"),
        "mailChimpApiKey" => new ConfigItem(2000, "mailChimpApiKey", "text", "", "see http://kb.mailchimp.com/accounts/management/about-api-keys")
      );
  }

  
  private static function buildCategories()
  {
    return array (
      gettext('Church Information') =>["nChurchLatitude","nChurchLongitude","sTimeZone","sChurchName","sChurchAddress","sChurchCity","sChurchState","sChurchZip","sChurchPhone","sChurchEmail","sHomeAreaCode","sChurchCountry"],
      gettext('User setup') => ["sSessionTimeout","sDefault_Pass","sMinPasswordLength","sMinPasswordChange","iMaxFailedLogins","sDisallowedPasswords"],
      gettext('Email Setup')  => ["sSendType","sToEmailAddress","sSMTPHost","sSMTPAuth","sSMTPUser","sSMTPPass","bEmailSend","mailChimpApiKey"],
      gettext('Member Setup')  => ["sEnableSelfRegistration","sDirClassifications","sDirRoleHead","sDirRoleSpouse","sDirRoleChild","sDefaultCity","sDefaultState","sDefaultCountry","bShowFamilyData","sEnableGravatarPhotos","bHidePersonAddress","bHideFriendDate","bHideFamilyNewsletter","bHideWeddingDate","bHideLatLon","cfgForceUppercaseZip"],
      gettext('System Settings')  => ["bCSVAdminOnly","bRegistered","sGZIPname","sZIPname","sPGPname","sXML_RPC_PATH","sHeader","debug","sEnableExternalBackupTarget","sExternalBackupType","sExternalBackupEndpoint","sExternalBackupUsername","sExternalBackupPassword","sExternalBackupAutoInterval","sLastBackupTimeStamp","sIntegrityCheckInterval","sLastIntegrityCheckTimeStamp"],
      gettext('Map Settings')  => ["sGeocoderID","sGeocoderPW","sGoogleMapKey","sISTusername","sISTpassword","bUseGoogleGeocode","sGMapIcons"],
      gettext('Report Settings')  => ["leftX","incrementY","sTaxReport1","sTaxReport2","sTaxReport3","sTaxSigner","sReminder1","sReminderSigner","sReminderNoPledge","sReminderNoPayments","sConfirm1","sConfirm2","sConfirm3","sConfirm4","sConfirm5","sConfirm6","sConfirmSigner","sPledgeSummary1","sPledgeSummary2","sDirectoryDisclaimer1","sDirectoryDisclaimer2","bDirLetterHead","sZeroGivers","sZeroGivers2","sZeroGivers3","sQBDTSettings","sConfirmSincerely"],
      gettext('Localization')  => ["sLanguage","sDistanceUnit","sPhoneFormat","sPhoneFormatWithExt","sDateFormatLong","sDateFormatNoYear","sDateFormatShort"],
      gettext('Other Settings')  => ["aFinanceQueries","iPDFOutputType","iFYMonth","bUseDonationEnvelopes","iChecksPerDepositForm","bUseScannedChecks","bEnableNonDeductible","sElectronicTransactionProcessor","sEnableIntegrityCheck","googleTrackingID"]
    );
  }
  
  /**
   * @param Config[] $configs
   */
  public static function init($configs)
  {
      self::$configs = self::buildConfigs();
      self::$categories = self::buildCategories();
      self::scrapeDBConfigs($configs);
  }
  
  public static function getCategories()
  {
    return self::$categories;
  }
  
  private static function scrapeDBConfigs($configs)
  {
    foreach ($configs as $config)
    {
      if ( isset( self::$configs[$config->getName()]))
      {
        //if the current config set defined by code contains the current config retreived from the db, then cache it 
        self::$configs[$config->getName()]->setDBConfigObject($config);
      }
      else
      {
        //there's a config item in the DB that doesn't exist in the current code.
        //delete it
        $config->delete();
      }
    }
  }
  
    public static function getConfigItem($name)
    {
      return self::$configs[$name];
    }

    public static function getValue($name)
    {
      return self::$configs[$name]->getValue();
    }

    public static function getBooleanValue($name)
    {
        return self::$configs[$name]->getValue();
    }

    public static function setValue($name, $value)
    {
       self::$configs[$name]->setValue($value);
    }

    public static function setValueById($Id, $value)
    {
        $config = self::getRawConfigById($Id);
        if (!is_null($config)) {
            $config->setValue($value);
            $config->save();
        }
    }

   
}
