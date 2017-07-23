<?php
require realpath(__DIR__.'/../vendor/autoload.php');

use Symfony\Component\Console\Application;

$localDevTools = new Application();

$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\SetupCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Configuration\DiagnoseCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\CreateLocalProjectCommand());

$output = new \GlowPointZero\LocalDevTools\ConsoleOutput();
$localDevTools->run(null, $output);