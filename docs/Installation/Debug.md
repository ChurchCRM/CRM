# Debug 

Explore various methods for debugging the ChurchCRM application, including turning on error reporting and enabling app logs.

## Error reporting in PHP

Update the copy of [Include\Config.php](https://github.com/ChurchCRM/CRM/blob/master/src/Include/Config.php.example) file change line 56 

`error_reporting(E_ERROR);` to `error_reporting(E_ALL);`

also see a listing of all [PHP error reporting]( http://php.net/manual/en/errorfunc.constants.php) that can be use

## Enable Debug Logs

Update the copy of [Include\Config.php](https://github.com/ChurchCRM/CRM/blob/master/src/Include/Config.php.example) file change line 62/63
to uncomment, remove `//`. Also ensure you have access to the location of the logs.




