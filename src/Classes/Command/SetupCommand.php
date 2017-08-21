<?php
namespace Glowpointzero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Glowpointzero\LocalDevTools\LocalConfiguration;
use Glowpointzero\LocalDevTools\Command\Configuration\DiagnoseCommand;
use Glowpointzero\LocalDevTools\Console\Style\DevToolsStyle;

class SetupCommand extends AbstractCommand
{
    const COMMAND_NAME = 'setup';
    const COMMAND_DESCRIPTION = 'Sets up your local dev environment tools.';
    
    public function configure()
    {
        parent::configure();
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Iterate through settings and ask to set each one
        foreach (LocalConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS as $configurationKey => $configurationDescription) {
            $configurationValue = $this->localConfiguration->get($configurationKey);
            
            if (class_exists($configurationDescription)) {
                $configurationCommand = $this->getApplication()->find($configurationDescription::COMMAND_NAME);
                $configurationCommand->run($input, $output);
                $configurationValue = $configurationCommand->getResultValue('resultingConfiguration');
            } else {
                $configurationValue = $this->io->ask(
                    $configurationDescription,
                    $configurationValue
                );
            }
            
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
                        . ' for easier access? If so, provide a path you\'d like to'
                        . ' access it from.',
                    $this->localConfiguration->get('projectsRootPath')
                ),
                null,
                function ($userInput) {
                    return $userInput;
                }
            );
            if (trim($symlinkPath)) {
                $this->fileSystem->symlink($this->localConfiguration->get('projectsRootPath'), $symlinkPath);
            }
        }
        
        $runDiagnose = $this->io->success('We\'re done here. Running diagnose in 3 seconds...');
        sleep(3);
        $this->getApplication()->find(DiagnoseCommand::COMMAND_NAME)->run($input, $output);
    }
}
