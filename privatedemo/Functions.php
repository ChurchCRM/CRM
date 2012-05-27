<?php
/*******************************************************************************
 *
 *  filename    : Functions.php
 *  description : Functions borrowed from the other churchinfo directory for
 *                private demo administration.
 *
 *  http://www.churchdb.org/
 *  Copyright 2011-2012 Michael Wilt
 *  
 *  LICENSE:
 *  (C) Free Software Foundation, Inc.
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *  General Public License for more details.
 *
 *  http://www.gnu.org/licenses
 *
 ******************************************************************************/
function RedirectURL($sRelativeURL)
// Convert a relative URL into an absolute URL and return absolute URL.
{
    global $sRootPath;
    global $sDocumentRoot;
    global $sSharedSSLServer;
    global $sHTTP_Host;
    global $bHTTPSOnly;
    global $sPort;

    // Check if port number needs to be included in URL
    if ($sPort)
        $sPortString = ":$sPort";
    else
        $sPortString = '';

    // http or https ?
    if ($_SESSION['bSecureServer'] || $bHTTPSOnly)
        $sRedirectURL = 'https://';    
    else
        $sRedirectURL = 'http://';

    // Using a shared SSL certificate?
    if (strlen($sSharedSSLServer) && $_SESSION['bSecureServer'])
        $sRedirectURL .= $sSharedSSLServer . $sPortString . '/' . $sHTTP_Host;
    else
        $sRedirectURL .= $sHTTP_Host . $sPortString;

    // If root path is already included don't add it again
    if (!$sRootPath) {
        // This check is not meaningful if installed in top level web directory
        $sRelativeURLPath = '/' . $sRelativeURL;
    } elseif (strpos($sRelativeURL, $sRootPath)===FALSE) {
        // sRootPath is not in sRelativeURL.  Add it
        $sRelativeURLPath = $sRootPath . '/' . $sRelativeURL;
        
    } else {
        // sRootPath already in sRelativeURL.  Don't add
        $sRelativeURLPath = $sRelativeURL;
    }

    // Test if file exists before redirecting.  May need to remove
    // query string first.
    $iQueryString = strpos($sRelativeURLPath,'?');
    if ($iQueryString) {
        $sPathExtension = substr($sRelativeURLPath,0,$iQueryString);
    } else {
        $sPathExtension = $sRelativeURLPath;
    }

    // $sRootPath gets in the way when verifying if the file exists
    $sPathExtension = substr($sPathExtension,strlen($sRootPath));
    $sFullPath = str_replace('\\','/',$sDocumentRoot.$sPathExtension);

    // With the query string removed we can test if file exists
    if (file_exists($sFullPath) && is_readable($sFullPath)) {
        $sRedirectURL .= $sRelativeURLPath;
    } else {
        $sErrorMessage = 'Fatal Error: Cannot access file: '.$sFullPath."<br>\n";
        $sErrorMessage .= "\$sPathExtension = $sPathExtension<br>\n";
        $sErrorMessage .= "\$sDocumentRoot = $sDocumentRoot<br>\n";

        die ($sErrorMessage);
    }

    return $sRedirectURL;
}

// Convert a relative URL into an absolute URL and redirect the browser there.
function Redirect($sRelativeURL)
{
    $sRedirectURL = RedirectURL($sRelativeURL);
    header("Location: " . $sRedirectURL);
    exit;
}
?>
