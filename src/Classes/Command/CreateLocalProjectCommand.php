<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;


/**
 * Creates all needed directories, files, etc.
 * to get started with a new project. Provides
 * option to clone & composer install an existing
 * project directly.
 */
class CreateLocalProjectCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'createlocalproject';
    const COMMAND_DESCRIPTION = 'Sets up a new project on your local machine.';
    
    const GIT_REPOSITORY_CLONE_TARGETS = ['Project files root directory', 'Document root'];
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
            '/^.*$/'
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
            'gitRepositoryBranch',
            'Branch name to check out initially?',
            null,
            null,
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
        $this->createDirectories();

        if ($this->createVhostConfiguration()) {
             $this->getApplication()
                ->find(Server\RestartCommand::COMMAND_NAME)
                ->run(new ArrayInput([]), $output);
        }
        
        if ($this->inputInterface->getOption('gitRepository')) {
            $gitCloned = $this->cloneGit();
            if ($gitCloned) {
                $this->runComposerActions();
            }
        }
        
        $this->io->success('DONE creating local project!');
    }
    
    
    /**
     * Creates all directories needed for a web project
     * (document root, logs, etc.)
     */
    protected function createDirectories()
    {
        // Project root directory
        $projectRoot = $this->getProjectRootDirectory();
        
        $this->io->write(sprintf(' Creating project root directory %s ... ', $projectRoot));
        if ($this->fileSystem->exists($projectRoot)) {
            $this->io->warning('This directory already exists.');
            $this->letUserDecideOnContinuing();
            $this->io->write(' ');
        }
        try {
            $this->fileSystem->mkdir($projectRoot);
            $this->io->ok();
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());
            $this->letUserDecideOnContinuing();
        }       
        
        // Project files subdirectory (containing code most likely to be
        // versioned, excluding logs, etc.)
        $projectFilesRoot = $this->getProjectFilesRootDirectory();
        
        $this->io->write(sprintf(' Creating project files root directory %s ... ', $projectFilesRoot));
    
        if ($this->fileSystem->exists($projectFilesRoot)) {
            $this->io->ok('exists. Ok!');
        } else {
            try {
                $this->fileSystem->mkdir($projectFilesRoot);
                $this->io->ok();
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
            $this->io->ok('exists. Ok!');
        } else {
            try {
                $this->fileSystem->mkdir($logsDirectory);
                $this->io->ok();
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
        
        // Document root directory a.k.a. "public html"
        $documentRoot = $this->getDocumentRootDirectory();
        $this->additionalVhostPlaceholders['documentRoot'] = $documentRoot;
        $this->io->write(sprintf(' Creating public html directory %s ... ', $documentRoot));

        if ($this->fileSystem->exists($documentRoot)) {
            $this->io->ok('exists. Ok!');
        } else {
            try {
                $this->fileSystem->mkdir($documentRoot);
                $this->io->ok();
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
    }
    
    
    protected function getProjectRootDirectory()
    {
        return $this->localConfiguration->get('projectsRootPath')
            . DIRECTORY_SEPARATOR
            . $this->inputInterface->getOption('projectKey');
    }
    
    protected function getProjectFilesRootDirectory()
    {
        return $this->getProjectRootDirectory() 
            . DIRECTORY_SEPARATOR 
            . $this->inputInterface->getOption('projectFilesRootDirectoryName');
    }
    
    protected function getDocumentRootDirectory()
    {
        return $this->getProjectFilesRootDirectory() 
            . DIRECTORY_SEPARATOR 
            . $this->inputInterface->getOption('documentRootDirectoryName');
    }
    
    
    /**
     * Lets user create/save a vhost configuration file off a template
     * 
     * @return boolean
     */
    protected function createVhostConfiguration()
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
            $this->io->ok();
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
            $files = $this->fileSystem->getFilesInDirectory(
                $templatesRootPath,
                '/^.*\.tmpl$/',
                ['files']
            );
            
            if (count($files) === 0) {
                $this->io->error(sprintf('No templates found in %s.', $templatesRootPath));
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
                $templateName = $this->io->choice(sprintf('Choose a file from %s', $templatesRootPath), $files);
            }            
        }
        
        $templateContents = file_get_contents($templatesRootPath . DIRECTORY_SEPARATOR . $templateName);
        
        return $templateContents;
    }
    
    
    /**
     * Clones git repository set via option ('gitRepository') into
     * gitRepositoryCloneTarget (including branch).
     */
    protected function cloneGit()
    {
        $gitRepository = $this->inputInterface->getOption('gitRepository');
        $targetDirectory = array_search(
            $this->inputInterface->getOption('gitRepositoryCloneTarget'),
            self::GIT_REPOSITORY_CLONE_TARGETS
        );
        if ($targetDirectory === 0) {
            $targetDirectoryPath = $this->getProjectFilesRootDirectory();
        } else {
            $targetDirectoryPath = $this->getDocumentRootDirectory();
        }
        
        // Remove document root directory, if it's the only file existing in the
        // clone target, as we don't want to trigger a Git clone abort in this case.
        $filesInDestination = $this->fileSystem->getFilesInDirectory($targetDirectoryPath);
        if (count($filesInDestination) === 1) {
            $firstFilePath = $targetDirectoryPath . DIRECTORY_SEPARATOR . $filesInDestination[0];

            if (
                $firstFilePath === $this->getDocumentRootDirectory() 
                && count($this->fileSystem->getFilesInDirectory($firstFilePath)) === 0) {

                $this->fileSystem->remove($firstFilePath);
            }
        }
        
        $gitBranch = $this->inputInterface->getOption('gitRepositoryBranch');
        
        $this->io->write(sprintf(' Cloning %s ... ', $gitRepository));
        $cloneProcess = new Process(
            sprintf(
                'git clone%s "%s" "%s"',
                $gitBranch ? ' -b '. $gitBranch : '',
                $gitRepository,
                $targetDirectoryPath
            )
        );
        $cloneProcess->run();
        if ($cloneProcess->isSuccessful()) {
            $this->io->ok();
        } else {
            $this->io->error($cloneProcess->getErrorOutput());
        }
    }
    
    /**
     * Runs composer action according to option 'composerAction'
     * 
     * @return boolean
     */
    protected function runComposerActions()
    {
        $composerAction = array_search(
            $this->inputInterface->getOption('composerAction'),
            self::COMPOSER_ACTIONS_AFTER_GIT_CLONE
        );
        
        if ($composerAction === 0) {
            return true;
        }     
        $composerCommand = sprintf(
            'composer %s',
            $composerAction > 1 ? 'update' : 'install'
        );
        
        $this->io->write(sprintf(' Running %s ... ', $composerCommand));

        $composerProcess = new Process($composerCommand);
        $composerProcess->run();
        
        if ($composerProcess->isSuccessful()) {
            $this->io->ok();
            return true;
        } else {
            $this->io->error($composerProcess->getErrorOutput());
            return false;
        }
    }
}