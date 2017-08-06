<?php
namespace GlowPointZero\LocalDevTools\Command\Server;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;

class RestartCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'server:restart';
    const COMMAND_DESCRIPTION = 'Restarts your local server.';
    
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->processing('Restarting server');
        $restartCommand = $this->localConfiguration->get('serverRestartCommand');
        $process = new Process($restartCommand);
        $process->run();
        if ($process->getExitCode() !== 0) {
            throw new \Exception($process->getErrorOutput(), 1502040768);
        }
        $this->io->ok();
    }
}