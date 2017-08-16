<?php
namespace GlowPointZero\LocalDevTools\Command\Link;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\ArrayInput;
use GlowPointZero\LocalDevTools\Command\AbstractCommand;

class LinkCommand extends AbstractCommand
{
    const COMMAND_NAME = 'link';
    const COMMAND_DESCRIPTION = 'Create a symlink quickly from your custom defined set';
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Check, whether there are symlinks set up at all
        while (count($this->localConfiguration->get('symlinks')) === 0) {
            $this->io->error('No symlinks set up yet');
            $setUpNow = $this->io->confirm('Set up now?');
            if ($setUpNow) {
                $this->getApplication()->find(LinkSetupCommand::COMMAND_NAME)->run(new ArrayInput([]), $output);
                $this->localConfiguration->load();
            } else {
                return;
            }
        }
        
        // Prepare all symlink shortcut and present as option
        $symlinks = $this->localConfiguration->get('symlinks');
        $options = [];
        foreach ($symlinks as $symlinkShortcut) {
            $options[] = $symlinkShortcut['identifier'];
        }
        $options[] = 'abort!';
        $chosenOption = array_search($this->io->choice('Which symlink do you want to create?', $options), $options);
        if ($chosenOption === count($options)-1) {
            return;
        }
        
        // Create symlink
        $linkConfiguration = array_values($symlinks)[$chosenOption];
        $this->io->processingStart(
            sprintf(
                'Linking "%s" to "%s"...',
                $linkConfiguration['source'],
                $linkConfiguration['target']
            )
        );
        // Abort, if the target doesn't exist
        if (!file_exists($linkConfiguration['target'])) {
            $this->io->error(sprintf('The target "%s" doesn\'t exist!', $linkConfiguration['target']));
            return 1;
        }
        // Check, whether source file already exists and if so
        // let the user decide how to continue.
        $sourceFileExists = file_exists($linkConfiguration['source']);
        if ($sourceFileExists && !is_link($linkConfiguration['source'])) {
            $this->io->warning(sprintf('The source path "%s" exists (and is not a symlink).', $linkConfiguration['source']));
            $override = $this->io->confirm('Backup existing file and create symlink?');
            if (!$override) {
                return;
            } else {
                $backupFileName = $linkConfiguration['source'] . '.backup-' . date('Y-m-d_H-i-s');
                $this->io->processingStart(sprintf('Backing up existing file to "%s"', $backupFileName));
                try {
                    $this->fileSystem->rename($linkConfiguration['source'], $backupFileName);
                    $this->io->processingEnd('ok');
                } catch (\Exception $exception) {
                    $this->io->newLine();
                    $this->io->error($exception->getMessage());
                    return 1;
                }
            }
            $this->io->processingStart('Creating symlink');
        }
        
        // Delete source file
        if (is_dir($linkConfiguration['source'])) {
            rmdir($linkConfiguration['source']);
        } else {
            unlink($linkConfiguration['source']);
        }
        
        // Create symlink (finally!)
        if (@symlink($linkConfiguration['target'], $linkConfiguration['source'])) {
            $this->io->processingEnd('ok');
        } else {
            $this->io->newLine();
            $this->io->error('Could not create symlink. Error when calling \'symlink\' method  :(');
        }
        
        $this->io->newLine();
    }
}
