<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

use GlowPointZero\LocalDevTools\LocalConfiguration;

abstract class AbstractCommand extends Command
{
    const COMMAND_NAME = '';
    const COMMAND_DESCRIPTION = '';
    
    
    /**
     * @var SymfonyStyle
     */
    var $io;
    
    
    /**
     * @var Filesystem
     */
    var $fileSystem;
    
    
    /**
     * @var LocalConfiguration
     */
    protected $localConfiguration;
        
    
    protected function configure()
    {
        $this->fileSystem = new Filesystem();
        $this->localConfiguration = new \GlowPointZero\LocalDevTools\LocalConfiguration($this->fileSystem);
        
        $this
            ->setName($this::COMMAND_NAME)
            ->setDescription($this::COMMAND_DESCRIPTION)
        ;
    }
    
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }
}