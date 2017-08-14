<?php
require realpath(__DIR__.'/../vendor/autoload.php');
define('LOCAL_DEV_TOOLS_ROOT', __DIR__);

use Symfony\Component\Console\Application;

$localDevTools = new Application();
$localDevTools->setName('Local Dev Tools');
$localDevTools->setVersion('beta-0.0.1');

$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\SetupCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Configuration\DiagnoseCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Server\RestartCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Project\CreateCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Database\CreateCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Database\ImportCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Database\DumpCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Database\CopyFromRemoteCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Link\LinkCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\Link\LinkSetupCommand());
$localDevTools->add(new \GlowPointZero\LocalDevTools\Command\InfoCommand());

$localDevTools->setDefaultCommand(\GlowPointZero\LocalDevTools\Command\InfoCommand::COMMAND_NAME);
$localDevTools->run();