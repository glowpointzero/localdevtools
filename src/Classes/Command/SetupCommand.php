<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GlowPointZero\LocalDevTools\LocalConfiguration;
use GlowPointZero\LocalDevTools\Command\Configuration\DiagnoseCommand;
use GlowPointZero\LocalDevTools\Console\Style\DevToolsStyle;

class SetupCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'setup';
    const COMMAND_DESCRIPTION = 'Sets up your local dev environment tools.';    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        
        $this->io->processingStart('Loading local configuration');
        // Load current configuration
        try {
            $localConfiguration = $this->localConfiguration->getAll();
            $this->io->processingEnd('ok');
        } catch (\Exception $ex) {
            $localConfiguration = [];
            $this->io->say('Doesn\'t exist yet!', DevToolsStyle::SAY_STYLE_WARNING, true, false);
        }
        
        // Iterate through settings and ask to set each one
        foreach (array_keys(LocalConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS) as $configurationKey) {
            $configurationValue = $this->localConfiguration->get($configurationKey);
            $configurationValue = $this->io->ask(
                    LocalConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS[$configurationKey],
                    $configurationValue
            );
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
                        . ' (%s)'
                        . 'for easier access? If so, provide a path here',
                    $this->localConfiguration->get('projectsRootPath')
                ),
                null,
                function($userInput) { return $userInput; }
            );
            if (trim($symlinkPath)) {
                $this->fileSystem->symlink($this->localConfiguration->get('projectsRootPath'), $symlinkPath);
            }
        }
        
        
        $runDiagnose = $this->io->confirm('We\'re done here. Run configuration diagnose (it won\'t hurt)?');
        if ($runDiagnose) {
            $this->getApplication()->find(DiagnoseCommand::COMMAND_NAME)->run($input, $output);
        }
    }
}