<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\TestFileLoader;
use ParaTest\Tests\TestBase;

/**
 * The functionnalities of this class is tested in SuiteLoaderTest.php.
 */
class TestFileLoaderTest extends TestBase
{
    public function testConstructor(): void
    {
        $options        = new Options(['group' => 'group1']);
        $testFileLoader = new TestFileLoader($options);
        $this->assertEquals($options, $this->getObjectValue($testFileLoader, 'options'));
    }

    public function testOptionsCanBeNull(): void
    {
        $testFileLoader = new TestFileLoader();
        $this->assertNull($this->getObjectValue($testFileLoader, 'options'));
    }

    public function testLoadThrowsExceptionWithInvalidPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $testFileLoader = new TestFileLoader();
        $testFileLoader->loadPath('path/to/nowhere');
    }
}
