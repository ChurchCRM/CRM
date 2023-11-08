<?php

namespace ChurchCRM;

interface PhotoInterface
{
    public function getPhoto();

    public function deletePhoto();

    public function setImageFromBase64($base64);
}
