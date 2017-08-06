<?php
namespace GlowPointZero\LocalDevTools;

use Symfony\Component\Filesystem\Filesystem;

class LocalConfiguration
{
    
    /**
     * @var Filesystem
     */
    var $fileSystem;
    
    
    const CONFIGURATION_DIRECTORY = '.localdevtools'; // ... under user home
    const CONFIGURATION_FILE_NAME = 'config';
    const DEFAULT_TEMPLATE_ROOT_PATH = 'Templates';
    
    var $isLoaded = false;
    var $configuration = [
        'hostConfigurationFilesRootPath' => '',
        'hostConfigurationTemplatesRootPath' => '',
        'projectsRootPath' => '',
        'hostsFilePath' => '',
        'hostsFileDomainPattern' => '((((projectKey)))).local www.((((projectKey)))).local ((((projectKey)))).lo www.((((projectKey)))).lo',
        'serverRestartCommand' => ''
    ];
    const CONFIGURATION_PARAMETERS_DESCRIPTIONS = [
        'hostConfigurationFilesRootPath' => 'Directory path of your virtual host *.conf files.',
        'hostConfigurationTemplatesRootPath' => 'Directory path of your template files.',
        'projectsRootPath' => 'Directory, where all your webprojects will be created.',
        'hostsFilePath' => 'Path to your hosts file.',
        'hostsFileDomainPattern' => 'Pattern used to extend your hosts file when creating new projects. Individual domains may be added during project setup.',
        'serverRestartCommand' => 'Command to restart your local webserver with',
    ];
    
    
    /**
     * @param Filesystem $fileSystem
     */
    public function __construct(Filesystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
        $this->configuration['hostConfigurationTemplatesRootPath'] = 
            LOCAL_DEV_TOOLS_ROOT 
            . DIRECTORY_SEPARATOR 
            . self::DEFAULT_TEMPLATE_ROOT_PATH;
    }
    
    
    /**
     * Sets an individual configuration parameter
     * 
     * @param string $parameter
     * @param mixed $value
     * @throws \Exception
     */
    public function set($parameter, $value)
    {
        if (!array_key_exists($parameter, $this->configuration)) {
            throw new \Exception(
                sprintf(
                    'Non-existent configuration parameter %s!',
                    $parameter
                ),
                1500808350
            );
        }
        $this->configuration[$parameter] = $value;
    }
    
    
    /**
     * Gets an individual configuration parameter
     * 
     * @param type $parameter
     * @return type
     * @throws \Exception
     */
    public function get($parameter)
    {
        if (!$this->isLoaded) {
            $this->load();
        }
        if (!array_key_exists($parameter, $this->configuration)) {
            throw new \Exception(
                sprintf(
                    'Non-existent configuration parameter %s!',
                    $parameter
                ),
                1500808421
            );
        }
        
        return $this->configuration[$parameter];
    }

    
    /**
     * Gets all configuration parameters and their values
     * 
     * @return array
     */
    public function getAll()
    {
        if (!$this->isLoaded) {
            $this->load();
        }
        return $this->configuration;
    }
    
    
    /**
     * Gets the absolute path to the configuration file
     * 
     * @return string
     */
    public function getConfigurationFilePathAbs()
    {
        $homeDirectory = $this->fileSystem->getUserHome();
        return $homeDirectory .'/'. self::CONFIGURATION_DIRECTORY .'/'. self::CONFIGURATION_FILE_NAME;
    }
    
    
    /**
     * Loads local dev tools configuration.
     * 
     * @throws \Exception
     */
    public function load()
    {
        if (!$this->fileSystem->exists($this->getConfigurationFilePathAbs())) {
            throw new \Exception(
                sprintf(
                    'Local Dev Tools configuration file (%s) doesn\'t exist! Run configure command first!',
                    $this->getConfigurationFilePathAbs()
                ),
                1500804159
            );
        }
        
        if ( ($configuration = file_get_contents($this->getConfigurationFilePathAbs())) ) {
            $jsonConfiguration = json_decode($configuration, true);
            
            foreach($this->configuration as $configKey => $configValue) {
                if (isset($jsonConfiguration[$configKey])) {
                    $this->configuration[$configKey] = $jsonConfiguration[$configKey];
                }
            }
            
        } else {
            throw new \Exception(
                sprintf(
                    'Couldn\'t load Local Dev Tools config file (%s)!',
                    $this->getConfigurationFilePathAbs()
                ),
                1500805200
            );
        }
        
        $this->isLoaded = true;
    }
    
    
    /**
     * Writes current configuration parameters to the local dev tools
     * configuration file.
     */
    public function save()
    {
        $this->fileSystem->dumpFile($this->getConfigurationFilePathAbs(), json_encode($this->configuration));
    }
    
    /**
     * @todo
     */
    public function validate()
    {
        
    }
}