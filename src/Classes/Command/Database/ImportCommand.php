<?php
namespace Glowpointzero\LocalDevTools\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;

use Glowpointzero\LocalDevTools\Command\Database\AbstractDatabaseCommand;

/**
 * Creates database and corresponding user
 */
class ImportCommand extends AbstractDatabaseCommand
{
    const COMMAND_NAME = 'db:import';
    const COMMAND_DESCRIPTION = 'Imports a db dump';
    
    
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        parent::configure();
        
        $this->addValidatableOption(
            'importFilePath',
            'Path to the file to import',
            null,
            '',
            '/^[0-9a-z\-\._]*$/i'
        );
        $this->addValidatableOption(
            'dumpCurrent',
            'Dump current database, if it exists',
            true,
            [true, false]
        );
        $this->addValidatableOption(
            'skipConfirmation',
            'Skip confirmation prompt before import',
            false,
            [false, true]
        );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dbName = $this->inputInterface->getOption('localDatabaseName');
        $importFilePath = $this->inputInterface->getOption('importFilePath');

        $dbExists = $this->localDatabaseExists($dbName);
        
        if ($dbExists && $this->inputInterface->getOption('dumpCurrent') !== false) {
            $this->io->section('Backing up existing database');
            $dbDumpPath = $this->createDbDump(
                $this->inputInterface->getOption('localHost'),
                $this->inputInterface->getOption('localUserName'),
                $this->inputInterface->getOption('localUserName'),
                $dbName
            );
        }
        
        if ($this->inputInterface->getOption('skipConfirmation') !== true) {
            $reallyImport = $this->io->confirm(
                sprintf(
                    'Really import file %s"%s"%s in to database "%s"?',
                    PHP_EOL,
                    $importFilePath,
                    PHP_EOL,
                    $dbName
                ),
                false
            );

            if (!$reallyImport) {
                return 0;
            }
        }
        
        $this->importDumpToLocalDb($importFilePath);
    }
}
