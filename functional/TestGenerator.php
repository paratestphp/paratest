<?php

class TestGenerator
{
    public $path;
    private $fullPath;

    public function __construct()
    {
        $this->path = 'generated-tests'.DS.uniqid();
        $this->fullPath = FIXTURES.DS.$this->path;

        if (!is_dir($this->fullPath)) {
            mkdir($this->fullPath, 0777, true);
        }
    }

    public function generate($tests=1, $methods=1)
    {
        for($i=0; $i<$tests; $i++) {
            $name = "Generated{$i}Test";
            $file = $this->fullPath.DS.$name.".php";
            file_put_contents($file, $this->generateTestString($name, $methods));
        }
    }

    private function generateTestString($testName, $methods=1)
    {
        $php = "<"."?php\n\nclass $testName extends PHPUnit_Framework_TestCase\n{\n";

        for($i=0; $i<$methods; $i++) {
            $php .= "\tpublic function testMethod{$i}(){";
            $php .= "\$this->assertTrue(true);}\n";
        }

        $php .= "}\n";
        return $php;
    }
}
