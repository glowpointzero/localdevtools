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
     * @var string
     */
    var $newPassword = null;
    
    
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
        
        $newPassword = $this->createLocalDatabaseAndUser($dbName, $userName);
        
        $this->setResultValue('dbName', $dbName);
        $this->setResultValue('userName', $userName);
        if (is_string($newPassword)) {
            $this->setResultValue('password', $newPassword);
        }
    }
    
    
}