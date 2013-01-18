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
        $path = dirname($this->path) . DIRECTORY_SEPARATOR;
        while(list(, $node) = each($nodes))
            $suites[(string)$node['name']] = realpath($path . (string)$node->directory);
        return $suites;
    }

    public function __toString()
    {
        return $this->path;
    }
}
