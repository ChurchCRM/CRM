<?php

namespace ChurchCRM\util
{
  
  use ChurchCRM\dto\SystemURLs;
  use ChurchCRM\Note;
  
    class PhotoUtils
    {
        
        
        public static function setImageFromUplad($type, $id,  $upload)
        {
           return PhotoUtils::processFile($type, $id, $upload);
        }
        
       
        
        public static function deletePhotos($type,$id)
        {
            
        }
        
        public static function getUploadedPhoto($type,$id)
        {
         
        }
   }
}
