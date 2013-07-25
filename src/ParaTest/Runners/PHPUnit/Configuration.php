<?php namespace ParaTest\Runners\PHPUnit;

/**
 * Class Configuration
 *
 * Stores information about the phpunit xml
 * configuration being used to run tests
 *
 * @package ParaTest\Runners\PHPUnit
 */
class Configuration
{
    /**
     * Path to the configuration file
     *
     * @var string
     */
    protected $path;

    /**
     * @var \SimpleXMLElement
     */
    protected $xml;

    /**
     * A collection of datastructures
     * build from the <testsuite> nodes inside of a
     * PHPUnit configuration
     *
     * @var array
     */
    protected $suites = array();

    public function __construct($path)
    {
        $this->path = $path;
        if(file_exists($path))
            $this->xml = simplexml_load_file($path);
    }
    
    /**
     * Get the bootstrap PHPUnit configuration attribute
     * 
     * @return string The bootstrap attribute or empty string if not set
     */
    public function getBootstrap()
    {
        if($this->xml)
            return (string)$this->xml->attributes()->bootstrap;
        else
            return '';
    }

    /**
     * Returns the path to the phpunit configuration
     * file
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Return the contents of the <testsuite> nodes
     * contained in a PHPUnit configuration
     *
     * @return array|null
     */
    public function getSuites()
    {
        if(!$this->xml) return null;
        $suites = array();
        $nodes = $this->xml->xpath('//testsuite');
        while(list(, $node) = each($nodes))
            foreach ($node->directory as $dir)
                $suites[(string) $node['name']][] = $this->getSuitePath((string) $dir);

        return $suites;
    }

    /**
     * Return the path of the directory
     * that contains the phpunit configuration
     *
     * @return string
     */
    public function getConfigDir()
    {
        return dirname($this->path) . DIRECTORY_SEPARATOR;
    }

    /**
     * Returns a suite path relative to the config file
     *
     * @param $path
     * @return string
     * @throws \RuntimeException
     */
    public function getSuitePath($path)
    {
        $real = realpath($this->getConfigDir() . $path);
        if($real) return $real;
        throw new \RuntimeException("Suite path $path could not be found");
    }

    /**
     * Converting the configuration to a string
     * returns the configuration path
     *
     * @return string
     */
    public function __toString()
    {
        return $this->path;
    }
}
