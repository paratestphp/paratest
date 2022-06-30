<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Logging\JUnit;

use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\Writer;
use ParaTest\Logging\LogInterpreter;
use ParaTest\Tests\TestBase;

use function file_exists;
use function file_get_contents;
use function preg_replace;
use function str_replace;
use function unlink;

/**
 * @internal
 *
 * @covers \ParaTest\Logging\JUnit\Writer
 */
final class WriterTest extends TestBase
{
    /** @var Writer  */
    protected $writer;
    /** @var LogInterpreter */
    protected $interpreter;
    /** @var string  */
    protected $passing;

    public function setUpTest(): void
    {
        $this->interpreter = new LogInterpreter();
        $this->writer      = new Writer($this->interpreter, 'test/fixtures/tests/');
        $this->passing     = FIXTURES . DS . 'results' . DS . 'single-passing.xml';
    }

    public function testConstructor(): void
    {
        static::assertInstanceOf(
            LogInterpreter::class,
            $this->getObjectValue($this->writer, 'interpreter')
        );
        static::assertEquals('test/fixtures/tests/', $this->writer->getName());
    }

    public function testSingleFileLog(): void
    {
        $this->addPassingReader();
        $xml = $this->writer->getXml();
        $xml = str_replace('3.703701', '1.234567', $xml);

        static::assertXmlStringEqualsXmlString((string) file_get_contents($this->passing), $xml);
    }

    public function testMixedFileLog(): void
    {
        $mixed  = FIXTURES . DS . 'results' . DS . 'mixed-results.xml';
        $reader = new Reader($mixed);
        $this->interpreter->addReader($reader);
        $output = $this->tmpDir . DS . 'mixed-results.xml';
        $writer = new Writer($this->interpreter, './test/fixtures/failing_tests');
        $writer->write($output);
        $xml = (string) file_get_contents($output);
        $xml = preg_replace('/time="[\d\.]+"/', 'time="1.234567"', $xml);

        static::assertXmlStringEqualsXmlString((string) file_get_contents($mixed), $xml);
    }

    public function testDataProviderWithSpecialCharacters(): void
    {
        $mixed  = FIXTURES . DS . 'results' . DS . 'data-provider-with-special-chars.xml';
        $reader = new Reader($mixed);
        $this->interpreter->addReader($reader);
        $output = $this->tmpDir . DS . 'data-provider-with-special-chars.xml';
        $writer = new Writer($this->interpreter, 'test/fixtures/tests/');
        $writer->write($output);
        $xml = (string) file_get_contents($output);
        $xml = preg_replace('/time="[\d\.]+"/', 'time="1.234567"', $xml);

        static::assertXmlStringEqualsXmlString((string) file_get_contents($mixed), $xml);
    }

    public function testWriteToFile(): void
    {
        $output = $this->tmpDir . DS . 'passing.xml';
        $this->addPassingReader();
        $this->writer->write($output);
        $xml = (string) file_get_contents($output);
        $xml = preg_replace('/time="[\d\.]+"/', 'time="1.234567"', $xml);
        static::assertXmlStringEqualsXmlString((string) file_get_contents($this->passing), $xml);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    public function testWriteToFileInNonExistentDir(): void
    {
        $output = $this->tmpDir . DS . 'logs' . DS . 'new' . DS . 'dir' . DS . 'passing.xml';
        $this->addPassingReader();
        $this->writer->write($output);
        $xml = (string) file_get_contents($output);
        $xml = preg_replace('/time="[\d\.]+"/', 'time="1.234567"', $xml);
        static::assertXmlStringEqualsXmlString((string) file_get_contents($this->passing), $xml);
        if (! file_exists($output)) {
            return;
        }

        unlink($output);
    }

    private function addPassingReader(): void
    {
        $reader = new Reader($this->passing);
        $this->interpreter->addReader($reader);
    }

    /**
     * Empty line attributes, e.g. line="" breaks Jenkins parsing since it needs to be an integer.
     * To repair, ensure that empty line attributes are actually written as 0 instead of empty string.
     */
    public function testThatEmptyLineAttributesConvertToZero(): void
    {
        $mixed  = FIXTURES . DS . 'results' . DS . 'junit-example-result.xml';
        $reader = new Reader($mixed);
        $this->interpreter->addReader($reader);
        $writer = new Writer($this->interpreter, 'test/fixtures/tests/');
        $xml    = $writer->getXml();

        static::assertStringNotContainsString(
            'line=""',
            $xml,
            'Expected no empty line attributes (line=""), but found one.'
        );
    }

    /**
     * @covers \ParaTest\Logging\JUnit\Reader::parseTestSuite
     * @covers \ParaTest\Logging\LogInterpreter::mergeSuites
     */
    public function testMergeOfCompleteExampleWithEveryXmlCaseCombination(): void
    {
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '01.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '02.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '03.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '04.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '05.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '06.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '07.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '08.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '09.xml'));
        $this->interpreter->addReader(new Reader(FIXTURES . DS . 'results' . DS . 'parallel' . DS . '10.xml'));

        $output = $this->tmpDir . DS . 'actual-combined.xml';
        $writer = new Writer($this->interpreter, '');
        $writer->write($output);
        $xml = (string) file_get_contents($output);
        $xml = preg_replace('/time="[\d\.]+"/', 'time="1.234567"', $xml);

        $expectedResult = FIXTURES . DS . 'results' . DS . 'parallel' . DS . 'combined.xml';

        static::assertXmlStringEqualsXmlString((string) file_get_contents($expectedResult), $xml);
    }
}
