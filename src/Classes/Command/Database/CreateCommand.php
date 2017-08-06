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
class CreateCommand extends AbstractDatabaseCommand
{
    
    const COMMAND_NAME = 'db:create';
    const COMMAND_DESCRIPTION = 'Creates a new DB and a corresponding user';
    
    protected $useDefaultDbCommandOptions = false;
    
    
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        parent::configure();
        
        $this->addValidatableOption(
            'newDatabaseName',
            'Database name',
            null,
            null,
            '/^[0-9a-z\-\._]{3,}$/i'
        );
        $this->addValidatableOption(
            'newUserName',
            'Database user name (defaults back to the new database name)',
            null,
            null,
            '/^[0-9a-z\-\._]*$/i'
        );
        
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dbName = $this->inputInterface->getOption('newDatabaseName');
        $userName = $this->inputInterface->getOption('newUserName') ?: $dbName;
        
        $dbExists = $this->localDatabaseExists($dbName, $dbExistsError);
        if ($dbExistsError) {
            $this->io->error($dbExistsError);
            return 1;
        } elseif ($dbExists) {
            $this->io->text('Database exists already.');
        } else {
            $this->io->processing('Creating database');
            $dbCreated = $this->createDb($dbName, $dbCreatedErrors);
            if (!$dbCreatedErrors) {
                $this->io->ok();
            } else {
                $this->io->error($dbCreatedErrors);
                return 1;
            }
        }
        
        
        $userExists = $this->localDatabaseUserExists($userName, $userExistsError);
        if ($userExistsError) {
            $this->io->error($userExistsError);
            return 1;
        } elseif ($userExists) {
            $this->io->success('Database user exists already. Please take care of access rights yourself!');
        } else {
            $this->io->processing('Creating database user');
            $userCreated = $this->createUser($userName, $dbName, $userCreatedErrors);
            if (!$userCreatedErrors) {
                $this->io->ok();
                $this->io->success(sprintf('User/Password: %s / %s', $userName, $userCreated));
            } else {
                $this->io->error($userCreatedErrors);
                return 1;
            }
        }
        
    }
    
    /**
     * Creates a new, empty database
     * 
     * @param string $dbName
     * @param reference $error
     * @return boolean
     */
    private function createDb($dbName, &$error)
    {
        $process = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            null,
            sprintf('"CREATE DATABASE %s COLLATE utf8_general_ci"', $dbName)
        );

        if ($process->getExitCode() !== 0) {
            $error = $process->getErrorOutput();
            return false;
        } else {
            return true;
        }
    }
    
    
    /**
     * Creates a new DB user and grants all privileges to the given db
     * 
     * @param string $userName
     * @param string $dbName
     * @param string $error
     * @return boolean|string
     */
    private function createUser($userName, $dbName, &$error)
    {
        $randomPassword = \GlowPointZero\LocalDevTools\Utility::generateRandomString(6);
        $userAndHostCombo = sprintf('\'%s\'@\'%%\'', $userName); // 'username'@'%'
        
        $createUserProcess = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            $dbName,
            sprintf(
                '"CREATE USER %s IDENTIFIED BY \'%s\'"',
                $userAndHostCombo,
                $randomPassword
            )
        );
        
        if ($createUserProcess->getExitCode() !== 0) {
            $error = $createUserProcess->getErrorOutput();
            return false;
        }
        
        $grantPrivilegesProcess = $this->processDbCommand(
            $this->inputInterface->getOption('localHost'),
            $this->inputInterface->getOption('localRootUserName'),
            $this->inputInterface->getOption('localRootUserPassword'),
            $dbName,
            sprintf(
                '"GRANT ALL PRIVILEGES ON %s.* TO %s; FLUSH PRIVILEGES;"',
                $dbName,
                $userAndHostCombo
            )
        );
        
        if ($grantPrivilegesProcess->getExitCode() !== 0) {
            $error = $grantPrivilegesProcess->getErrorOutput();
            return false;
        } else {
            return $randomPassword;
        }
    }
    
    
    
    
}