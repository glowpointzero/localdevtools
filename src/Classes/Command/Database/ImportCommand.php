<?php
namespace GlowPointZero\LocalDevTools\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;

use GlowPointZero\LocalDevTools\Command\Database\AbstractDatabaseCommand;

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

        
        $dbExists = $this->localDatabaseExists($dbName, $dbExistsErrors);
        if ($dbExistsErrors) {
            $this->io->error('Unable to check, whether local database exists. No dump created. Import aborted.');
            return 1;
        }
        
        if ($dbExists && $this->inputInterface->getOption('dumpCurrent') !== false) {
            
            $dbDumpPath = $this->createDbDump(
                $this->inputInterface->getOption('localHost'),
                $this->inputInterface->getOption('localUserName'),
                $this->inputInterface->getOption('localUserName'),
                $this->inputInterface->getOption('localPassword'),
                $dumpErrors
            );
            if ($dbDumpPath === false) {
                $this->io->error($dumpErrors);
                return 1;
            } else {
                $this->io->note('Local database backup dumped to '. $dbDumpPath);
            }
        }
        
        if ($this->inputInterface->getOption('skipConfirmation') !== true) {
            $reallyImport = $this->io->confirm(
                sprintf(
                    'Really import file %s"%s"%s in to database "%s"?',
                    PHP_EOL, $importFilePath, PHP_EOL, $dbName
                ),
                false
            );

            if (!$reallyImport) {
                return 0;
            }
        }
        
        if (!$this->importDumpToLocalDb($dumpFilePath, $importError)) {
            $this->io->error($importError);
            return 1;
        } else {
            return 0;
        }
        
    }
    
}