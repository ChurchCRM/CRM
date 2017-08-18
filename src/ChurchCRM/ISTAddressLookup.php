<?php

namespace ChurchCRM;

class ISTAddressLookup
{
    // This code is written to work with XML lookups provide by
    // Intelligent Search Technology, Ltd.
    // https://www.intelligentsearch.com/Hosted/User/

    public function GetAddress1()
    {
        return $this->DeliveryLine1;
    }

    public function GetAddress2()
    {
        return $this->DeliveryLine2;
    }

    public function GetCity()
    {
        return $this->City;
    }

    public function GetState()
    {
        return $this->State;
    }

    public function GetZip()
    {
        return $this->ZipAddon;
    }

    public function GetZip5()
    {
        return $this->Zip;
    }

    public function GetZip4()
    {
        return $this->Addon;
    }

    public function GetLOTNumber()
    {
        return $this->LOTNumber;
    }

    public function GetDPCCheckdigit()
    {
        return $this->DPCCheckdigit;
    }

    public function GetRecordType()
    {
        return $this->RecordType;
    }

    public function GetLastLine()
    {
        return $this->LastLine;
    }

    public function GetCarrierRoute()
    {
        return $this->CarrierRoute;
    }

    public function GetReturnCode()
    {
        return $this->ReturnCode;
    }

    public function GetReturnCodes()
    {
        return $this->ReturnCodes;
    }

    public function GetErrorCodes()
    {
        return $this->ErrorCodes;
    }

    public function GetErrorDesc()
    {
        return $this->ErrorDesc;
    }

    public function GetSearchesLeft()
    {
        return $this->SearchesLeft;
    }

    public function SetAddress($address1, $address2, $city, $state)
    {
        $this->address1 = trim($address1);
        $this->address2 = trim($address2);
        $this->city = trim($city);
        $this->state = trim($state);
    }

    public function getAccountInfo($sISTusername, $sISTpassword)
    {
        // Returns account related information.  Currently, it is used to retrieve
        // remaining number of transactions.  Return codes are:
        // 0 - information retrieved successfully
        // 1 - invalid account
        // 2 - account is disabled
        // 3 - account does not have access to CorrectAddress(R) XML web services
        // 4 - unspecified error

        $base = 'https://www.intelligentsearch.com/CorrectAddressWS/';
        $base .= 'CorrectAddressWebService.asmx/wsGetAccountInfo';

        $query_string = '';
        $XmlArray = null;

        //NOTE: Fields with * sign are required.
        //intializing the parameters

        $username = $sISTusername;                //  * (Type your username)
    $password = $sISTpassword;                //  * (Type your password)

    $params = [
        'username' => $username,
        'password' => $password,
    ];

        $query_string = 'username='.$username.'&password='.$password;

        $url = "$base?$query_string";

        $response = file_get_contents($url);

        //        $fp = fopen('/path/to/debug/file.txt', 'w+');
        //        fwrite($fp, $response . "\n" . $url);
        //        fclose($fp);
        // Initialize return values to NULL
        $this->SearchesLeft = '';
        $this->ReturnCode = '';

        if (!$response) {
            $this->ReturnCode = '9';
            $this->SearchesLeft = 'Connection failure: '.$base.'<br>';
            $this->SearchesLeft .= ' Incorrect server name and/or path or server unavailable.<br>';

            // This feature requires that "allow_url_fopen = On" in the php.ini file.
            if (!ini_get('allow_url_fopen')) {
                $this->SearchesLeft .= '<br>IMPORTANT: This feature requires "allow_url_fopen = On" in php.ini';
                $this->SearchesLeft .= '<br>Please check your php.ini file for "allow_url_fopen"<br>';
            }
        } else {
            $this->ReturnCode = XMLparseIST($response, 'ReturnCode');

            switch ($this->ReturnCode) {
        case '0':
          $this->SearchesLeft = XMLparseIST($response, 'SearchesLeft');
          break;
        case '1':
          $this->SearchesLeft = 'Invalid Account';
          break;
        case '2':
          $this->SearchesLeft = 'Account is disabled';
          break;
        case '3':
          $this->SearchesLeft = 'Account does not have access to CorrectAddress(R)';
          $this->SearchesLeft .= ' XML web services';
          break;
        default:
          $this->SearchesLeft = 'Error';
          break;
      }
        }
    }

    public function wsCorrectA($sISTusername, $sISTpassword)
    {

    // Lookup and Correct US address

        $base = 'https://www.intelligentsearch.com/CorrectAddressWS/';
        $base .= 'CorrectAddressWebService.asmx/wsCorrectA';
        //        $base    .= "CorrectAddressWebService.asmx/wsTigerCA";

        $query_string = '';
        $XmlArray = null;

        //NOTE: Fields with * sign are required.
        //intializing the parameters

        $username = $sISTusername;                //  * (Type your username)
    $password = $sISTpassword;                //  * (Type your password)
    $firmname = '';                           // optional
    $urbanization = '';                           // optional
    $delivery_line_1 = $this->address1;              //  * (Type the street address1)
    $delivery_line_2 = $this->address2;                  // optional
    $city_state_zip = $this->city.' '.$this->state; //  *
    $ca_codes = '128            135         139'; //  *
    $ca_filler = '';                              //  *
    $batchname = '';                               // optional

    $params = [
        'username'        => $username,
        'password'        => $password,
        'firmname'        => $firmname,
        'urbanization'    => $urbanization,
        'delivery_line_1' => $delivery_line_1,
        'delivery_line_2' => $delivery_line_2,
        'city_state_zip'  => $city_state_zip,
        'ca_codes'        => $ca_codes,
        'ca_filler'       => $ca_filler,
        'batchname'       => $batchname,
    ];

        foreach ($params as $key => $value) {
            $query_string .= "$key=".urlencode($value).'&';
        }

        $url = "$base?$query_string";

        $response = file_get_contents($url);

        //        $fp = fopen('/var/www/html/message.txt', 'w+');
        //        fwrite($fp, $response . "\n" . $url);
        //        fclose($fp);
        // Initialize return values
        $this->DeliveryLine1 = '';
        $this->DeliveryLine2 = '';
        $this->City = '';
        $this->State = '';
        $this->ZipAddon = '';
        $this->Zip = '';
        $this->Addon = '';
        $this->LOTNumber = '';
        $this->DPCCheckdigit = '';
        $this->RecordType = '';
        $this->LastLine = '';
        $this->CarrierRoute = '';
        $this->ReturnCodes = '';
        $this->ErrorCodes = '';
        $this->ErrorDesc = '';
        $this->SearchesLeft = '';

        if (!$response) {
            $this->DeliveryLine1 = "Connection failure.\n";
            $this->DeliveryLine1 .= $base."\n";
            $this->DeliveryLine1 .= 'Incorrect server name and/or path or server unavailable.';
            $this->ErrorCodes = 'xx';
            $this->ErrorDesc = 'Incorrect server name and/or path or server unavailable.';
            $this->SearchesLeft = '0';
        } else {
            $this->DeliveryLine1 = XMLparseIST($response, 'DeliveryLine1');
            $this->DeliveryLine2 = XMLparseIST($response, 'DeliveryLine2');
            $this->City = XMLparseIST($response, 'City');
            $this->State = XMLparseIST($response, 'State');
            $this->ZipAddon = XMLparseIST($response, 'ZipAddon');
            $this->Zip = XMLparseIST($response, 'Zip');
            $this->Addon = XMLparseIST($response, 'Addon');
            $this->LOTNumber = XMLparseIST($response, 'LOTNumber');
            $this->DPCCheckdigit = XMLparseIST($response, 'DPCCheckdigit');
            $this->RecordType = XMLparseIST($response, 'RecordType');
            $this->LastLine = XMLparseIST($response, 'LastLine');
            $this->CarrierRoute = XMLparseIST($response, 'CarrierRoute');
            $this->ReturnCodes = XMLparseIST($response, 'ReturnCodes');
            $this->ErrorCodes = XMLparseIST($response, 'ErrorCodes');
            $this->ErrorDesc = XMLparseIST($response, 'ErrorDesc');
            $this->SearchesLeft = XMLparseIST($response, 'SearchesLeft');

            //            if(strlen(XMLparseIST($response,"ErrorCodes"))){
//                if (XMLparseIST($response,"ErrorCodes") != "x1x2") {
//                    $this->DeliveryLine1 .= " " . XMLparseIST($response,"ErrorCodes");
//                    $this->DeliveryLine1 .= " " . XMLparseIST($response,"ErrorDesc");
//                }
//            }
//            if (XMLparseIST($response,"ReturnCodes") > 1) {
//                $this->DeliveryLine1 .= " Multiple matches.  Unable to determine proper match.";
//            }
//            if (XMLparseIST($response,"ReturnCodes") < 1) {
//                $this->DeliveryLine1 .= " No match found.";
//            }
        }
    }
}
