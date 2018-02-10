<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemURLs;

class RedirectUtils {
  
  public static function Redirect($sRelativeURL) {
    // Convert a relative URL into an absolute URL and redirect the browser there.
    header('Location: ' . SystemURLs::getRootPath() .'/'. $sRelativeURL);
    exit;
  }
}
