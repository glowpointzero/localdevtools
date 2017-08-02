<?php
namespace GlowPointZero\LocalDevTools\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;

use GlowPointZero\LocalDevTools\Command\AbstractCommand;

/**
 * Creates all needed directories, files, etc.
 * to get started with a new project. Provides
 * option to clone & composer install an existing
 * project directly.
 */
class CopyFromRemoteCommand extends AbstractCommand
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
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'remoteDatabaseName',
            'Remote database name',
            null,
            null,
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'remoteUserName',
            'Remote db user name',
            null,
            null,
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'remotePassword',
            'Password (remote user)',
            null,
            null,
            '/^.*$/'
        );
        $this->addValidatableOption(
            'localHost',
            'Local db host name or ip',
            '127.0.0.1',
            null,
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'localDatabaseName',
            'Local database name',
            null,
            null,
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'localUserName',
            'Local db user name',
            null,
            null,
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'localPassword',
            'Password (local user)',
            '',
            null,
            '/^.*$/'
        );
        
        
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->localConfiguration->load();
        $this->localConfiguration->validate();
        
        $this->validateAllOptions();
        
        if (!$this->remoteDbIsAccessible()) {
            return;
        }
        
    }
    
    
    protected function remoteDbIsAccessible()
    {
        $process = $this->processDbCommand('remote', 'SHOW TABLES');
        if ($process->getExitCode() !== 0) {
            $this->io->error($process->getErrorOutput());
            return false;
        } else {
            return true;
        }
    }
    
    /**
     * Runs a command on the local or remote db
     * 
     * @param type $db
     * @param type $dbCommand
     * @param type $wrapper
     * @return Process
     */
    protected function processDbCommand($db='local', $dbCommand, $wrapper = '')
    {
        $command = 'mysql --host=%s --database=%s --user=%s --password="%s" --execute="%s" %s';
        $connectionProcess = new Process(
            sprintf(
                $command,
                $this->inputInterface->getOption($db . 'Host'),
                $this->inputInterface->getOption($db . 'DatabaseName'),
                $this->inputInterface->getOption($db . 'UserName'),
                $this->inputInterface->getOption($db . 'Password'),
                $dbCommand,
                $wrapper
            )
        );
        $connectionProcess->run();
        return $connectionProcess;
    }
}