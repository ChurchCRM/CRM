<?php

return [
    'propel' => [
        'database' => [
            'connections' => [
                'default' => [
                    'adapter'  => 'mysql',
                    'dsn'      => 'mysql:host=localhost;port=3306;dbname=churchcrm',
                    'user'     => 'churchcrm',
                    'password' => 'churchcrm',
                    'settings' => [
                        'charset' => 'utf8',
                    ],
                ],
            ],
        ],
    ],
];
