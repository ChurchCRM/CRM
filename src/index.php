<?php
if (file_exists ( 'Include/Config.php')) {
  require_once 'Include/Config.php';
} else {
  header("Location: Setup.php" );
  exit();
}

function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
{
  $str = str_replace(' ', '', ucwords(str_replace('-', ' ', $string)));

  if (!$capitalizeFirstCharacter) {
    $str[0] = strtolower($str[0]);
  }

  return $str;
}

function endsWith($haystack, $needle) {
  // search forward starting from end minus needle length characters
  return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}

$hasSession = isset($_SESSION['iUserID']);
$redirectTo = ($hasSession) ? '/menu' : '/login';

// Get the current request path and convert it into a magic filename
// e.g. /list-events => /ListEvents.php
$shortName = str_replace($sRootPath . '/', '', $_SERVER['REQUEST_URI']);
$fileName = dashesToCamelCase($shortName, true) . '.php';

if (strtolower($shortName) == 'index.php' || strtolower($fileName) == 'index.php')
{
  // Index.php -> Menu.php or Login.php
  Header("Location: " . $sRootPath . $redirectTo);
  exit;
}
else if (!$hasSession)
{
  // Must show login form if no session
  require 'Login.php';
}
else if (file_exists($shortName))
{
  // Try actual path
  require $shortName;
}
else if (file_exists($fileName))
{
  // Try magic filename
  require $fileName;
}
else if ( strpos($_SERVER['REQUEST_URI'],"js") || strpos($_SERVER['REQUEST_URI'],"css") ) # if this is a CSS or JS file that we can't find, return 404
{
  header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
  exit;
}
else
{
  Header("Location: index.php");
  exit;
}

?>
