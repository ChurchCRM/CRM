<?php

// Build-time only. Production loads the generated PHP distributed in src/Model.
return [
    'propel' => [
        'paths' => ['schemaDir' => __DIR__, 'phpDir' => __DIR__ . '/../src'],
        'generator' => ['namespaceAutoPackage' => false],
        'database' => ['connections' => ['default' => ['adapter' => 'mysql', 'dsn' => 'mysql:host=localhost;dbname=churchcrm', 'user' => 'churchcrm', 'password' => '']]],
    ],
];
