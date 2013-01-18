<?php namespace ParaTest\Runners\PHPUnit;

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

    public function getPath()
    {
        return $this->path;
    }

    public function getSuites()
    {
        if(!$this->xml) return null;
        $suites = array();
        $nodes = $this->xml->xpath('//testsuite');
        while(list(, $node) = each($nodes))
            $suites[(string)$node['name']] = $this->getSuitePath((string)$node->directory);
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
        $real = realpath($this->getConfigDir() . $path);
        if($real) return $real;
        throw new \RuntimeException("Suite path $path could not be found");
    }

    public function __toString()
    {
        return $this->path;
    }
}
