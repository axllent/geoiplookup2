<?php
namespace App;

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application('geoiplookup2', '@build_version@');
$application->add(new GeoipLookup2());
$application->setDefaultCommand('geoiplookup2', true); // Single command application
$application->run();
