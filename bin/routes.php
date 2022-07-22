#!/usr/bin/env php
<?php

require_once 'vendor/autoload.php';
$app = new \Lum\Router\CLI();
echo $app->run();
