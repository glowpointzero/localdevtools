<?php
namespace GlowPointZero\LocalDevTools\Command\Configuration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;
use GlowPointZero\LocalDevTools\Console\Style\DevToolsStyle;

class DiagnoseCommand extends AbstractCommand
{
    const COMMAND_NAME = 'configuration:diagnose';
    const COMMAND_DESCRIPTION = 'Diagnoses your local \'Local Dev Tools\' setup.';
    
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $localConfiguration = $this->localConfiguration->getAll();
        $localConfigurationTableValues = [];
        foreach ($localConfiguration as $configurationKey => $configurationValue) {
            $description = \GlowPointZero\LocalDevTools\LocalConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS[$configurationKey];
            $this->io->section($configurationKey . PHP_EOL . '(' . $description . ')');
            if (is_string($configurationValue)) {
                $this->io->say(sprintf('Current value: ' . PHP_EOL . '%s', $configurationValue));
            }
            $this->testConfigurationValue($configurationKey, $configurationValue);
            sleep(1);
        }
        
        $this->io->caution(
            sprintf(
                'Make sure you wildcard your vhosts directory! '. PHP_EOL
                    . 'You\'ll need to include your configured directory'
                    . ' "%s" in your vhosts config.'. PHP_EOL
                    . ' On apache you might want to add'. PHP_EOL . PHP_EOL
                    . '   %s '. PHP_EOL . PHP_EOL
                    . 'to your httpd-vhosts.conf!',
                $this->localConfiguration->get('hostConfigurationFilesRootPath'),
                'Include "'. $this->localConfiguration->get('hostConfigurationFilesRootPath') . DIRECTORY_SEPARATOR . '*.conf"'
            )
        );
    }
    
    
    /**
     * Tests given configuration value
     *
     * Tests given value for empty value and - if based on the
     * configuration key, it seems like it is a path, checks
     * the file path.
     *
     * @param string $configurationKey
     * @param string $configurationValue
     * @return void
     */
    protected function testConfigurationValue($configurationKey, $configurationValue)
    {
        if ($configurationKey === 'identifier') {
            $this->io->say(sprintf('"%s":', $configurationValue), null, false, false);
        }

        if (is_array($configurationValue)) {
            foreach ($configurationValue as $subConfigurationKey => $subConfigurationValue) {
                $this->testConfigurationValue($subConfigurationKey, $subConfigurationValue);
            }
            $this->io->newLine();
            return;
        } else {
            $this->io->processingStart(sprintf('Testing "%s"', $configurationKey));
        }

        if (empty($configurationValue)) {
            $this->io->say('is empty!', DevToolsStyle::SAY_STYLE_WARNING, false, false);
        } elseif (preg_match('/(source|target|path)$/i', $configurationKey) && !file_exists($configurationValue)) {
            $this->io->say(sprintf('file "%s" doesn\'t exist!', $configurationValue), DevToolsStyle::SAY_STYLE_ERROR, false, false);
        } else {
            $this->io->processingEnd('ok');
            $this->io->newLine();
        }
    }
}
