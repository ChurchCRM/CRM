<?php

namespace ChurchCRM\dto;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;

class Photo
{
  
  private $photoLocation;
  private $photoType;
  private $id;
  private $photoURI;
  private $photoThumbURI;
  private $photoContentType;
  public static $validExtensions = array("jpeg", "jpg", "png");
  
  public function __construct($photoType,$id) {
    $this->photoType = $photoType;
    $this->id = $id;
    $this->photoURI = $this->photoHunt(SystemURLs::getImagesRoot() . "/" . $photoType . "/" . $id); 
    $this->photoThumbURI = $this->photoHunt(SystemURLs::getImagesRoot() . "/" . $photoType . "/thumbnails/" . $id);
    if ($this->photoURI)
    {
      $this->photoLocation = "local";
    }
  }
  
  private function photoHunt($baseName)
  {
    $extensions = Photo::$validExtensions;
    while (list(, $ext) = each($extensions)) {
      $photoFile = $baseName . "." . $ext;
      if (file_exists($photoFile)) {
       return $photoFile;
      }
    }
    return null;
  }
  
  private function createThumbnail()      
  {
    $this->photoThumbURI = SystemURLs::getImagesRoot() . "/" . $this->photoType . "/thumbnails/" . $this->id.".png";
    $thumbWidth = 100;
    $img =  imagecreatefrompng($this->photoURI); 
    $width = imagesx( $img );
    $height = imagesy( $img );
    $new_width = $thumbWidth;
    $new_height = floor( $height * ( $thumbWidth / $width ) );
    $tmp_img = imagecreatetruecolor( $new_width, $new_height );
    imagecopyresized( $tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
    imagepng($tmp_img, $this->photoThumbURI);
  }
  
  public function getThumbnailBytes()
  {
    if (!file_exists($this->photoThumbURI))
    {
      $this->createThumbnail();
    }
    return file_get_contents($this->photoThumbURI);
  }
  
  public function getPhotoBytes()
  {
    return file_get_contents($this->photoURI);
  }
  
  public function getPhotoContentType()
  {
    if ($this->photoContentType)
    {
      return ;
    }
    else
    {
      $finfo = new \finfo(FILEINFO_MIME);
      $this->photoContentType = $finfo->file($this->photoURI);
      return $this->photoContentType;
    }
    
  }
  
  
  public function getThumbnailURI()
  {
    return $this->photoThumbURI;
  }
  
  public function isPhotoLocal()
  {
    return ($this->photoLocation == "local");
  }
  
  public function loadFromGravatar($email, $s = 60, $d = '404', $r = 'g', $img = false, $atts = [])
  {

    $url = 'http://www.gravatar.com/avatar/';
    $url .= md5(strtolower(trim($email)));
    $url .= "?s=$s&d=$d&r=$r";

    $headers = @get_headers($url);
    if (strpos($headers[0], '404') === false) {
        $this->photoURI =  $url;
        $this->photoThumbURI = $url;
        $this->photoLocation = "remote";
    }
  }
  
  public function setImageFromBase64($base64)
  {
      $this->delete();
      $fileName = SystemURLs::getImagesRoot() . "/" . $this->photoType . "/" . $this->id.".png";
      $img = str_replace('data:image/png;base64,', '', $base64);
      $img = str_replace(' ', '+', $img);
      $fileData = base64_decode($img);
      $finfo = new \finfo(FILEINFO_MIME);
      if ($finfo->buffer($fileData) == "image/png; charset=binary")
      {
        //file_put_contents( $fileName , $fileData);
      }

  }
  
  public function delete()
  {
    echo "deleting";
    print_r($this);
    exit;
    $deleted = false;
    if ($this->isPhotoLocal())
    {
      if (file_exists($this->photoURI))
      {
        unlink ($this->photoURI);
        $deleted = true;
      }
      if (file_exists($this->photoThumbURI))
      {
        unlink($this->photoThumbURI);
        $deleted = true;
      }
    }
    return $deleted;
  }
  
  private static function processFile($type,$id,$filename)
  {
      

  }
  
  
}