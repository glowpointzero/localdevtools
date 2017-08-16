<?php
namespace GlowPointZero\LocalDevTools\Command\Database;

use Symfony\Component\Process\Process;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;
use GlowPointZero\LocalDevTools\Utility\StringUtility;

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
        ) {
        
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
     * @throws Exception
     * @return string
     */
    protected function createDbDump(
            $host,
            $userName,
            $password,
            $databaseName
        ) {
        $dumpPath =
            $this->fileSystem->getUserHome()
            . DIRECTORY_SEPARATOR
            . 'dumps';
        
        $this->fileSystem->mkdir($dumpPath);
        
        $dumpName = sprintf('%s--%s--%s.sql', $databaseName, $host, date('Y-m-d--H-i-s'));
        
        $dumpAbsPath = $dumpPath . DIRECTORY_SEPARATOR . $dumpName;
        
        $this->io->processingStart(sprintf('Dumping the database %s (@%s) into "%s"', $databaseName, $host, $dumpAbsPath));

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
            throw new \Exception($process->getErrorOutput(), 1502037510);
        } elseif ((@filesize($dumpAbsPath) === 0)) {
            throw new \Exception('The dump file is empty. Something went wrong.', 1502037652);
        }
        
        $this->io->processingEnd('dumped.');
        
        return $dumpAbsPath;
    }
    
    /**
     * Checks, whether a specific local database exists
     *
     * @param string $databaseName
     * @throws Exception
     * @return boolean
     */
    protected function localDatabaseExists($dbName)
    {
        $this->io->processingStart(sprintf('Checking, whether the db "%s" exists on your system', $dbName));
        
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            null,
            '"SHOW DATABASES"'
        );
        
        if ($process->getExitCode() !== 0) {
            throw new \Exception($process->getErrorOutput(), 1502038887);
        }
        
        $databaseNamePattern = sprintf('/<field name="Database">%s<\/field>/i', $dbName);
        $dbExists = preg_match($databaseNamePattern, $process->getOutput());
        if ($dbExists) {
            $this->io->processingEnd('it does!');
        } else {
            $this->io->processingEnd('it doesn\'t!');
        }
        
        return $dbExists;
    }
    
    
    /**
     * Creates local database
     *
     *
     * @param string $dbName
     * @param string $userName
     * @param boolean $setLocalPasswordOnSuccess If true, the 'localPassword'
     *                                           will be reset to the automatically
     *                                           generated password
     * @throws \Exception
     */
    protected function createLocalDatabase($dbName)
    {
        $this->io->processingStart(sprintf('Creating database "%s"', $dbName));
        
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            null,
            sprintf('"CREATE DATABASE %s COLLATE utf8_general_ci"', $dbName)
        );

        if ($process->getExitCode() !== 0) {
            throw new \Exception($process->getErrorOutput(), 1502039452);
        }
        $this->io->processingEnd('created');
        return true;
    }
    
    
    /**
     * Creates a local database user and grants access to a specific database
     *
     * @param string $userName
     * @param string $databaseName
     * @return string
     * @throws \Exception
     */
    protected function createLocalDatabaseUser($userName, $databaseName)
    {
        $randomPassword = StringUtility::generateRandomString(6);
        $userAndHostCombo = sprintf('\'%s\'@\'%%\'', $userName); // 'username'@'%'
        
        $this->io->processingStart(sprintf('Creating user %s for db "%s"', $userName, $databaseName));
        
        $grantPrivilegesProcess = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            $databaseName,
            sprintf(
                '"CREATE USER %s IDENTIFIED BY \'%s\'"',
                $userAndHostCombo,
                $randomPassword
            )
        );
        if ($grantPrivilegesProcess->getExitCode() !== 0) {
            throw new \Exception($grantPrivilegesProcess->getErrorOutput(), 1502040558);
        }
        
        $this->io->processingEnd('ok');
        $this->io->success(sprintf('New user/password: %s / %s', $userName, $randomPassword));
        
        // Re-set current option values for 'localDatabaseName', user name and
        // password, if those options exist in the current call.
        if ($this->inputInterface->hasOption('localDatabaseName')
                && $this->inputInterface->hasOption('localUserName')) {
            if ($this->inputInterface->getOption('localDatabaseName') === $databaseName) {
                $this->inputInterface->setOption('localUserName', $userName);
                $this->io->text(sprintf('Using "%s" as new user for the rest of the request.', $userName));
            }
            if ($this->inputInterface->getOption('localUserName') === $userName
                && $this->inputInterface->getOption('localDatabaseName') === $databaseName) {
                $this->inputInterface->setOption('localPassword', $randomPassword);
                $this->io->text('Using new password for the rest of the request.');
            }
        }
        
        $this->io->processingStart(sprintf('Granting the user %s all privileges', $userName));
        $grantPrivilegesProcess = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            $databaseName,
            sprintf(
                '"GRANT ALL PRIVILEGES ON %s.* TO %s; FLUSH PRIVILEGES;"',
                $databaseName,
                $userAndHostCombo
            )
        );
        
        if ($grantPrivilegesProcess->getExitCode() !== 0) {
            throw new \Exception($grantPrivilegesProcess->getErrorOutput(), 1502040602);
        }
        
        $this->io->processingEnd('All privileges granted.');
        
        return $randomPassword;
    }
    
    
    /**
     * Will create both a local database and a corresponding user
     *
     * @param string $dbName
     * @param string $userName
     * @return mixed New password, if new user has been created, true otherwise
     */
    protected function createLocalDatabaseAndUser($dbName, $userName)
    {
        $dbExists = $this->localDatabaseExists($dbName);
        if (!$dbExists) {
            $this->createLocalDatabase($dbName);
        }
        
        $userExists = $this->localDatabaseUserExists($userName);
        if ($userExists) {
            $this->io->success('Database user exists already. Please take care of access rights yourself!');
            return true;
        } else {
            return $this->createLocalDatabaseUser($userName, $dbName);
        }
    }
    
    
    /**
     * Checks, whether a specific local database exists
     *
     * @param string $dbUserName
     * @throws Exception
     * @return boolean
     */
    protected function localDatabaseUserExists($dbUserName)
    {
        $this->io->processingStart(sprintf('Checking, whether the db user "%s" exists on your system', $dbUserName));

        $process = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            null,
            sprintf('"SELECT COUNT(*) as foundUsers FROM mysql.user WHERE user=\'%s\'"', $dbUserName)
        );
        
        if ($process->getExitCode() !== 0) {
            throw new \Exception($process->getErrorOutput(), 1502038955);
        }
        
        preg_match_all('/<field name="foundUsers">([0-9]+)<\/field>/i', $process->getOutput(), $matches);
        if (!isset($matches[1][0])) {
            throw new \Exception(
                'Could not parse query result to determine the existing number of users.',
                1502038983
            );
        }
        
        $userExists = $matches[1][0] > 0;
        if ($userExists) {
            $this->io->processingEnd('it does!');
        } else {
            $this->io->processingEnd('it doesn\'t!');
        }
        
        return $userExists;
    }
    
    
    /**
     * Imports a .sql file into a local database
     *
     * @param string $dumpFilePath Path to the dump file
     * @throws Exception
     * @return boolean
     */
    protected function importDumpToLocalDb($dumpFilePath)
    {
        $localDatabaseName = $this->inputInterface->getOption('localDatabaseName');
        $this->io->processingStart(
            sprintf(
                'Importing dump file %s into local database %s',
                $dumpFilePath,
                $localDatabaseName
            )
        );
        
        $importProcess = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localUserName'),
            $this->inputInterface->getOption('localPassword'),
            null,
            sprintf(
                'mysql "%s" < "%s"',
                $localDatabaseName,
                $dumpFilePath
            )
        );
        
        if ($importProcess->getExitCode() !== 0) {
            throw new \Exception($importProcess->getErrorOutput(), 1502037658);
        }
        
        $this->io->processingEnd('done.');
        return true;
    }
}
