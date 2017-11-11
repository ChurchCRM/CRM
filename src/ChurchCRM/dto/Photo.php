<?php

namespace ChurchCRM\dto;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;

class Photo
{
  
  private $photoLocation;
  private $photoType;
  private $id;
  private $photoURI;
  private $photoThumbURI;
  private $photoContentType;
  public static $validExtensions = ["png", "jpeg", "jpg"];

  public function __construct($photoType,$id) {
    $this->photoType = $photoType;
    $this->id = $id;
    $this->photoURI = $this->photoHunt(SystemURLs::getImagesRoot() . "/" . $photoType . "/" . $id); 
  }
  
  private function photoHunt($baseName) {
    $extensions = Photo::$validExtensions;
    foreach($extensions as $ext) 
    {
      $photoFile = $baseName . "." . $ext;
      if (file_exists($photoFile)) {
       return $photoFile;
      }
    }
    # we still haven't found a photo file.  Begin checking remote if it's enabled
    if (SystemConfig::getBooleanValue('bEnableGooglePhotos')) {
        //return  $this->loadFromGoogle($this->getEmail());
    }
    if (SystemConfig::getBooleanValue('bEnableGravatarPhotos')) {
        if ($this>photoType == "Person")
        {
          $personEmail = PersonQuery::create()->findOneById($this->id)->getEmail();
          $photoPath = $this->loadFromGravatar($personEmail);
          if ($photoPath) {
            return $photoPath;
          }
         
        }
        elseif ($this>photoType == "Family" )
        {
          
        }
        
    }
 
    # stil no image - generate it from initials
    return $this->renderInitials();
  }
  
  private function getGDImage($sourceImagePath) {
    $sourceImageType = exif_imagetype($sourceImagePath);
    switch ($sourceImageType) 
    {
        case IMAGETYPE_GIF:
            $sourceGDImage = imagecreatefromgif($sourceImagePath);
            break;
        case IMAGETYPE_JPEG:
            $sourceGDImage = imagecreatefromjpeg($sourceImagePath);
            break;
        case IMAGETYPE_PNG:
            $sourceGDImage = imagecreatefrompng($sourceImagePath);
            break;
    }
    return $sourceGDImage;
  }
  
  private function createThumbnail() {
    $this->photoThumbURI = SystemURLs::getImagesRoot() . "/" . $this->photoType . "/thumbnails/" . $this->id.".png";
    $thumbWidth = 100;
    $img =  $this->getGDImage($this->photoURI); //just in case we have legacy JPG/GIF that don't have a thumbnail.
    $width = imagesx( $img );
    $height = imagesy( $img );
    $new_width = $thumbWidth;
    $new_height = floor( $height * ( $thumbWidth / $width ) );
    $tmp_img = imagecreatetruecolor( $new_width, $new_height );
    imagecopyresized( $tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
    imagepng($tmp_img, $this->photoThumbURI);
  }

  public function getThumbnailBytes() {
    if (!file_exists($this->photoThumbURI))
    {
      $this->createThumbnail();
    }
    return file_get_contents($this->photoThumbURI);
  }

  public function getPhotoBytes() {
    return file_get_contents($this->photoURI);
  }

  public function getPhotoContentType() {
    if ($this->isPhotoRemote())
    {
      return;
    }
    else
    {
      $finfo = new \finfo(FILEINFO_MIME);
      $this->photoContentType = $finfo->file($this->photoURI);
      return $this->photoContentType;
    }
  }

  public function getThumbnailURI() {
    if (!file_exists($this->photoThumbURI))
    {
      $this->createThumbnail();
    }
    return $this->photoThumbURI;
  }
  
  public function getPhotoURI() {
    return $this->photoURI;
  }
  
  public function isPhotoLocal() {
    return ($this->photoLocation == "local");
  }
  
   public function isPhotoRemote() {
      return ($this->photoLocation == "remote");
   }
  
  private function loadFromGravatar($email, $s = 60, $d = '404', $r = 'g', $img = false, $atts = []) {
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5(strtolower(trim($email)));
    $url .= "?s=$s&d=$d&r=$r";
  
    $targetPath = SystemURLs::getImagesRoot() . "/" . $this->photoType . "/" . $this->id.".png";
    $photo = imagecreatefromstring(file_get_contents($url));
    if ($photo){
      imagepng($photo,$targetPath);
      return $targetPath;
    }
    return null;
   

  }

  private function loadFromGoogle($email) {
      $url = 'http://picasaweb.google.com/data/entry/api/user/';
      $url .= strtolower(trim($email));
      $url .= "?alt=json";
      $headers = @get_headers($url);
      if (strpos($headers[0], '404') === false) {
          $json = file_get_contents($url);
          if (!empty($json)) {
              $obj = json_decode($json);
              $photoEntry = $obj->entry;
              $photoURL = $photoEntry->{'gphoto$thumbnail'}->{'$t'};
              $this->photoURI =  $photoURL;
              $this->photoThumbURI = $photoURL;
              $this->photoLocation = "remote";
              return true;
        }
      }
      return false;
  }
  
  
  private function getRandomColor($image) {
    $red = rand(0, 150);
    $green = rand(0, 150);
    $blue = rand(0, 150);
    return imagecolorallocate($image, $red, $green, $blue);
  }
  
  private function getInitialsString()
  {
    $retstr = "";
    if ($this>photoType == "Person")
    {
      $fullNameArr = explode(" ",PersonQuery::create()->findOneById($this->id)->getFullName());
      foreach ($fullNameArr as $name)
      {
        $retstr .= substr($name, 0,1);
      }

    }
    elseif ($this>photoType == "Family" )
    {

    }
    return $retstr;
  }
  
  private function renderInitials() {
    $initials = $this->getInitialsString();
    $targetPath = SystemURLs::getImagesRoot() . "/" . $this->photoType . "/" . $this->id.".png";
    $image = imagecreatetruecolor(100,100);
    $bgcolor = $this->getRandomColor($image);
    imagefilledrectangle($image, 0, 0, 100, 100, $bgcolor);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefttext($image, 20, 0,30,50, $white, SystemURLs::getDocumentRoot()."/fonts/Roboto-Regular.ttf", $initials);
    imagepng($image,$targetPath);
    return $targetPath;
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
        file_put_contents( $fileName , $fileData);
      }

  }
  
  public function delete()
  {
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
}