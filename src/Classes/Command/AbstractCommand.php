<?php
namespace GlowPointZero\LocalDevTools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

use GlowPointZero\LocalDevTools\LocalConfiguration;

abstract class AbstractCommand extends Command
{
    const COMMAND_NAME = '';
    const COMMAND_DESCRIPTION = '';
    
    
    /**
     * @var SymfonyStyle
     */
    var $io;
    
    
    /** 
     * @var InputInterface 
     */
    var $inputInterface;
    
    
    /**
     * @var Filesystem
     */
    var $fileSystem;
    
    
    /**
     * @var LocalConfiguration
     */
    protected $localConfiguration;
        
    /**
     * A collection of options to revalidate
     * 
     * @var array
     */
    protected $options = [];
    
    
    
    protected function configure()
    {
        $this->fileSystem = new Filesystem();
        $this->localConfiguration = new \GlowPointZero\LocalDevTools\LocalConfiguration($this->fileSystem);
        
        $this
            ->setName($this::COMMAND_NAME)
            ->setDescription($this::COMMAND_DESCRIPTION)
        ;
    }
    
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->inputInterface = $input; // unfortunately 'input' is not available via SymfonyStyle...
        $this->io = new SymfonyStyle($input, $output);
        
    }
    
    /**
     * Adds a controller option that may later be validated and ask the user correction for
     * 
     * @param string $name
     * @param string $description
     * @param string $default
     * @param array $choices
     * @param mixed $validation
     * @param string $onlyValidateIfOptionSet
     */
    public function addValidatableOption($name, $description, $default = null, $choices = [], $validation = null, $onlyValidateIfOptionSet = null)
    {
        
        parent::addOption(
            $name,
            null,
            InputOption::VALUE_REQUIRED, // This indicates, whether the option may be set without value (i.e. boolean)
            $description,
            $default
        );
        
        $this->options[$name] = [
            'description' => $description,
            'default' => $default,
            'choices' => $choices,
            'validation' => $validation ? $validation : false,
            'onlyValidateIfOptionSet' => $onlyValidateIfOptionSet,
            'validationRound' => 1
        ];
    }
    
    /**
     * Goes through all options and validates each
     */
    protected function validateAllOptions()
    {       
       /** @var InputOption $option */
       foreach($this->options as $optionName => $optionDefinition) {
           $optionNeedsValidation = $this->optionNeedsValidation($optionName);
           
           while ($optionNeedsValidation && !$this->optionIsValid($optionName)) {
               
               if ($optionDefinition['validationRound'] > 1) {
                   $this->outputErrorForOption($optionName);
               }
               
               $this->letUserSetOption($optionName);
               
               $optionDefinition['validationRound']++;
               $this->options[$optionName]['validationRound']++;
            }
        }
    }
    
    
    /**
     * Checks whether an option needs validation
     * 
     * @param string $optionName
     * @return boolean
     */
    protected function optionNeedsValidation($optionName)
    {
        $optionDefinition = $this->options[$optionName];
        $needsValidation = true;
        
        if ($optionDefinition['validation'] === false) {
            $needsValidation = false;
        }
        if ($optionDefinition['onlyValidateIfOptionSet'] !== null && $optionDefinition['onlyValidateIfOptionSet'] !== false) {
            $otherOptionName = $optionDefinition['onlyValidateIfOptionSet'];
            $otherOptionValue = $this->inputInterface->getOption($otherOptionName);
            $otherOptionDefault = $this->options[$otherOptionName]['default'];
            if ($otherOptionValue === $otherOptionDefault || empty($otherOptionValue)) {
                $needsValidation = false;
            }
        }
        
        return $needsValidation;
    }
    
    
    /**
     * Validates one single option value
     * 
     * @param string $optionName
     * @return boolean
     */
    protected function optionIsValid($optionName)
    {
        $optionDefinition = $this->options[$optionName];
        $optionValue = $this->inputInterface->getOption($optionName);
        
        $isDefaultValue = ($optionValue === $optionDefinition['default']);
        $needsAnyValue = ($optionDefinition['validation'] === true);
        $isEmpty = empty($optionValue);
        $isOptional = ($optionDefinition['validation'] === 'optional');
        $isAtLeastSecondValidationRound = ($optionDefinition['validationRound'] > 1);
        
        if ($needsAnyValue && $isEmpty) {
            return false;
        } elseif ($isEmpty && $isOptional && $isAtLeastSecondValidationRound) {
            return true;
        } elseif($isEmpty && $isOptional && !$isAtLeastSecondValidationRound) {
            return false;
        } elseif($isDefaultValue && !$isAtLeastSecondValidationRound) {
            return false;
        } elseif($isDefaultValue && $isAtLeastSecondValidationRound) {
            return true;
        }
        
        if ($needsAnyValue && !$isEmpty) {
            return true;
        } elseif (preg_match($optionDefinition['validation'], $optionValue)) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * Outputs an error for a specific option.
     * 
     * @param type $optionName
     */
    protected function outputErrorForOption($optionName)
    {
        $optionDefinition = $this->options[$optionName];
        if (count($optionDefinition['choices']) > 0) {
            $this->io->error('Please choose one of the options below!');
        } elseif ($optionDefinition['validation'] === true) {
            $this->io->error(sprintf('The value for "%s" can\'t be empty!', $optionName));
        } else {
            $patternMismatchErrorMessage = 'The value for "%s" isn\'t valid. Please check it.';
            if ($optionDefinition['validationRound'] > 2) {
                $patternMismatchErrorMessage = 'Aw, not again! See, the value for "%s" must match the pattern "%s"!';
            }
            $this->io->error(sprintf($patternMismatchErrorMessage, $optionName, $optionDefinition['validation']));
        }
    }
    
    
    /**
     * Lets the user (re-)set a specific option as defined by
     * 'addValidatableOption'
     * 
     * @param string $optionName
     */
    protected function letUserSetOption($optionName)
    {
        $optionDefinition = $this->options[$optionName];
        if (count($optionDefinition['choices'])) {
            $newValue = $this->io->choice($optionDefinition['description'], $optionDefinition['choices']);
        } else {
            $newValue = $this->io->ask(
                $optionDefinition['description'],
                $optionDefinition['default'],
                function($inputValue) { return $inputValue; }
            );
        }
        
        $this->inputInterface->setOption($optionName, $newValue);
    }
    
    
    /**
     * Asks the user, whether he/she likes to continue in the
     * process and auto-quits if chosen 'no' (overridable).
     * 
     * @param string $reasonWhyProcessStopped
     * @param bool $abortIfUserDecidesToQuit
     * @return bool
     */
    protected function letUserDecideOnContinuing($reasonWhyProcessStopped = '', $abortIfUserDecidesToQuit = true)
    {
        $reasonWhyProcessStopped .= ' Continue anyway?';
        if ($abortIfUserDecidesToQuit) {
            $reasonWhyProcessStopped .= ' Choosing "no" will stop the whole process.';
        }
        $continue = $this->io->confirm(
            trim($reasonWhyProcessStopped),
            false
        );
        
        if (!$continue && $abortIfUserDecidesToQuit) {
            $this->io->warning('Aborting...');
            exit();
        }
        
        return $continue;
        
    }
    
}