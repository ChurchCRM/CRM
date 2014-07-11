<?php

// Database connection constants.
$sSERVERNAME = 'localhost';
$sUSER = 'privatedemo';
$sPASSWORD = 'privatedemo';
$sDATABASE = 'privatedemo';

$useTarFile = 'churchinfo-1.2.12.tar';

$sPhpMailSERVERNAME = 'localhost';
$sPhpMailUSER = 'phpmail';
$sPhpMailPASSWORD = 'phpmail';
$sPhpMailDATABASE = 'phpmail';

// Root path
$sRootPath='/privatedemo';
$sDocumentRoot = dirname(__FILE__);

// If you are using a non-standard port number you may need to include the 
// port number in the URL.  Default value is fine for most installations.
$sPort='';

// You can enforce https access by setting this true.
$bHTTPSOnly=FALSE;

// When using a shared SSL certificate provided by your webhost for https access
// you may need to add the shared SSL server name to the URL.  Default value is fine
// for most installations
$sSharedSSLServer='';

// When using a shared SSL certificate your webhost may also require you to use a 
// modified version of your hostname.  Default value is fine for most installations.
$sHTTP_Host=$_SERVER['HTTP_HOST'];
?>
