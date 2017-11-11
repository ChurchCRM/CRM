<?php

namespace ChurchCRM\dto;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\PersonQuery;
use ChurchCRM\FamilyQuery;

class Photo
{
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
    $this->photoThumbURI = SystemURLs::getImagesRoot() . "/" . $photoType . "/thumbnails/" . $id . ".jpg"; 
  }
  
  private function photoHunt($baseName) {
    $extensions = Photo::$validExtensions;
    foreach($extensions as $ext) 
    {
      $photoFiles = array($baseName . "." . $ext,$baseName . "-remote." . $ext,$baseName . "-initials." . $ext);
      foreach ($photoFiles as $photoFile)
      {
        if (file_exists($photoFile)) {
          return $photoFile;
         }
      }
    }
    # we still haven't found a photo file.  Begin checking remote if it's enabled
    # only check google and gravatar for person photos.
    if ($this->photoType == "Person") {
      if (SystemConfig::getBooleanValue('bEnableGooglePhotos')) {
        $personEmail = PersonQuery::create()->findOneById($this->id)->getEmail();
        $photoPath =  $this->loadFromGoogle($personEmail, $baseName);
        if ($photoPath) {
          return $photoPath;
        }
      }
    
      if (SystemConfig::getBooleanValue('bEnableGravatarPhotos')) {
        $personEmail = PersonQuery::create()->findOneById($this->id)->getEmail();
        $photoPath = $this->loadFromGravatar($personEmail,  $baseName);
        if ($photoPath) {
          return $photoPath;
        }
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
    $thumbWidth = SystemConfig::getValue("iThumbnailWidth");
    $img =  $this->getGDImage($this->photoURI); //just in case we have legacy JPG/GIF that don't have a thumbnail.
    $width = imagesx( $img );
    $height = imagesy( $img );
    $new_width = $thumbWidth;
    $new_height = floor( $height * ( $thumbWidth / $width ) );
    $tmp_img = imagecreatetruecolor( $new_width, $new_height );
    imagecopyresized( $tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height );
    imagejpeg($tmp_img, $this->photoThumbURI, 50);
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
    $finfo = new \finfo(FILEINFO_MIME);
    $this->photoContentType = $finfo->file($this->photoURI);
    return $this->photoContentType;
  }
  
  public function getThumbnailContentType() {
    $finfo = new \finfo(FILEINFO_MIME);
    $this->thumbnailContentType = $finfo->file($this->photoThumbURI);
    return $this->thumbnailContentType;
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

  private function loadFromGravatar($email, $baseName) {
    $s = 60;
    $d = '404';
    $r = 'g';
    $img = false;
    $atts = [];
    $url = 'https://www.gravatar.com/avatar/';
    $url .= md5(strtolower(trim($email)));
    $url .= "?s=$s&d=$d&r=$r";

    $photo = imagecreatefromstring(file_get_contents($url));
    if ($photo){
      $photoPath = $baseName."-remote.png";
      imagepng($photo, $photoPath);
      return $photoPath;
    }
    return false;
  }

  private function loadFromGoogle($email, $baseName) {
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
          $photo = imagecreatefromstring(file_get_contents($photoURL));
          if ($photo){
            $photoPath = $baseName."-remote.png";
            imagepng($photo, $photoPath);
            return $photoPath;
          }
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
  
  private function getInitialsString() {
    $retstr = "";
    if ($this->photoType == "Person")
    {
      $fullNameArr = explode(" ",PersonQuery::create()->findOneById($this->id)->getFullName());
      foreach ($fullNameArr as $name)
      {
        $retstr .= substr($name, 0,1);
      }

    }
    elseif ($this->photoType == "Family" )
    {
      $fullNameArr = explode(" ",  FamilyQuery::create()->findOneById($this->id)->getName());
      foreach ($fullNameArr as $name)
      {
        $retstr .= substr($name, 0,1);
      }
    }
    return $retstr;
  }
  
  private function renderInitials() {
    $initials = $this->getInitialsString();
    $targetPath = SystemURLs::getImagesRoot() . "/" . $this->photoType . "/" . $this->id."-initials.png";
    $height = SystemConfig::getValue("iPhotoHeight");
    $width= SystemConfig::getValue("iPhotoWidth");
    $pointSize = SystemConfig::getValue("iInitialsPointSize");
    $font = SystemURLs::getDocumentRoot()."/fonts/Roboto-Regular.ttf";
    //(0.5*$width)-(0.75*$pointSize),(0.5*$height)+(0.40*$pointSize)
    
    $image = imagecreatetruecolor($width,$height);
    $bgcolor = $this->getRandomColor($image);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0,$height , $width, $bgcolor);
    $tb = imagettfbbox($pointSize, 0, $font, $initials);
    $x = ceil(($width - $tb[2]) / 2);
    $y = ceil(($height - $tb[5]) / 2);
    imagefttext($image, $pointSize, 0, $x, $y, $white, $font, $initials);
    imagepng($image,$targetPath);
    return $targetPath;
  }
  
  public function setImageFromBase64($base64) {
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
  
  public function delete() {
    $deleted = false;
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
    return $deleted;
  }  
}