<?php
namespace ChurchCRM;

interface iPhoto
{
    public function getPhotoBytes();
    public function getThumbnailBytes();
    public function getThumbnailURI();
    public function getPhotoURI();
    public function deletePhoto();
    public function setImageFromBase64($base64);
    public function isPhotoLocal();
    public function isPhotoRemote();
    public function getPhotoContentType();
}
