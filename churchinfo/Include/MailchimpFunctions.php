<?php
/**
 * User: gdawoud
 * Date: 12/9/2014
 * Time: 11:00 PM
 */
require_once 'Mailchimp.php';

class ChurchInfoMailchimp {

    private $myMailchimp;

    public function __construct() {

        $apikey = "";
        // Read in report settings from database
        $rsConfig = mysql_query("SELECT cfg_name, IFNULL(cfg_value, cfg_default) AS value FROM config_cfg WHERE cfg_name='mailChimpApiKey'");
        if ($rsConfig) {
            while (list($cfg_name, $cfg_value) = mysql_fetch_row($rsConfig)) {
               $apikey = $cfg_value;
            }
        }

        $this->myMailchimp = new MailChimp($apikey);
    }

    function isEmailInMailChimp($email) {
        try {
            $lists = $this->myMailchimp->helper->listsForEmail(array("email" => $email));
            return $lists[0]["name"];
        } catch (Exception $e) {
            return "";
        }
    }

}