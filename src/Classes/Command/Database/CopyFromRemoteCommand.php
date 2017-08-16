<?php
namespace Glowpointzero\LocalDevTools\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Glowpointzero\LocalDevTools\Command\Database\AbstractDatabaseCommand;

/**
 * Copies a database from a remote server to a local one
 */
class CopyFromRemoteCommand extends AbstractDatabaseCommand
{
    const COMMAND_NAME = 'db:copyfromremote';
    const COMMAND_DESCRIPTION = 'Copies a database from a remote server your local machine.';
    
    
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        parent::configure();
        
        $this->addValidatableOption(
            'remoteHost',
            'Remote db host name or ip',
            null,
            null,
            '/^[0-9a-z\-\._]{3,}$/i'
        );
        $this->addValidatableOption(
            'remoteDatabaseName',
            'Remote database name',
            null,
            null,
            '/^[0-9a-z\-\._]{3,}$/i'
        );
        $this->addValidatableOption(
            'remoteUserName',
            'Remote db user name',
            null,
            null,
            '/^[0-9a-z\-\._]{3,}$/i'
        );
        $this->addValidatableOption(
            'remotePassword',
            'Password (remote user)',
            null,
            null,
            '/^.*$/'
        );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $localDbName = $this->inputInterface->getOption('localDatabaseName');
        $localUserName = $this->inputInterface->getOption('localUserName');
            
        try {
            $this->remoteDbIsAccessible();
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());
            return 1;
        }
        
        $remoteDbDumpPath = $this->createDbDump(
            $this->inputInterface->getOption('remoteHost'),
            $this->inputInterface->getOption('remoteUserName'),
            $this->inputInterface->getOption('remotePassword'),
            $this->inputInterface->getOption('remoteDatabaseName')
        );
                
        if (!$this->localDatabaseExists($localDbName)) {
            $this->io->text('Local database doesn\'t exist yet.');
            $this->createLocalDatabase($localDbName);
        }
        
        if (!$this->localDatabaseUserExists($localUserName)) {
            $this->createLocalDatabaseUser($localUserName, $localDbName);
        }
        
        $this->importDumpToLocalDb($remoteDbDumpPath);
    }
    
    
    
    /**
     * Checks whether the remote database is accessible
     *
     * @var reference $errors
     * @throws Exception
     * @return boolean
     */
    protected function remoteDbIsAccessible()
    {
        $remoteHost = $this->inputInterface->getOption('remoteHost');
        $remoteUserName = $this->inputInterface->getOption('remoteUserName');
        
        $this->io->processingStart(
            sprintf(
                'Checking remote db access (%s@%s)',
                $remoteUserName,
                $remoteHost
            )
        );
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('remoteHost'),
            $remoteUserName,
            $this->inputInterface->getOption('remotePassword'),
            null,
            '"SHOW TABLES FROM '. $this->inputInterface->getOption('remoteDatabaseName') . '"'
        );
        if ($process->getExitCode() !== 0) {
            throw new \Exception($process->getErrorOutput(), 1502039256);
        }
        
        $this->io->processingEnd('ok');
        return true;
    }
}
