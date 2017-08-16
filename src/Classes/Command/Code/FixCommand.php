<?php
namespace Glowpointzero\LocalDevTools\Command\Code;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Glowpointzero\LocalDevTools\Command\AbstractCommand;

class FixCommand extends AbstractCommand
{
    const COMMAND_NAME = 'code:fix';
    const COMMAND_DESCRIPTION = 'Runs php-cs-fixer over the files you specify';
    
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configFile = $this->io->ask('php-cs-fixer configuration file (optional)?', '', function ($value) {
            return $value;
        });
        if (empty($configFile)) {
            $rules = $this->io->ask('Rules', '@PSR1,@PSR2', function ($value) {
                return $value;
            });
        } else {
            $rules = false;
        }
        $fileOrDirectory = $this->io->ask('File or directory', './');
        $dryRun = $this->io->confirm('Dry run?');
        $showDiff = $this->io->confirm('Show diff?');
        
        $phpFixerPath = realpath($GLOBALS['LOCAL_DEV_TOOLS_EXEC_PATH'] . '/../vendor/bin/php-cs-fixer');
        $commandLine = '"' . $phpFixerPath . '" fix';
        
        if ($fileOrDirectory) {
            $commandLine .= ' "' . $fileOrDirectory . '"';
        }
        if ($dryRun) {
            $commandLine .= ' --dry-run';
        }
        if ($showDiff) {
            $commandLine .= ' --diff';
        }
        if ($configFile) {
            $commandLine .= sprintf(' --config="%s"', $configFile);
        }
        if ($rules) {
            $commandLine .= sprintf(' --rules="%s"', $rules);
        }
        
        $this->io->say('Executing ' . PHP_EOL . $commandLine . PHP_EOL . PHP_EOL);

        $fixerProcess = new \Symfony\Component\Process\Process($commandLine);
        $fixerProcessReturnCode = $fixerProcess->run();
        if ($fixerProcessReturnCode > 0) {
            $this->io->error('There seemed to be some errors');
        } else {
            $this->io->success('This seemed to have turned out well');
        }
        
        // Always output both error output (i.e. non-existing file or syntax errors)
        // as well as regular output (actual feedback given back by the php-cs-fixer)
        print $fixerProcess->getErrorOutput();
        print $fixerProcess->getOutput();
    }
}
