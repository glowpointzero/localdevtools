<?php
namespace Glowpointzero\LocalDevTools\Command\Server;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Glowpointzero\LocalDevTools\Command\AbstractCommand;

class RestartCommand extends AbstractCommand
{
    const COMMAND_NAME = 'server:restart';
    const COMMAND_DESCRIPTION = 'Restarts your local server.';
    
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->say('Restarting server...');
        $commands = explode(';', $restartCommand);
        foreach ($commands as $command) {
            $process = new Process(trim($command));
            $process->run(function ($type, $bufferedOutput) {
                if (Process::ERR === $type) {
                   $this->io->say($bufferedOutput, \Glowpointzero\LocalDevTools\Console\Style\DevToolsStyle::SAY_STYLE_ERROR);
                } else {
                    $this->io->say($bufferedOutput, \Glowpointzero\LocalDevTools\Console\Style\DevToolsStyle::SAY_STYLE_OK);
                }
            });
        }
    }
}
