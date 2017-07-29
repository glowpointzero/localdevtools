<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateLocalProjectCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'createlocalproject';
    const COMMAND_DESCRIPTION = 'Sets up a new project on your local machine.';
    
    const GIT_REPOSITORY_CLONE_TARGETS = ['projectRoot', 'documentRoot'];
    const COMPOSER_ACTIONS_AFTER_GIT_CLONE = ['none', 'install', 'update'];  
    const LOGS_DIRECTORY = 'logs';
    
    var $additionalVhostPlaceholders = [
        'documentRoot' => 'REPLACEMENT NOT SET!',
        'logsDirectory' => 'REPLACEMENT NOT SET!',
    ];
    
    /**
     * {@inheritdoc}
     */
    public function configure()
    {
        parent::configure();
        
        $this->addValidatableOption(
            'projectKey',
            'Project key',
            null,
            null,
            '/^[0-9a-z\-]{3,}$/'
        );
        
        $this->addValidatableOption(
            'additionalDomains',
            'Additional domains (space-separated)',
            null,
            null,
            'optional'
        );
        
        $this->addValidatableOption(
            'projectFilesRootDirectoryName',
            'Project files root directory name (will reside in the project root directory along with the "logs" directory, for example)',
            'web',
            null,
            '/^[0-9a-z\-]{3,}$/i'
        );
        $this->addValidatableOption(
            'documentRootDirectoryName',
            'Document root directory name',
            'public_html',
            null,
            '/^[0-9a-z\-]{3,}$/i'
        );
        $this->addValidatableOption(
            'gitRepository',
            'Git repo to clone initially',
            null,
            null,
            '/^.*$/'
        );
        $this->addValidatableOption(
            'gitRepositoryCloneTarget',
            'Git repository clone target',
            null,
            self::GIT_REPOSITORY_CLONE_TARGETS,
            true,
            'gitRepository'
        );
        $this->addValidatableOption(
            'composerActionAfterGitClone',
            'Composer action to take after git clone',
            null,
            self::COMPOSER_ACTIONS_AFTER_GIT_CLONE,
            true,
            'gitRepository'
        );
    }
    
    
    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->localConfiguration->load();
        $this->localConfiguration->validate();
        
        $this->validateAllOptions();
        $this->createDirectories();      
        if ($this->createAndWriteVhostConfiguration()) {
             $this->getApplication()->find(Server\RestartCommand::COMMAND_NAME)->run($input, $output);
        }
    }
    
    
    /**
     * Creates all directories needed for a web project
     * (document root, logs, etc.)
     */
    protected function createDirectories()
    {
        // Project root directory
        $projectRoot =
            $this->localConfiguration->get('projectsRootPath')
            . DIRECTORY_SEPARATOR
            . $this->inputInterface->getOption('projectKey');
        
        $this->io->write(sprintf(' Creating project root directory %s ... ', $projectRoot));
        if ($this->fileSystem->exists($projectRoot)) {
            $this->io->warning('This directory already exists.');
            $this->letUserDecideOnContinuing();
            $this->io->write(' ');
        }
        try {
            $this->fileSystem->mkdir($projectRoot);
            $this->io->write('<info>ok!</info>', true);
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());
            $this->letUserDecideOnContinuing();
        }       
        
        // Project files subdirectory (containing code most likely to be
        // versioned, excluding logs, etc.)
        $projectFilesRoot = 
                $projectRoot 
                . DIRECTORY_SEPARATOR 
                . $this->inputInterface->getOption('projectFilesRootDirectoryName');
        
        $this->io->write(sprintf(' Creating project files root directory %s ... ', $projectFilesRoot));
    
        if ($this->fileSystem->exists($projectFilesRoot)) {
            $this->io->write('<info>exists. Ok!</info>', true);
        } else {
            try {
                $this->fileSystem->mkdir($projectFilesRoot);
                $this->io->write('<info>ok!</info>', true);
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
        
        // "Logs" directory
        $logsDirectory = $projectRoot . DIRECTORY_SEPARATOR . self::LOGS_DIRECTORY;
        $this->additionalVhostPlaceholders['logsDirectory'] = $logsDirectory;
        
        $this->io->write(sprintf(' Creating logs directory %s ... ', $logsDirectory));

        if ($this->fileSystem->exists($logsDirectory)) {
            $this->io->text('<info>exists. Ok!</info>');
        } else {
            try {
                $this->fileSystem->mkdir($logsDirectory);
                $this->io->text('<info>ok!</info>');
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
        
        // Document root directory a.k.a. "public html"
        $documentRoot =
                $projectFilesRoot 
                . DIRECTORY_SEPARATOR 
                . $this->inputInterface->getOption('documentRootDirectoryName');
        $this->additionalVhostPlaceholders['documentRoot'] = $documentRoot;
        $this->io->write(sprintf(' Creating public html directory %s ... ', $documentRoot));

        if ($this->fileSystem->exists($documentRoot)) {
            $this->io->text('<info>exists. Ok!</info>');
        } else {
            try {
                $this->fileSystem->mkdir($documentRoot);
                $this->io->text('<info>ok!</info>');
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
    }
    
    
    /**
     * Lets user create/save a vhost configuration file off a template
     * 
     * @return boolean
     */
    protected function createAndWriteVhostConfiguration()
    {
        $templateRootPath = $this->localConfiguration->get('hostConfigurationTemplatesRootPath');
        
        if (!is_dir($templateRootPath . DIRECTORY_SEPARATOR . 'Server')) {
            $templateRootPath = LOCAL_DEV_TOOLS_ROOT . DIRECTORY_SEPARATOR . \GlowPointZero\LocalDevTools\LocalConfiguration::DEFAULT_TEMPLATE_ROOT_PATH;
        }
        
        $templateRootPath = trim($templateRootPath, ' '. DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Server';
        
        $templateContents = $this->chooseAndLoadTemplate($templateRootPath);
        if ($templateContents === false) {
            $this->io->warning('Skipping server configuration setup.');
            return false;
        }
        
        // Load server configuration template and replace placeholders
        foreach ($this->options as $optionName => $optionConfiguration) {
            $templateContents = str_replace(
                sprintf('((((%s))))', $optionName),
                $this->inputInterface->getOption($optionName),
                $templateContents
            );
        }
        foreach ($this->additionalVhostPlaceholders as $placeholderName => $placeholderValue) {
            $templateContents = str_replace(
                sprintf('((((%s))))', $placeholderName),
                $placeholderValue,
                $templateContents
            );
        }

        $vhostConfigurationPath = 
            $this->localConfiguration->get('hostConfigurationFilesRootPath')
            . DIRECTORY_SEPARATOR
            . $this->inputInterface->getOption('projectKey') . '.conf';
        
        $this->io->write(sprintf(' Writing vhost configuration to %s ...', $vhostConfigurationPath));
        if (file_exists($vhostConfigurationPath)) {
            $this->io->warning(sprintf('The vhost target file exists, configuration will be added to this file.'));
        }
        
        $written = false;
        try {
            $this->fileSystem->appendToFile($vhostConfigurationPath, $templateContents);
            $this->io->write(' <info>ok!</info>');
            $written = true;
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());
        }
        
        return $written;
    }
    
    
    /**
     * Lets user choose a specific template file
     * 
     * @param type $templatesRootPath
     * @return boolean
     */
    protected function chooseAndLoadTemplate($templatesRootPath)
    {
        
        $templateName = '';
        
        while (empty($templateName)) {
            $files = scandir($templateRootPath);
            $files = array_filter(
                $files,
                function ($fileName) {
                    if (substr($fileName, -5) === '.tmpl') {
                        return true;
                    } else {
                        return false;
                    }
                }
            );
            $files = array_values($files); // reset indexes, in case any files have been skipped
            if (count($files) === 0) {
                $this->io->error(sprintf('No templates found in %s.', $templateRootPath));
                $options = [
                    'Try again',
                    'Skip this (do it manually later)'
                ];
                $continuationChoice = $this->io->choice(
                    'How do you want to continue?',
                    $options
                );
                $continuationChoiceResult = array_search($continuationChoice, $options);
                if ($continuationChoiceResult === 1) {
                    return false;
                }
                
            } else {
                $templateName = $this->io->choice(sprintf('Choose a file from %s', $templateRootPath), $files);
            }            
        }
        
        $templateContents = file_get_contents($templateRootPath . DIRECTORY_SEPARATOR . $templateName);
        
        return $templateContents;
    }
}