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
            '/^[0-9a-z\-_]{3,}$/i'
        );
        $this->addValidatableOption(
            'documentRootDirectoryName',
            'Document root directory name',
            'public_html',
            null,
            '/^[0-9a-z\-_]{3,}$/i'
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
        $this->io->section('File system');
        $this->createDirectories();

        $this->io->section('Server');
        $this->createVhostConfiguration();
        $this->getApplication()
            ->find(Server\RestartCommand::COMMAND_NAME)
            ->run(new ArrayInput([]), $output);
        
        $this->extendHostsFile();
        
        $this->io->section('Database');
        $this->handleLocalDatabaseCreationAndImport($output);
        
        $this->io->section('Git & Composer');
        if ($this->inputInterface->getOption('gitRepository')) {
            $gitCloned = $this->cloneGit();
            if ($gitCloned) {
                try {
                    $this->runComposerActions();
                } catch (\Exception $exception) {
                    $this->io->error(
                        'Composer failed somehow. This script will now terminate.'
                        . ' Run the composer command again, once you looked into it.'
                    );
                }
                
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
        
        $this->io->processing(sprintf('Creating project root directory %s', $projectRoot));
        if ($this->fileSystem->exists($projectRoot)) {
            $this->io->warning('This directory already exists.');
            $this->letUserDecideOnContinuing();
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
        
        $this->io->processing(sprintf('Creating project files root directory %s', $projectFilesRoot));
    
        if ($this->fileSystem->exists($projectFilesRoot)) {
            $this->io->ok('already exists. Ok!');
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
        
        $this->io->processing(sprintf('Creating logs directory %s', $logsDirectory));

        if ($this->fileSystem->exists($logsDirectory)) {
            $this->io->ok('already exists. Ok!');
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
        $this->io->processing(sprintf('Creating public html directory %s', $documentRoot));

        if ($this->fileSystem->exists($documentRoot)) {
            $this->io->ok('already exists. Ok!');
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
     * @throws Exception
     * @return void
     */
    protected function createVhostConfiguration()
    {
        $templateRootPath = $this->localConfiguration->get('hostConfigurationTemplatesRootPath');
        
        if (!is_dir($templateRootPath . DIRECTORY_SEPARATOR . 'Server')) {
            $templateRootPath = LOCAL_DEV_TOOLS_ROOT . DIRECTORY_SEPARATOR . \GlowPointZero\LocalDevTools\LocalConfiguration::DEFAULT_TEMPLATE_ROOT_PATH;
        }
        
        $templateRootPath = trim($templateRootPath, ' '. DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Server';
        $templateContents = $this->chooseAndLoadTemplate($templateRootPath);
        
        $this->io->text(sprintf('Creating vhost configuration based on the template found under "%s"', $templateRootPath));
        
        if ($templateContents === false) {
            throw new \Exception(sprintf('Could not load contents from "%s".', $templateRootPath), 1502041243);
        }
        
        // Load server configuration template and replace placeholders
        foreach (array_keys($this->options) as $optionName) {
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
        
        if (file_exists($vhostConfigurationPath)) {
            $this->io->warning(sprintf('The vhost target file exists, configuration will be added to this file.'));
            $templateContents = PHP_EOL . PHP_EOL . $templateContents;
        }
        $this->io->processing(sprintf('Writing vhost configuration to "%s"', $vhostConfigurationPath));
        
        $this->fileSystem->appendToFile($vhostConfigurationPath, $templateContents);
        $this->io->ok();
    }
    
    
    /**
     * Extends 
     * @throws \Exception
     */
    protected function extendHostsFile()
    {
        $hostsFilePath = $this->localConfiguration->get('hostsFilePath');
        $this->io->processing(sprintf('Extending hosts file, appending new local domains (%s)', $hostsFilePath));

        if (!@is_file($hostsFilePath)) {
            throw new \Exception(sprintf('The hosts file doesn\'t exist under the given path ("%s")!', $hostsFilePath), 1502042022);
        }
        $projectKey = $this->inputInterface->getOption('projectKey');
        $newDomainsString = str_replace(
            '((((projectKey))))',
            $projectKey,
            PHP_EOL
                . sprintf('Inserted on %s by %s', date('Y-m-d H:m:s'), __FILE__)
                . PHP_EOL
                .'127.0.0.1     '
                . $this->localConfiguration->get('hostsFileDomainPattern')
                . ' '. $this->inputInterface->getOption('additionalDomains')
        );
        
        file_put_contents($hostsFilePath, $newDomainsString, FILE_APPEND);
        
        $this->io->ok();
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
     * Handles all the creating / copying of databases during
     * the 'execute()' process
     * 
     * @param OutputInterface $output
     */
    protected function handleLocalDatabaseCreationAndImport($output)
    {
        $doneCreatingLocalDatabase = false;
        $localDatabaseCreated = false;
        $createALocalDatabase = $this->io->confirm('Create a local database?');
        while (!$doneCreatingLocalDatabase && $createALocalDatabase) {
            
            $createLocalCommand = $this->getApplication()->find(Database\CreateCommand::COMMAND_NAME);
            
            try {
                $createLocalCommand->run(new ArrayInput([]), $output);
                $doneCreatingLocalDatabase = true;
                $localDatabaseCreated = true;
                
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $doneCreatingLocalDatabase = !$this->io->confirm('It seems like this didn\'t go as planned. Try again?');
            }
            
        }
        
        $localDatabaseOptions = [];
        if ($localDatabaseCreated) {
            $localDatabaseOptions['--localHost'] = $createLocalCommand->getResultValue('--localHost');
            $localDatabaseOptions['--localRootUserName'] = $createLocalCommand->getResultValue('--localRootUserName');
            $localDatabaseOptions['--localRootUserPassword'] = $createLocalCommand->getResultValue('--localRootUserPassword');
            $localDatabaseOptions['--localDatabaseName'] = $createLocalCommand->getResultValue('dbName');
            $localDatabaseOptions['--localUserName'] = $createLocalCommand->getResultValue('userName');
            $localDatabaseOptions['--localPassword'] = $createLocalCommand->getResultValue('password');
        }
        
        $doneImportingRemoteDatabase = false;
        $importRemoteDatabase = $this->io->confirm('Import a remote DB to the local database?');
        while (!$doneImportingRemoteDatabase && $importRemoteDatabase) {
            try {
                $copyFromRemoteCommand = $this->getApplication()->find(Database\CopyFromRemoteCommand::COMMAND_NAME);
                $copyFromRemoteCommand->run(new ArrayInput($localDatabaseOptions), $output);
                
                $doneImportingRemoteDatabase = true;
                
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $doneImportingRemoteDatabase = !$this->io->confirm('It seems like this didn\'t go as planned. Try again?');
            }
        }
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
        
        $this->io->processing(sprintf('Cloning %s', $gitRepository));
        $cloneProcess = new Process(
            sprintf(
                'git clone%s "%s" "%s"',
                $gitBranch ? ' -b '. $gitBranch : '',
                $gitRepository,
                $targetDirectoryPath
            )
        );
        $cloneProcess->run();
        $this->io->ok();
    }
    
    /**
     * Runs composer action according to option 'composerAction'
     * 
     * @return void
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
        
        $this->io->processing(sprintf('Running %s', $composerCommand));

        $composerProcess = new Process($composerCommand);
        $composerProcess->run();
        $this->io->ok();
    }
}