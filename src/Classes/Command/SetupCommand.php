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
        foreach ($this->localConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS as $configurationKey => $configurationDescription) {
            $currentConfigurationValue = $this->localConfiguration->get($configurationKey);
            $configureViaDedicatedCommand = class_exists($configurationDescription);

            if ($configureViaDedicatedCommand) {
                $this->io->section(sprintf('Configure "%s"', $configurationKey));
                $configurationCommand = $this->getApplication()->find($configurationDescription::COMMAND_NAME);

                if (!$this->io->confirm(sprintf('Run "%s" now (you can always do this later)?', $configurationDescription::COMMAND_NAME), false)) {
                    continue;
                }

                $configurationCommand->run($input, $output);
                $configurationValue = $configurationCommand->getResultValue('resultingConfiguration');
                $this->localConfiguration->set($configurationKey, $configurationValue);
                continue;
            }

            $this->io->section(sprintf('Configure "%s"%s', $configurationKey, PHP_EOL . $configurationDescription));
            $configurationSuggestions = $this->localConfiguration->getConfigurationSuggestions($configurationKey);
            $setCustomOption = 'Enter a custom value';
            $useCurrentOption = sprintf('Use current value ("%s")', $currentConfigurationValue);

            if (count($configurationSuggestions)) {
                $allConfigurationSuggestions = $configurationSuggestions;
                array_unshift($allConfigurationSuggestions, $setCustomOption);
                if ($currentConfigurationValue) {
                    array_unshift($allConfigurationSuggestions, $useCurrentOption);
                }
                $configurationValue = $this->io->choice('Suggestions for this configuration value', $allConfigurationSuggestions, $useCurrentOption);
                if ($configurationValue === $useCurrentOption) {
                    continue;
                }
            }

            if ($currentConfigurationValue) {
                $configurationValue = $this->io->choice(
                    'Your choice',
                    [
                        $useCurrentOption,
                        $setCustomOption
                    ],
                    $useCurrentOption
                );
                if ($configurationValue === $useCurrentOption) {
                    continue;
                }
            }

            $configurationValue = $this->io->ask($configurationDescription);
            $this->localConfiguration->set($configurationKey, $configurationValue);
        }
        
        // Save settings file
        $this->io->text(
            sprintf('Ok! Saving your settings into %s.', $this->localConfiguration->getConfigurationFilePathAbs())
        );
        $this->localConfiguration->save();
        $this->io->success('We\'re done here.');

        if ($this->io->confirm('Diagnose configuration?')) {
            $this->getApplication()->find(DiagnoseCommand::COMMAND_NAME)->run($input, $output);
        } else {
            $this->io->say(sprintf('Okay, you can always run "%s" to do so.', DiagnoseCommand::COMMAND_NAME));
        }

    }
}
