<?php namespace ParaTest\Logging\JUnit;

use ParaTest\Logging\LogInterpreter;

class Writer
{
    protected $name;
    protected $outputPath;
    protected $interpreter;

    public function __construct($name, 
                                $outputPath, 
                                LogInterpreter $interpreter)
    {
        $this->name = $name;
        $this->outputPath = $outputPath;
        $this->interpreter = $interpreter;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOutputPath()
    {
        return $this->outputPath;
    }

    public function getXml()
    {
        $readers = $this->interpreter->getReaders();
        $document = new \DOMDocument("1.0", "UTF-8");
        $testsuites = $document->createElement("testsuites");
        $document->appendChild($testsuites);

        $testsuite = $document->createElement("testsuite");
        $testsuite->setAttribute("name", $this->name);
        $testsuite->setAttribute("tests", $this->interpreter->getTotalTests());
        $testsuite->setAttribute("assertions", $this->interpreter->getTotalAssertions());
        $testsuite->setAttribute("failures", $this->interpreter->getTotalFailures());
        $testsuite->setAttribute("errors", $this->interpreter->getTotalErrors());
        //$testsuite->setAttribute("time", $this->interpreter->getTotalTime());

        $suites = array();
        foreach($readers as $reader) {
            $rsuites = $reader->getSuites();
            foreach($rsuites as $rsuite) {
                if(!isset($suites[$rsuite->file])) {
                    $suites[$rsuite->file] = $rsuite;
                    continue;
                }
                $suites[$rsuite->file]->cases = array_merge($suites[]);
            }
        }
        
  /*      foreach($suites as $suite) {
            $suiteElem = $document->createElement('testsuite');
            $suiteElem->setAttribute('name', $suite->name);
            $suiteElem->setAttribute('file', $suite->file);
            $suiteElem->setAttribute('tests', $suite->tests);
        }*/
    }
}