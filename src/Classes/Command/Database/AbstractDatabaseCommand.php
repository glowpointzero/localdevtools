<?php
namespace GlowPointZero\LocalDevTools\Command\Database;

use Symfony\Component\Process\Process;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;

/**
 * Creates database and corresponding user
 */
class AbstractDatabaseCommand extends AbstractCommand
{
    
    protected $useDefaultDbCommandOptions = true;
    protected $useLocalDbRootUserCommandOptions = true;
    
    public function configure()
    {
        parent::configure();
        
        if ($this->useDefaultDbCommandOptions || $this->useLocalDbRootUserCommandOptions) {
            $this->addValidatableOption(
                'localHost',
                'Local db host name or ip',
                '127.0.0.1',
                null,
                '/^[0-9a-z\-\.]{3,}$/i'
            );
        }
        
        if ($this->useLocalDbRootUserCommandOptions) {
            $this->addValidatableOption(
                'localRootUserName',
                'Database *ROOT* user name',
                'root',
                null,
                '/^[0-9a-z\-\._]{3,}$/i'
            );
            $this->addValidatableOption(
                'localRootUserPassword',
                'Database *ROOT* user password',
                '',
                null,
                '/^.*$/'
            );
        }
        if ($this->useDefaultDbCommandOptions) {
            $this->addValidatableOption(
                'localDatabaseName',
                'Local database name',
                null,
                null,
                '/^[0-9a-z\-\._]{3,}$/i'
            );
            $this->addValidatableOption(
                'localUserName',
                'Local database user name',
                null,
                null,
                '/^[0-9a-z\-\._]{3,}$/i'
            );
            $this->addValidatableOption(
                'localPassword',
                'Password (for the local database user)',
                '',
                null,
                '/^.*$/'
            );
        }
        
    }
    
    
    /**
     * Runs a mysql command via command line
     * 
     * @param string $host
     * @param string $userName
     * @param string $password
     * @param string $databaseName
     * @param  $command
     * @return Process
     * @throws \Exception
     */
    protected function processDbCommand(
            $host,
            $userName,
            $password,
            $databaseName = null,
            $command
        )
    {
        
        // Auto-prepend 'mysql' command, if given command starts out with quotes
        if (substr($command, 0, 1) === '"') {
            $command = 'mysql '. $command;
        }
        $commandParts = explode(' ', $command, 2);
        
        // Treat first argument part as command name and limit to 'mysql' & 'mysqldump'
        if (!in_array($commandParts[0], ['mysql', 'mysqldump'])) {
            throw new \Exception(sprintf('Command type "%s" is not allowed.', $commandParts[0]));
        }
        
        // If 'command' is wrapped into quotes, prepend --execute,
        // but *not* if there are more than 2 quotes in the whole command,
        // indicating that the command contains parts to be run outside of --execute.
        if (
            substr($commandParts[1], 0, 1) === '"'
            && substr($commandParts[1], -1) === '"'
            && substr_count($commandParts[1], '"') === 2) {
            $commandParts[1] = sprintf('--execute=%s', $commandParts[1]);
        }
        
        // Add 'database' option, if set
        if ($databaseName) {
            $commandParts[1] .= sprintf(' --database="%s"', $databaseName);
        }
        
        // Generate 'defaults' file to use for this command
        $defaultsFilePath = $this->fileSystem->createTemporaryFile();
        $this->fileSystem->appendToFile(
            $defaultsFilePath,
            sprintf(
                '[client]%spassword="%s"',
                PHP_EOL,
                $password
            )
        );
        
        $commandLine = sprintf(
            '%s --defaults-file="%s" --host="%s" --user="%s" %s %s',
            $commandParts[0],
            $defaultsFilePath,
            $host,
            $userName,
            $returnXml = $commandParts[0] == 'mysql' ? '--xml' : '',
            $commandParts[1]
        );
        $connectionProcess = new Process($commandLine);
        $connectionProcess->run();        
        
        return $connectionProcess;
    }
    
    
    /**
     * Dumps DB to a local file and returns path.
     * 
     * @param string $host
     * @param string $userName
     * @param string $password
     * @param string $databaseName
     * @param reference &$errors
     * @return boolean|string
     */
    protected function createDbDump(
            $host,
            $userName,
            $password,
            $databaseName,
            &$error
        )
    {
        $dumpPath = 
            $this->fileSystem->getUserHome()
            . DIRECTORY_SEPARATOR
            . 'dumps';
        
        $this->fileSystem->mkdir($dumpPath);
        
        $dumpName = sprintf('%s--%s--%s.sql', $databaseName, $host, date('Y-m-d--H-i-s'));
        
        $dumpAbsPath = $dumpPath . DIRECTORY_SEPARATOR . $dumpName;
         
        $process = $this->processDbCommand(
            $host,
            $userName,
            $password,
            null,
            sprintf(
                'mysqldump "%s" > "%s"',
                $databaseName,
                $dumpAbsPath
            )
        );
        
        if ($process->getExitCode() !== 0) {
            $error = $process->getErrorOutput();
            return false;
        } elseif( (@filesize($dumpAbsPath) === 0) ) {
            $error = 'The dump file seems to be empty - plausibility check failed.';
            return false;
        } else {
            return $dumpAbsPath;
        }
    }
    
    /**
     * Checks, whether a specific local database exists
     * 
     * @param string $databaseName
     * @param type $error
     * @return boolean
     */
    protected function localDatabaseExists(
            $dbName,
            &$error)
    {
        
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            null,
            '"SHOW DATABASES"'
        );
        
        if ($process->getExitCode() !== 0) {
            $error = $process->getErrorOutput();
            return false;
        } else {
            $error = false;
            $databaseNamePattern = sprintf('/<field name="Database">%s<\/field>/i', $dbName);
            return preg_match($databaseNamePattern, $process->getOutput());
        }
    }
    
    
    /**
     * Checks, whether a specific local database exists
     * 
     * @param string $dbUserName
     * @param string|false $error
     * @return boolean
     */
    protected function localDatabaseUserExists(
            $dbUserName,
            &$error)
    {
        
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            null,
            sprintf('"SELECT COUNT(*) as foundUsers FROM mysql.user WHERE user=\'%s\'"', $dbUserName)
        );
        
        if ($process->getExitCode() !== 0) {
            $error = $process->getErrorOutput();
            return false;
        } else {
            $error = false;
            preg_match_all('/<field name="foundUsers">([0-9]+)<\/field>/i', $process->getOutput(), $matches);
            if (!isset($matches[1][0])) {
                $error = 'Could not parse query result correctly to determine the existing number of users.';
                return false;
            } else {
                return $matches[1][0] > 0;
            }
        }
    }
    
    
    /**
     * Imports a .sql file into a local database
     * 
     * @param string $dumpFilePath Path to the dump file
     * @param type $error
     * @return boolean
     */
    protected function importDumpToLocalDb($dumpFilePath, &$error)
    {
        $importProcess = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localUserName'),
            $this->inputInterface->getOption('localPassword'),
            null,
            sprintf(
                'mysql "%s" < "%s"',
                $this->inputInterface->getOption('localDatabaseName'),
                $dumpFilePath
            )
        );
        
        if ($importProcess->getExitCode() !== 0) {
            $error = $importProcess->getErrorOutput();
            return false;
        } else {
            return true;
        }
    }
    
}