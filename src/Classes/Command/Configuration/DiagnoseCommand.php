<?php
namespace GlowPointZero\LocalDevTools\Command\Configuration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;

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
        foreach($localConfiguration as $localConfigurationKey => $localConfigurationValue) {
            $localConfigurationTableValues[] = [
                $localConfigurationKey,
                \GlowPointZero\LocalDevTools\LocalConfiguration::CONFIGURATION_PARAMETERS_DESCRIPTIONS[$localConfigurationKey],
                $localConfigurationValue
            ];
        }
        $this->io->table(
            ['Parameter', 'Description', 'Current local value'],
            $localConfigurationTableValues
        );
        
        $this->io->note(
            sprintf('Make sure you wildcard your vhosts directory! '. PHP_EOL
                    . 'You\'ll need to include your configured directory'
                    . ' "%s" in your vhosts config.'. PHP_EOL
                    . ' On apache you might want to add'. PHP_EOL . PHP_EOL
                    . '   %s '. PHP_EOL . PHP_EOL
                    . 'to your httpd-vhosts.conf!',
                $this->localConfiguration->get('hostsFilePath'),
                'Include "'. $this->localConfiguration->get('hostsFilePath') . DIRECTORY_SEPARATOR . '*.conf"'
            )
        );
        
        // TODO: Do some real checks here!
    }
}