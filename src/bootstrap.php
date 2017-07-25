<?php
require realpath(__DIR__.'/../vendor/autoload.php');

use Symfony\Component\Console\Application;

$localDevTools = new Application();
$localDevTools->setName('Local Dev Tools');
$localDevTools->setVersion('beta-0.0.1');

$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\SetupCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Configuration\DiagnoseCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\CreateLocalProjectCommand());

$localDevTools->run();