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
            in_array($validation, [null, 'optional']) ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED,
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
     * 
     * @param InputInterface $input
     */
    protected function validateAllOptions(InputInterface $input)
    {       
       /** @var InputOption $option */
       foreach($this->options as $optionName => $optionDefinition) {
           $optionNeedsValidation = $this->optionNeedsValidation($optionName, $input);
           
           while ($optionNeedsValidation && !$this->optionIsValid($optionName, $input)) {
               
               if ($optionDefinition['validationRound'] > 1) {
                   $this->outputErrorForOption($optionName);
               }
               
               $this->letUserSetOption($optionName, $input);
               
               $optionDefinition['validationRound']++;
               $this->options[$optionName]['validationRound']++;
            }
        }
    }
    
    
    /**
     * Checks whether an option needs validation
     * 
     * @param string $optionName
     * @param InputInterface $input
     * @return boolean
     */
    protected function optionNeedsValidation($optionName, InputInterface $input)
    {
        $optionDefinition = $this->options[$optionName];
        $needsValidation = true;
        
        if ($optionDefinition['validation'] === false) {
            $needsValidation = false;
        }
        
        if ($optionDefinition['onlyValidateIfOptionSet'] !== null && $optionDefinition['onlyValidateIfOptionSet'] !== false) {
            $otherOptionName = $optionDefinition['onlyValidateIfOptionSet'];
            $otherOptionValue = $input->getOption($otherOptionName);
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
     * @param InputInterface $input
     * @return boolean
     */
    protected function optionIsValid($optionName, InputInterface $input)
    {
        $optionDefinition = $this->options[$optionName];
        $optionValue = $input->getOption($optionName);
        
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
            $this->io->error(sprintf('The option %s can\'t be empty!', $optionName));
        } else {
            $this->io->error(sprintf('The option %s doesn\'t match the pattern "%s"!', $optionName, $optionDefinition['validation']));
        }
    }
    
    
    protected function letUserSetOption($optionName, InputInterface $input)
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
        
        $input->setOption($optionName, $newValue);
    }
    
    
}