<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GlowPointZero\LocalDevTools\LocalConfiguration;
use GlowPointZero\LocalDevTools\Command\Configuration\DiagnoseCommand;

class SetupCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'setup';
    const COMMAND_DESCRIPTION = 'Sets up your local dev environment tools.';    
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        
        // Load current configuration
        try {
            $localConfiguration = $this->localConfiguration->getAll();
        } catch (Exception $ex) {
        }
        
        // Iterate through settings and ask to set each one
        foreach ($localConfiguration as $configurationKey => $configurationValue) {
            $configurationValue = $this->io->ask(LocalConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS[$configurationKey], $configurationValue);
            $this->localConfiguration->set($configurationKey, $configurationValue);
        }
        
        // Save settings file
        $this->io->text(
            sprintf('Ok! Saving your settings into %s.', $this->localConfiguration->getConfigurationFilePathAbs())
        );
        $this->localConfiguration->save();
        
        // Symlink projects root
        if ($this->localConfiguration->get('projectsRootPath')) {
            $symlinkPath = $this->io->ask(
                sprintf(
                    'Would you like to create a symlink to your project roots path'
                        . '(%s) for easier access? If so, provide a path here',
                    $this->localConfiguration->get('projectsRootPath')
                ),
                \Symfony\Component\Console\Input\InputArgument::OPTIONAL
            );
            if ($symlinkPath) {
                $this->fileSystem->symlink($this->localConfiguration->get('projectsRootPath'), $symlinkPath);
            }
        }
        
        
        $this->io->confirm('We\'re done here. Run configuration diagnose (it won\'t hurt)?');
        
        // Run diagnose
        $this->getApplication()->find(DiagnoseCommand::COMMAND_NAME)->run($input, $output);
    }
}