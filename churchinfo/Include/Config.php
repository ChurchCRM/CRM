<?php
/*******************************************************************************
*
*  filename    : Include/Config.php
*  website     : http://www.churchdb.org
*  description : global configuration
*
******************************************************************************/

// Database connection constants
$sSERVERNAME = "localhost";
$sUSER = "churchinfo";
$sPASSWORD = "churchinfo";
$sDATABASE = "churchinfo";

// Root path of your ChurchInfo installation ( THIS MUST BE SET CORRECTLY! )
// For example, if you will be accessing from http://www.yourdomain.com/web/churchinfo
// then you would enter "/web/churchinfo" here.
// Another example, if you will be accessing from http://www.yourdomain.com
// then you would enter "" here ... an empty string for a top level installation.
// This path SHOULD NOT end with slash.
$sRootPath="/churchinfo";

// If you are using a non-standard port number you may need to include the 
// port number in the URL.  Default value is fine for most installations.
$sPort="";

// You can enforce https access by setting this true.
$bHTTPSOnly=FALSE;

// When using a shared SSL certificate provided by your webhost for https access
// you may need to add the shared SSL server name to the URL.  Default value is fine
// for most installations
$sSharedSSLServer="";

// When using a shared SSL certificate your webhost may also require you to use a 
// modified version of your hostname.  Default value is fine for most installations.
$sHTTP_Host=$_SERVER['HTTP_HOST'];

// Some webhosts implement shared SSL differently.  ChurchInfo currently
// works with the following implementation of shared SSL hosting.
//
// Let's say your "normal" http access looks like this:
// http://www.mydomain.org/churchinfo/Default.php
//
// Now let's say that access via your webhosts shared SSL certificate looks like this:
// https://ssl.secureaccess.net/ssl.mydomain.org/churchinfo/Default.php
//
// Here are the settings to implement the above example
// $sSharedSSLServer="ssl.secureaccess.net";
// $sHTTP_Host="ssl.mydomain.org";
//
// If your webhost implements shared SSL differently you may need to modify the
// source code to work with another implementation of shared SSL.  The only
// code that should need to be modified is the function RedirectURL() in
// the file Include/Functions.php.   
// Please post to the sourceforge help forum.  Tell us about your changes to
// get ChurchInfo to work with another implementation of shared SSL and we'll 
// try to add it to the next release.

//
// SETTINGS END HERE.  DO NOT MODIFY BELOW THIS LINE
//

$sIncludeDir=dirname(__FILE__);
$sInstallDir=dirname($sIncludeDir);
$sDocumentRoot=$sInstallDir;
if ( strlen($sRootPath) > 1 ) {
    // Count number of "/" in $sRootPath to find DocumentRoot
    $iCount = substr_count($sRootPath,'/');
    while ($iCount>0) {
        $iCount--;
        $sDocumentRoot=dirname($sDocumentRoot);
    }
}

// Absolute path must be specified for this require since this file is called 
// from scripts located in other directories
require ($sIncludeDir.DIRECTORY_SEPARATOR.'LoadConfigs.php');
?>
