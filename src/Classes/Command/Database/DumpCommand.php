<?php
namespace Glowpointzero\LocalDevTools\Command\Database;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Glowpointzero\LocalDevTools\Command\Database\AbstractDatabaseCommand;

/**
 * Creates database and corresponding user
 */
class DumpCommand extends AbstractDatabaseCommand
{
    const COMMAND_NAME = 'db:dump';
    const COMMAND_DESCRIPTION = 'Exports a database';
    
    protected $useDefaultDbCommandOptions = false;
    protected $useLocalDbRootUserCommandOptions = false;
    
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        parent::configure();
        
        $this->addValidatableOption(
            'host',
            'DB host name or ip',
            null,
            null,
            '/^[0-9a-z\-\.]{3,}$/i'
        );
        $this->addValidatableOption(
            'databaseName',
            'Database name',
            null,
            null,
            '/^[0-9a-z\-\._]{3,}$/i'
        );
        $this->addValidatableOption(
            'userName',
            'Database user name',
            null,
            null,
            '/^[0-9a-z\-\._]{3,}$/i'
        );
        $this->addValidatableOption(
            'password',
            'Password',
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
        $this->createDbDump(
            $this->inputInterface->getOption('host'),
            $this->inputInterface->getOption('userName'),
            $this->inputInterface->getOption('password'),
            $this->inputInterface->getOption('databaseName')
        );
    }
}
