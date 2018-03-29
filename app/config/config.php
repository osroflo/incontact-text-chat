<?php

return new \Phalcon\Config(array(
    'database' => array(
        'adapter'     => 'Mysql',
        'host'        => 'localhost',
        'username'    => 'foo',
        'password'    => 'bar',
        'dbname'      => 'smschat',
        'charset'     => 'utf8',
    ),
    'application' => array(
        'controllersDir' => __DIR__ . '/../controllers/',
        'modelsDir'      => __DIR__ . '/../models/',
        'viewsDir'       => __DIR__ . '/../views/',
        'pluginsDir'     => __DIR__ . '/../plugins/',
        'libraryDir'     => __DIR__ . '/../library/tropo/',
        'cacheDir'       => __DIR__ . '/../cache/',
        'baseUri'        => '/',
    ),
    'ThreeScale' => array(
        'provider_key' => 'foo',
        'user_key' => 'bar'
    )
));
