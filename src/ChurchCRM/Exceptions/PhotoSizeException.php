<?php

namespace ChurchCRM\Exceptions;

/**
 * Thrown when an uploaded photo exceeds the server's configured size limit.
 * Caught by API route handlers to return an HTTP 413 response.
 */
class PhotoSizeException extends \RuntimeException
{
}
