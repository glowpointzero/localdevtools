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
            'projectRootDirectoryName',
            'Project root directory name (the *PARENT* directory of your public_html directory)',
            'web',
            null,
            '/^[0-9a-z\-]{3,}$/'
        );
        $this->addValidatableOption(
            'publicHtmlDirectoryName',
            'Public HTML directory name',
            'web',
            null,
            '/^[0-9a-z\-]{3,}$/'
        );
        $this->addValidatableOption(
            'gitRepository',
            'Git repo to clone initially',
            null,
            null,
            true
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
        $this->validateAllOptions($input);
        $this->io->success('All set ... let\'s go!');
    }
}