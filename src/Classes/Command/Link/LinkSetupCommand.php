<?php
namespace Glowpointzero\LocalDevTools\Command\Link;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Glowpointzero\LocalDevTools\Command\AbstractCommand;
use Glowpointzero\LocalDevTools\Command\SetupCommandInterface;

class LinkSetupCommand extends AbstractCommand implements SetupCommandInterface
{
    const COMMAND_NAME = 'link:setup';
    const COMMAND_DESCRIPTION = 'Sets up your local symlink shortcuts.';
    
    protected $symlinkShortcuts = [];
    
    
    public function getConfiguredValues()
    {
        return $this->listCurrentSymlinkShortcuts(false);
    }
    
    public function getConfigurationTitle()
    {
        return 'Pre-configured symlink shortcuts';
    }
    
    public function __construct($name = null)
    {
        parent::__construct($name);
        $this->symlinkShortcuts = $this->localConfiguration->get('symlinks', []);
    }
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io->say('Let\'s set up your symlink shortcuts!');
        
        $choices = ['list all', 'add', 'remove', 'done!'];
        $nextStep = array_search($this->io->choice(' What do you want to do? ', $choices), $choices);
        
        while ($nextStep !== 3) {
            if ($nextStep === 0) {
                $this->io->section('Current symlink shortcuts');
                $this->listCurrentSymlinkShortcuts();
            } elseif ($nextStep === 1) {
                $this->io->section('Add new');
                $this->addSymlinkShortcut();
            } elseif ($nextStep === 2) {
                $this->io->section('Remove');
                $this->removeSymlinkShortcut();
            }
            $this->localConfiguration->set('symlinks', $this->symlinkShortcuts);
            $this->localConfiguration->save();
            $nextStep = array_search($this->io->choice(' What to do next', $choices), $choices);
        }
        uasort($this->symlinkShortcuts, function ($firstValue, $secondValue) {
            $comparingArray = [$firstValue['identifier'], $secondValue['identifier']];
            asort($comparingArray);
            if ($comparingArray[0] === $firstValue['identifier']) {
                return -1;
            }
        });
        
        $this->setResultValue('resultingConfiguration', $this->symlinkShortcuts);
    }
    
    
    /**
     * Lists all currently registered symlink shortcuts
     *
     * @param boolean $directOutput Output content directly (return alternatively)
     * @return string|void
     */
    protected function listCurrentSymlinkShortcuts($directOutput = true)
    {
        $content = '';
        if (count($this->symlinkShortcuts) === 0) {
            $content = '(no entries yet)';
        } else {
            foreach ($this->symlinkShortcuts as $symlink) {
                $content .= sprintf(
                   'Identifier: %s ' . PHP_EOL . 'Source: %s ' . PHP_EOL . 'Target: %s',
                    $symlink['identifier'],
                    $symlink['source'],
                    $symlink['target']
                ) . PHP_EOL . PHP_EOL;
            }
        }
        
        if ($directOutput) {
            $this->io->say($content);
        } else {
            return $content;
        }
    }
    
    
    /**
     * Lets the user set up a new symlink shortcut
     *
     * @return void
     */
    protected function addSymlinkShortcut()
    {
        $identifier = $this->io->ask('Unique identifier', '');
        $sourcePath = $this->io->ask('Source path', '');
        $targetPath = $this->io->ask('Target path', '');
        $identifierHash = md5($identifier);

        if (array_key_exists($identifierHash, $this->symlinkShortcuts)) {
            $this->io->caution('A shortcut using this identifier exists!');
            $createSymlinkShortcut = $this->io->confirm('Override?');
        } else {
            $createSymlinkShortcut = true;
        }

        if ($createSymlinkShortcut) {
            $this->symlinkShortcuts[$identifierHash] = [
                'identifier' => $identifier,
                'source' => $sourcePath,
                'target' => $targetPath
            ];
            $this->io->success(sprintf('Added symlink shortcut "%s"', $identifier));
        }
    }
    
    
    /**
     * Lets the user remove a specific symlink shortcut
     *
     * @return void
     */
    protected function removeSymlinkShortcut()
    {
        $options = [];
        foreach ($this->symlinkShortcuts as $entry) {
            $options[] = $entry['identifier'];
        }
        $options[] = 'abort!';
        $choiceNumber = array_search($this->io->choice('Which entry do you want to remove?', $options), $options);
        $shortcutHash = array_keys($this->symlinkShortcuts)[$choiceNumber];
        $removedEntry = $this->symlinkShortcuts[$shortcutHash];
        if ($choiceNumber < count($options)-1) {
            unset($this->symlinkShortcuts[$shortcutHash]);
            $this->io->success(sprintf('Removed symlink shortcut "%s".', $removedEntry['identifier']));
        } else {
            $this->io->success('Aborted removing symlink shortcut.');
        }
    }
}
