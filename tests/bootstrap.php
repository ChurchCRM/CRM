<?php
function loader($class)
{
    $file = $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
    
}

require './churchinfo/service/PersonService.php';
spl_autoload_register('loader');