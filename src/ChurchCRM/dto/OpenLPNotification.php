<?php

namespace ChurchCRM\dto;


class OpenLPNotification
{
  protected $OLPAddress;
  protected $OLPUsername;
  protected $OLPPassword;
  protected $AlertText;
 
  public function __construct($OLPAddress,$OLPUsername,$OLPPassword)
  {
    $this->OLPAddress=$OLPAddress;
    $this->OLPUsername=$OLPUsername;
    $this->OLPPassword=$OLPPassword;
  }
  
  public function setAlertText($text)
  {
    $this->AlertText = (string)$text;
  }
  
  private function getAuthorizationHeader()
  {
    return base64_encode(SystemConfig::getValue("sOLPUserName").":".SystemConfig::getValue("sOLPPassword"));
  }
  
  public function send()
  {
    $headers = array (
      'http'=>array(
        'method' =>"GET",
        'timeout' => 5
      ),
      "ssl" => array(
          "verify_peer" => false,
          "verify_peer_name" => false,
          "allow_self_signed" => true,
      )
    );
    if(SystemConfig::getValue("sOLPUserName"))
    {
      $headers['http']['header'] = "Authorization: Basic ".$this->getAuthorizationHeader()."\r\n";
    }
    //return json_encode($headers);
    $request = array(
      "request" => array(
        "text" =>$this->AlertText
      )
    );
    $url = $this->OLPAddress."/api/alert?data=".urlencode(json_encode($request));
    $context = stream_context_create($headers);
    $response = file_get_contents($url,false,$context);
    return $response;
  }
}