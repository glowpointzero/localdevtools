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
        $remoteDbDumpPath = $this->createDbDump('remote');
        $this->io->success($remoteDbDumpPath);
        
        if ($this->localDbExists()) {
            $localDbDumpPath = $this->createDbDump('local');
            $this->io->success($localDbDumpPath);
        }
        
        $this->io->writeln('Done');
    }
    
    
    /**
     * Checks whether the remote database is accessible
     * 
     * @return boolean
     */
    protected function remoteDbIsAccessible()
    {
        $process = $this->processDbCommand('remote', 'mysql', ['SHOW TABLES']);
        if ($process->getExitCode() !== 0) {
            $this->io->error($process->getErrorOutput());
            return false;
        } else {
            return true;
        }
    }
    
    protected function localDbExists()
    {
        $process = $this->processDbCommand('local', 'mysql', ['SHOW DATABASES']);

        if ($process->getExitCode() !== 0) {
            $errorMessage = $process->getErrorOutput();
            $this->io->error($process->getExitCodeText());
            return 0;
        } else {
            
            $databaseNamePattern = sprintf('/\n%s\s/', $this->inputInterface->getOption('localDatabaseName'));
            return preg_match($databaseNamePattern, $process->getOutput());
            
        }
    }
    
    
    /**
     * Runs a command on the local or remote db
     * 
     * @param string $db              'local' or 'remote'
     * @param string $commandType      Command to execute, either mysql or mysqldump
     * @param array  $commandArguments Special arguments to be used by the command
     * @return Process
     */
    protected function processDbCommand($db='local', $commandType, $commandArguments = [])
    {
        
        if (!in_array($commandType, ['mysql', 'mysqldump'])) {
            throw new \Exception(sprintf('Command type "%s" is not allowed.', $commandType));
        }
        
        $defaultsFileOption = $this->fileSystem->createTemporaryFile();
        $this->fileSystem->appendToFile(
            $defaultsFileOption,
            sprintf(
                '[client]%spassword="%s"',
                PHP_EOL,
                $this->inputInterface->getOption($db . 'Password')
            )
        );
        
        $databaseName = $this->inputInterface->getOption($db . 'DatabaseName');
        $databaseOption = sprintf(
            '--database="%s"',
            $databaseName
        );
        
        
        $commandLine = sprintf(
            '%s --defaults-file="%s" --host="%s" %s --user="%s" %s"%s"',
            $commandType,
            $defaultsFileOption,
            $this->inputInterface->getOption($db . 'Host'),
            (preg_match('/SHOW DATABASE/i', $commandArguments[0]) || $commandType === 'mysqldump') ? '' : $databaseOption,
            $this->inputInterface->getOption($db . 'UserName'),
            $commandType === 'mysqldump' ? '"' . $databaseName . '" > ' : '--execute=',
            $commandArguments[0]
        );
        $this->io->comment($commandLine);
        $connectionProcess = new Process($commandLine);
        $connectionProcess->run();
        return $connectionProcess;
    }
    
    
    /**
     * Dumps DB to a local file and returns path.
     * 
     * @var string $db 'local' or 'remote'
     * @return boolean|string
     */
    protected function createDbDump($db = 'local')
    {
        $dumpPath = 
            $this->fileSystem->getUserHome()
            . DIRECTORY_SEPARATOR
            . 'dumps';
        
        $this->fileSystem->mkdir($dumpPath);
        
        $dumpName =
            $this->inputInterface->getOption($db . 'DatabaseName')
            . '--' . $this->inputInterface->getOption($db . 'Host')
            . '--' . date('Y-m-d--H-i-s')
            . '.sql';
        
        $dumpAbsPath = $dumpPath . DIRECTORY_SEPARATOR . $dumpName;
                
        $process = $this->processDbCommand(
            $db,
            'mysqldump',
            [$dumpAbsPath]
        );
        
        if ($process->getExitCode() === 0) {
            return $dumpAbsPath;
        } else {
            $this->io->error($process->getErrorOutput());
            return false;
        }
        
    }
}