<?php

$loader = new \Phalcon\Loader();

//Register some namespaces
$loader->registerNamespaces(
    array(
       "Incontact"    =>  __DIR__ . "/../library/incontact/",
       "ThreeScale"    =>  __DIR__ . "/../library/threescale/",
       "Incontact\Models"    =>  __DIR__ . "/../models/",
       "Tropo"    =>  "/opt/library/tropo/",
    )
);

$loader->registerDirs(array(
    __DIR__ . '/../models/'
));

// register autoloader
$loader->register();