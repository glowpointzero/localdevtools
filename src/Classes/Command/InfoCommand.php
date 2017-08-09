<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;

class InfoCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'info';
    const COMMAND_DESCRIPTION = 'Display application info and help';    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $applicationNameAndVersion = $this->getApplication()->getName() .'   |   '. $this->getApplication()->getVersion();
        $this->io->block(
            [
                '',
                $applicationNameAndVersion,
                ''
            ],
            null,
            'fg=black;bg=cyan',
            '      ',
            true
        );
        $this->getApplication()->find('help')->run($input, $output);
    }
}