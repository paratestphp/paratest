<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\TestFileLoader;

/**
 * The functionnalities of this class is tested in SuiteLoaderTest.php.
 */
class TestFileLoaderTest extends \ParaTest\Tests\TestBase
{
    public function testConstructor()
    {
        $options = new Options(['group' => 'group1']);
        $testFileLoader = new TestFileLoader($options);
        $this->assertEquals($options, $this->getObjectValue($testFileLoader, 'options'));
    }

    public function testOptionsCanBeNull()
    {
        $testFileLoader = new TestFileLoader();
        $this->assertNull($this->getObjectValue($testFileLoader, 'options'));
    }

    public function testLoadThrowsExceptionWithInvalidPath()
    {
        $this->expectException(\InvalidArgumentException::class);

        $testFileLoader = new TestFileLoader();
        $testFileLoader->loadPath('path/to/nowhere');
    }
}
