<?php

namespace ChurchCRM\Exceptions;

/**
 * Thrown when the server cannot write a processed image, for example an unwritable
 * image directory. Upload handlers answer it with HTTP 500, not 400.
 */
class ImageStorageException extends \RuntimeException
{
}
