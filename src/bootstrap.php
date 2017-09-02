<?php
// Find autoload.php and derive vendor directory from it
$autoloaderViaComposer = realpath(__DIR__ . '/../../../autoload.php');
if (file_exists($autoloaderViaComposer)) {
    $vendorRootPath = realpath(__DIR__ . '/../../..');
} else {
    $vendorRootPath = realpath(__DIR__ . '/../vendor');
}
require_once $vendorRootPath . '/autoload.php';
define('VENDOR_ROOT', $vendorRootPath);
define('LOCAL_DEV_TOOLS_ROOT', __DIR__);

use Symfony\Component\Console\Application;

$localDevTools = new Application();
$localDevTools->setName('Local Dev Tools');
$localDevTools->setVersion('1.0.1');

$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\SetupCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Configuration\DiagnoseCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Code\FixCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Server\RestartCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Project\CreateCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Database\CreateCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Database\ImportCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Database\DumpCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Database\CopyFromRemoteCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Link\LinkCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\Link\LinkSetupCommand());
$localDevTools->add(new \Glowpointzero\LocalDevTools\Command\InfoCommand());

$localDevTools->setDefaultCommand(\Glowpointzero\LocalDevTools\Command\InfoCommand::COMMAND_NAME);
$localDevTools->run();
