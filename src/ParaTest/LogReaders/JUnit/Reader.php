<?php namespace ParaTest\LogReaders\JUnit;

class Reader
{
    protected $xml;
    protected $isSingle = false;
    protected $suites = array();

    public function __construct($logFile) 
    {
        if(!file_exists($logFile))
            throw new \InvalidArgumentException("Log file $logFile does not exist");

        $this->xml = simplexml_load_file($logFile);
        $this->init();
    }

    public function isSingleSuite()
    {
        return $this->isSingle;
    }

    public function getSuites()
    {
        return $this->suites;
    }

    protected function init()
    {
        $nodes = $this->xml->xpath('/testsuites/testsuite/testsuite');
        $this->isSingle = sizeof($nodes) === 0;
        if(!$this->isSingle) {
            $node = current($this->xml->xpath("/testsuites/testsuite"));
            $this->suites[] = static::suiteFromNode($node);
            $caseNodes = $this->xml->xpath('//testcase');
            $cases = array();
            while(list( , $node) = each($caseNodes)) {
                $case = $node;
                if(!isset($cases[(string)$node['file']])) $cases[(string)$node['file']] = array();
                $cases[(string)$node['file']][] = $node;
            }
            foreach($cases as $file => $arr) {
                $testCases = array();
                $cb = array('ParaTest\\LogReaders\\JUnit\\Reader', 'caseFromNode');
                $suite = array_reduce($arr, function($result, $c) use(&$testCases, $cb) {
                    $testCases[] = call_user_func_array($cb, array($c));
                    $result['name'] = $c['class'];
                    $result['file'] = $c['file'];
                    $result['tests'] = $result['tests'] + 1;
                    $result['assertions'] += $c['assertions'];
                    $result['failures'] += sizeof($c->xpath('failure'));
                    $result['errors'] += sizeof($c->xpath('error'));
                    $result['time'] += floatval($c['time']);
                    return $result;
                }, array('name' => '',
                         'file' => '',
                         'tests' => 0,
                         'assertions' => 0,
                         'failures' => 0,
                         'errors' => 0,
                         'time' => 0));
                $suite = $this->suiteFromArray($suite);
                $suite->cases = $testCases;
                $this->suites[0]->suites[] = $suite;
            }
        }
    }

    public static function caseFromNode($node) {
        return new TestCase((string) $node['name'],
                            (string) $node['class'],
                            (string) $node['file'],
                            (string) $node['line'],
                            (string) $node['assertions'],
                            (string) $node['time']);
    }

    public static function suiteFromArray($arr)
    {
        return new TestSuite($arr['name'],
                             $arr['tests'],
                             $arr['assertions'],
                             $arr['failures'],
                             $arr['errors'],
                             $arr['time'],
                             $arr['file']);
    }

    public static function suiteFromNode($node) 
    {
        return new TestSuite((string) $node['name'],
                             (string) $node['tests'],
                             (string) $node['assertions'],
                             (string) $node['failures'],
                             (string) $node['errors'],
                             (string) $node['time']);
    }
}