<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function uniqid;

class TestGenerator
{
    /** @var string */
    public $path;
    /** @var string  */
    private $fullPath;

    public function __construct()
    {
        $this->path     = 'generated-tests' . DS . uniqid();
        $this->fullPath = FIXTURES . DS . $this->path;

        if (is_dir($this->fullPath)) {
            return;
        }

        mkdir($this->fullPath, 0777, true);
    }

    public function generate(int $tests = 1, int $methods = 1): void
    {
        for ($i = 0; $i < $tests; ++$i) {
            $name = "Generated{$i}Test";
            $file = $this->fullPath . DS . $name . '.php';
            file_put_contents($file, $this->generateTestString($name, $methods));
        }
    }

    private function generateTestString(string $testName, int $methods = 1): string
    {
        $php = '<' . "?php\n\nclass $testName extends PHPUnit\\Framework\\TestCase\n{\n";

        for ($i = 0; $i < $methods; ++$i) {
            $php .= "\tpublic function testMethod{$i}(): void{";
            $php .= "\$this->assertTrue(true);}\n";
        }

        $php .= "}\n";

        return $php;
    }
}
