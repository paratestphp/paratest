<?php namespace ParaTest\Runners\PHPUnit;

use Symfony\Component\Finder\Finder;

class Configuration
{
    protected $path;
    protected $xml;
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

    public function getPath()
    {
        return $this->path;
    }

    public function getSuites()
    {
        if(!$this->xml) return null;
        $suites = array();
        $nodes = $this->xml->xpath('//testsuite');
        while(list(, $node) = each($nodes)) {
            $suites[(string) $node['name']] = $this->getSuitePath((string) $node->directory);
        }

        return $suites;
    }

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
        try {
            $finder = new Finder();
            $files = $finder->in($this->getConfigDir() . $path)->files()->ignoreDotFiles(false);

            return $files;
        } catch (\Exception $e) {
            throw new \RuntimeException("Suite path $path could not be found");
        }
    }

    public function __toString()
    {
        return $this->path;
    }
}
