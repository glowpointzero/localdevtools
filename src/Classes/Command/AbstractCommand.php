<?php
namespace Glowpointzero\LocalDevTools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

use Glowpointzero\LocalDevTools\LocalConfiguration;
use Glowpointzero\LocalDevTools\Component\Filesystem;
use Glowpointzero\LocalDevTools\Console\Style\DevToolsStyle;

abstract class AbstractCommand extends Command
{
    const COMMAND_NAME = '';
    const COMMAND_DESCRIPTION = '';
    
    
    /**
     * @var DevToolsStyle
     */
    protected $io;
    
    
    /**
     * @var InputInterface
     */
    protected $inputInterface;
    
    
    /**
     * @var Filesystem
     */
    protected $fileSystem;
    
    
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
    
    
    /**
     * May contain result values that need to be accessed
     * from outside of the command after it has been run.
     *
     * @var array
     */
    protected $resultValues = [];
    
    
    
    protected function configure()
    {
        $this->fileSystem = new \Glowpointzero\LocalDevTools\Console\Component\Filesystem();
        $this->localConfiguration = new \Glowpointzero\LocalDevTools\LocalConfiguration($this->fileSystem);
        
        $this
            ->setName($this::COMMAND_NAME)
            ->setDescription($this::COMMAND_DESCRIPTION)
        ;
        
        // @todo
        $this->addOption(
            'alwaysUseDefaults',
            '',
            \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            'If set, will skip asking for options that have a valid default value'
        );
    }
    
    
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->inputInterface = $input;
        $this->io = new DevToolsStyle($input, $output);
        
        $this->io->title('Running ' . $this->getName() . ' ...');
    }
    
    
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        parent::interact($input, $output);
        
        $this->localConfiguration->load();
        
        $this->validateAllOptions();
    }
    
    
    /**
     * Adds a controller option that may later be validated and ask the user correction for
     *
     * @param string $name                    Option identifier
     * @param string $description             Option description
     * @param string $default                 Default value (optional)
     * @param array $choices                  Option choices (optional)
     * @param mixed $validation               Validate input (true | regex pattern)
     * @param string $onlyValidateIfOptionSet Other option that must be set to trigger the validation of this one
     */
    public function addValidatableOption($name, $description, $default = null, $choices = [], $validation = null, $onlyValidateIfOptionSet = null)
    {
        if (count($choices) === 2 && in_array(true, $choices) && in_array(false, $choices)) {
            $isBoolean = true;
        } else {
            $isBoolean = false;
        }
        
        // Add original values to options stack
        $this->options[$name] = [
            'description' => $description,
            'default' => $default,
            'choices' => $choices,
            'validation' => $validation === false ? false : $validation,
            'onlyValidateIfOptionSet' => $onlyValidateIfOptionSet,
            'validationRound' => 1,
            'isBoolean' => $isBoolean
        ];
        
        // Add 'official' console option
        // REQUIRED means that an option *value* is required (option doesn't work if set, but left empty)
        $inputOptionMode = InputOption::VALUE_REQUIRED;
        if ($isBoolean) {
            $inputOptionMode = InputOption::VALUE_NONE;
            $default = null;
        }
        
        parent::addOption(
            $name,
            null,
            $inputOptionMode,
            $description,
            $default
        );
    }
    
    /**
     * Goes through all options and validates each
     */
    protected function validateAllOptions()
    {
        /** @var InputOption $option */
        foreach ($this->options as $optionName => $optionDefinition) {
            $optionNeedsValidation = $this->optionNeedsValidation($optionName);
           
            if ($this->optionIsValid($optionName)
                && $this->inputInterface->hasParameterOption('--'. $optionName)) {
                $optionNeedsValidation = false;
            }
           
            while ($optionNeedsValidation) {
                if ($this->options[$optionName]['validationRound'] > 1) {
                    $this->outputErrorForOption($optionName);
                }
               
                $this->letUserSetOption($optionName);

                $optionDefinition['validationRound']++;
                $this->options[$optionName]['validationRound']++;
                $optionNeedsValidation = !$this->optionIsValid($optionName);
            }
        }
        
        // Reset 'validationRounds'
        foreach ($this->options as $optionName => $optionDefinition) {
            $this->options[$optionName]['validationRound'] = 1;
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
        $optionValue = $this->inputInterface->getOption($optionName);
        $isDefaultValue = ($optionValue === $optionDefinition['default']);
        $isEmpty = empty($optionValue);
        
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
        
        $isAtLeastSecondValidationRound = ($optionDefinition['validationRound'] > 1);
        if (($isEmpty || $isDefaultValue) && $isAtLeastSecondValidationRound) {
            $needsValidation = false;
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
        $needsAnyValue = ($optionDefinition['validation'] === true);
        
        if (substr($optionDefinition['validation'], 0, 1) === '/') {
            $hasRegex = true;
            $regexMatches = preg_match($optionDefinition['validation'], $optionValue);
        } else {
            $hasRegex = false;
        }
        
        if ($optionDefinition['isBoolean'] && ($optionValue === true || $optionValue === false)) {
            return true;
        }
        if (!empty($optionValue) && $needsAnyValue) {
            return true;
        }
        if ($hasRegex && $regexMatches) {
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
        
        if ($optionDefinition['isBoolean']) {
            $defaultBooleanOption = ($optionDefinition['default'] === null) ? false : $optionDefinition['default'];
            $newValue = $this->io->confirm($optionDefinition['description'], $defaultBooleanOption);
        } elseif (count($optionDefinition['choices'])) {
            $newValue = $this->io->choice($optionDefinition['description'], $optionDefinition['choices']);
        } else {
            if (preg_match('/passw/i', $optionName)) {
                $newValue = $this->io->askHidden(
                    $optionDefinition['description'],
                    function ($inputValue) {
                        return trim($inputValue);
                    }
                );
            } else {
                $newValue = $this->io->ask(
                    $optionDefinition['description'],
                    $optionDefinition['default'],
                    function ($inputValue) {
                        return trim($inputValue);
                    }
                );
            }
        }
        if ($newValue === null) {
            $newValue = '';
        }
        
        $this->setResultValue('--' . $optionName, $newValue);
        $this->inputInterface->setOption($optionName, $newValue);
    }
    
    
    /**
     * Sets a new default option value for an option that has already
     * been initialized.
     *
     * @param string $optionName
     * @param mixed $newDefaultValue
     */
    public function updateDefaultOptionValue($optionName, $newDefaultValue)
    {
        $this->options[$optionName]['default'] = $newDefaultValue;
    }
    
    
    /**
     * Asks the user, whether he/she likes to continue in the
     * process and auto-quits if chosen 'no' (overrideable).
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
    
    /**
     * Gets a specific result/return value of this command
     *
     * @return mixed
     */
    public function getResultValue($key)
    {
        if (!isset($this->resultValues[$key])) {
            return null;
        } else {
            return $this->resultValues[$key];
        }
    }
    
    protected function setResultValue($key, $value)
    {
        $this->resultValues[$key] = $value;
    }
}
