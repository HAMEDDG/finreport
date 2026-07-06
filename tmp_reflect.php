<?php
require 'vendor/autoload.php';
$ref = new ReflectionClass('App\\Controller\\SecurityController');
echo $ref->getFileName(), PHP_EOL;
$methods = array_map(function ($m) { return $m->getName(); }, $ref->getMethods());
echo implode(PHP_EOL, $methods), PHP_EOL;
