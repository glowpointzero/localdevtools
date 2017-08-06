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
     * @var boolean
     */
    var $hasCreatedANewUser = false;
    
    /**
     * @var string 
     */
    var $newPassword = '';
    
    protected function setNewPassword($password)
    {
        $this->newPassword = $password;
    }
    public function getNewPassword()
    {
        return $this->newPassword;
    }
    protected function setHasCreatedANewUser($hasCreatedANewUser)
    {
        $this->hasCreatedANewUser = (bool) $hasCreatedANewUser;
    }
    public function hasCreatedANewUser() {
        return $this->hasCreatedANewUser;
    }
    
    
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
        
        $dbExists = $this->localDatabaseExists($dbName);
        if (!$dbExists) {
            $this->createDb($dbName);
        }
        
        $userExists = $this->localDatabaseUserExists($userName);
        if ($userExists) {
            $this->io->success('Database user exists already. Please take care of access rights yourself!');
        } else {
            $userPassword = $this->createUser($userName, $dbName);
            $this->io->ok();
            $this->io->success(sprintf('User/Password: %s / %s', $userName, $userPassword));
        }
    }
    
    /**
     * Creates a new, empty database
     * 
     * @param string $dbName
     * @throws Exception
     * @return boolean
     */
    private function createDb($dbName)
    {
        $this->io->processing(sprintf('Creating database "%s"', $dbName));
        
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
        
        $this->io->ok();
        return true;
    }
    
    
    /**
     * Creates a new DB user and grants all privileges to the given db
     * 
     * @param string $userName
     * @param string $dbName
     * @throws Exception
     * @return boolean|string
     */
    private function createUser($userName, $dbName)
    {
        $randomPassword = \GlowPointZero\LocalDevTools\Utility::generateRandomString(6);
        $this->setNewPassword($randomPassword);
        $userAndHostCombo = sprintf('\'%s\'@\'%%\'', $userName); // 'username'@'%'
        
        $this->io->processing(sprintf('Creating user %s for db "%s"', $userName, $dbName));
        
        $grantPrivilegesProcess = $this->processDbCommand(
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
        if ($grantPrivilegesProcess->getExitCode() !== 0) {
            throw new \Exception($grantPrivilegesProcess->getErrorOutput(), 1502040558);
        }
        $this->setHasCreatedANewUser(true);
        $this->io->ok();
        
        $this->io->processing(sprintf('Granting the user %s all privileges', $userName));
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
        $this->io->ok();
        
        if ($grantPrivilegesProcess->getExitCode() !== 0) {
            throw new \Exception($grantPrivilegesProcess->getErrorOutput(), 1502040602);
        }
        
        return $randomPassword;
    }
    
    
    
    
}