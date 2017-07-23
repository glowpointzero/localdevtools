<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Optional;
use \Symfony\Component\Console\Input\InputArgument;

class CreateLocalProjectCommand extends AbstractCommand
{
    
    const COMMAND_NAME = 'createlocalproject';
    const COMMAND_DESCRIPTION = 'Sets up a new project on your local machine.';
        
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $validator = Validation::createValidator();
        
        // Project key
        $projectKeyIsValid = false;
        while(!$projectKeyIsValid) {
            $projectKey = $this->io->ask('Project key (Letters, dashes and numbers only, will be the main domain name as well.)');
            $violations = $validator->validate(
                    $projectKey,
                    [new Regex(['pattern' => '/^[0-9a-z\-]{3,}$/i']) ]
                );
            if (count($violations) === 0) {
                $projectKeyIsValid = true;
            } else {
                $this->io->error($violations[0]->getMessage());
            }
        }
        
        // Additional domain names
        $additionalDomains = [];
        $addMoreDomains = true;
        while ($addMoreDomains) {
            
            $newDomain = $this->io->ask(
                'Add another domain (leave empty to continue)',
                null,
                function($newValue){
                    return $newValue;
                }
            );
            
            $newDomain = trim(strtolower($newDomain));
            if (empty($newDomain)) {
                $addMoreDomains = false;
            } else {
                $additionalDomains[] = $newDomain;
            }
        }
        
        // Project root directory
        $projectRootDirectoryIsValid = false;
        while(!$projectRootDirectoryIsValid) {
            $projectRootDirectoryName = $this->io->ask('Web project root directory name (*NOT* where your public files will be, usually)', 'web');
            $violations = $validator->validate(
                    $projectKey,
                    [new Regex(['pattern' => '/^[0-9a-z\-]{3,}$/i']) ]
                );
            if (count($violations) === 0) {
                $projectRootDirectoryIsValid = true;
            } else {
                $this->io->error($violations[0]->getMessage());
            }
        }
        
        // Public html directory
        $webrootDirectoryNameIsValid = false;
        while(!$webrootDirectoryNameIsValid) {
            $webrootDirectoryName = $this->io->ask('Webroot directory name', 'web');
            $violations = $validator->validate(
                    $projectKey,
                    [new Regex(['pattern' => '/^[0-9a-z\-]{3,}$/i']) ]
                );
            if (count($violations) === 0) {
                $webrootDirectoryNameIsValid = true;
            } else {
                $this->io->error($violations[0]->getMessage());
            }
        }
        
        
        // Git repository
        $gitRepository = $this->io->ask(
                sprintf('Git repo to clone into %s initially (optional)'),
                null,
                function($newValue){
                    return $newValue;
                }
            );
            
        if (!empty($gitRepository)) {
            $composerActionAfterCloning = $this->io->choice('Execute composer after cloning?', ['No!', 'Yes, "install"', 'Yes, "update"']);
        }
        
        
        
    }
}