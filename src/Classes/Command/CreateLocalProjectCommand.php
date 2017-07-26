<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateLocalProjectCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'createlocalproject';
    const COMMAND_DESCRIPTION = 'Sets up a new project on your local machine.';
    
    const GIT_REPOSITORY_CLONE_TARGETS = ['projectRoot', 'publicHtml'];
    const COMPOSER_ACTIONS_AFTER_GIT_CLONE = ['none', 'install', 'update'];  
    const LOGS_DIRECTORY = 'logs';
    
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
            'publicHtmlDirectoryName',
            'Public HTML directory name',
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
        
    }
    

    protected function createDirectories()
    {
        $projectRoot =
            $this->localConfiguration->get('projectsRootPath')
            . DIRECTORY_SEPARATOR
            . $this->inputInterface->getOption('projectKey');
        
        $this->io->text(sprintf('Creating project root directory %s ... ', $projectRoot));
        if ($this->fileSystem->exists($projectRoot)) {
            $this->io->warning('This directory already exists.');
            $this->letUserDecideOnContinuing();
        }
        try {
            $this->fileSystem->mkdir($projectRoot);
            $this->io->text('  ok!');
        } catch (\Exception $exception) {
            $this->io->error($exception->getMessage());
            $this->letUserDecideOnContinuing();
        }        
        
        $projectFilesRoot = 
                $projectRoot 
                . DIRECTORY_SEPARATOR 
                . $this->inputInterface->getOption('projectFilesRootDirectoryName');
        $this->io->text(sprintf('Creating project files root directory %s ... ', $projectFilesRoot));

        if ($this->fileSystem->exists($projectFilesRoot)) {
            $this->io->text('  Exists. Ok.');
        } else {
            try {
                $this->fileSystem->mkdir($projectFilesRoot);
                $this->io->write('  ok!');
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
        
        
        $logsDirectory = $projectRoot . DIRECTORY_SEPARATOR . self::LOGS_DIRECTORY;
        $this->io->text(sprintf('Creating logs directory %s ... ', $logsDirectory));

        if ($this->fileSystem->exists($logsDirectory)) {
            $this->io->text('  Exists. Ok.');
        } else {
            try {
                $this->fileSystem->mkdir($logsDirectory);
                $this->io->text(' ok!');
            } catch (\Exception $exception) {
                $this->io->error($exception->getMessage());
                $this->letUserDecideOnContinuing();
            }
        }
    }
}