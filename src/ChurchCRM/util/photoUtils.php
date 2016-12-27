<?php

namespace ChurchCRM\util
{
  
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\Note;
  
    class PhotoUtils
    {
        private static function processFile($type,$id,$filename)
        {
            $finalFileName = $id;
            $imageLocationMain = SystemURLs::getImagesRoot() . "/" . $type . "/";
            $imageLocationThumb = SystemURLs::getImagesRoot() . "/" . $type . "/thumbnails/";
            
            $foo = new \upload($filename);
            $foo->allowed = array('image/png', 'image/jpg', 'image/jpeg');

            if ($foo->uploaded) {
              $foo->file_new_name_body = $id;
              $foo->file_overwrite = true;
              $foo->Process($imageLocationMain);
              if (!$foo->processed) {
                return 'error 1 : ' . $foo->error. " MIME Type: ". $foo->file_src_mime;
              }
              

              $exif = exif_read_data($foo->file_dst_pathname);
              if ( !empty($exif['Orientation']) ) {
                switch ( $exif['Orientation'] ) {
                  case 3:
                    $foo->image_rotate = 180;
                    break;

                  case 6:
                    $foo->image_rotate =  90;
                    break;

                  case 8:
                    $foo->image_rotate = 270;
                    break;
                }
              }

              $foo->file_new_name_body = $finalFileName;
              $foo->file_overwrite = true;
              $foo->image_resize = true;
              $foo->image_ratio_fill = true;
              $foo->image_y = 250;
              $foo->image_x = 250;
              $foo->Process($imageLocationThumb);
              if (!$foo->processed) {
                return 'error 2 : ' . $foo->error;
              } else {
                $note = new Note();
                $note->setText("Profile Image Uploaded");
                $note->setType("photo");
                $note->setEntered($_SESSION['iUserID']);
                if ($type == "Person" ) {
                  $note->setPerId($id);
                  $note->save();
                } else if ($type == "Family") {
                  $note->setFamId($id);
                  $note->save();
                }
                $foo->Clean();
              
                return true;
              }
            } else {
               
              $foo->Clean();
              return $foo->error;
            }
            
        }
        
        public static function setImageFromUplad($type, $id,  $upload)
        {
           return PhotoUtils::processFile($type, $id, $upload);
        }
        
        public static function setImageFromBase64($type, $id, $base64)
        {
            $img = str_replace('data:image/png;base64,', '', $base64);
            $img = str_replace(' ', '+', $img);
            $fileData = base64_decode($img);
            $temp_file = SystemURLs::getImagesRoot() . "/temp.png";
            file_put_contents($temp_file , $fileData);
            return PhotoUtils::processFile($type, $id, $temp_file);
            unlink($temp_file);
            
        }
        
        public static function deletePhotos($type,$id)
        {
            $validExtensions = array("jpeg", "jpg", "png");
            $finalFileName = SystemURLs::getImagesRoot() . "/".$type . "/" . $id;
            $finalFileNameThumb =  SystemURLs::getImagesRoot() . "/" . $type . "/thumbnails/" . $id;

            $deleted = false;
            while (list(, $ext) = each($validExtensions)) {
              $tmpFile = $finalFileName . "." . $ext;
              if (file_exists($tmpFile)) {
                unlink($tmpFile);
                $deleted = true;
              }
              $tmpFile = $finalFileNameThumb . "." . $ext;
              if (file_exists($tmpFile)) {
                unlink($tmpFile);
                $deleted = true;
              }
            }

            return $deleted;
        }
    }
}
