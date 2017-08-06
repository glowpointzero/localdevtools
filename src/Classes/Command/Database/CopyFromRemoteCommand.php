<?php
namespace GlowPointZero\LocalDevTools\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use GlowPointZero\LocalDevTools\Command\Database\AbstractDatabaseCommand;

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
        
        $remoteDbIsAccessible = $this->remoteDbIsAccessible($remoteDbAccessErrors);
        if ($remoteDbIsAccessible !== true) {
            $this->io->error($remoteDbAccessErrors);
            return 1;
        }
        $remoteDbDumpPath = $this->createDbDump(
            $this->inputInterface->getOption('remoteHost'),
            $this->inputInterface->getOption('remoteUserName'),
            $this->inputInterface->getOption('remotePassword'),
            $this->inputInterface->getOption('remoteDatabaseName'),
            $dumpErrors
        );
        if ($remoteDbDumpPath === false) {
            $this->io->error($dumpErrors);
            return 1;
        } else {
            $this->io->text('Remote database dumped to '. $remoteDbDumpPath);
        }
        
        
        if (!$this->localDatabaseExists($this->inputInterface->getOption('localDatabaseName'))) {
            $createCommand = $this->getApplication()->find(CreateCommand::COMMAND_NAME);
            $createCommand->run(
                new ArrayInput([
                    '--newDatabaseName' => $this->inputInterface->getOption('localDatabaseName'),
                    '--newUserName' => $this->inputInterface->getOption('localUserName')
                ]),
                $output
            );
        }
        
        $this->importDumpToLocalDb($remoteDbDumpPath);
        
    }
    
    
    
    /**
     * Checks whether the remote database is accessible
     * 
     * @var reference $errors
     * @return boolean|string
     */
    protected function remoteDbIsAccessible(&$errors)
    {
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('remoteHost'),
            $this->inputInterface->getOption('remoteUserName'),
            $this->inputInterface->getOption('remotePassword'),
            null,
            '"SHOW TABLES FROM '. $this->inputInterface->getOption('remoteDatabaseName') . '"'
        );
        if ($process->getExitCode() !== 0) {
            $errors = $process->getErrorOutput();
            return false;
        } else {
            return true;
        }
    }
    
}