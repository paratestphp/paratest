<?php

declare(strict_types=1);

namespace ParaTest\Tests;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\Options;
use ParaTest\Runners\PHPUnit\Runner;
use ParaTest\Runners\PHPUnit\RunnerInterface;
use ParaTest\Tests\Functional\RunnerResult;
use PHPUnit;
use PHPUnit\Framework\SkippedTestError;
use PHPUnit\Runner\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

use function copy;
use function file_exists;
use function get_class;
use function glob;
use function preg_match;
use function sprintf;
use function str_replace;
use function uniqid;

abstract class TestBase extends PHPUnit\Framework\TestCase
{
    /** @var class-string<RunnerInterface> */
    protected $runnerClass = Runner::class;
    /** @var array<string, string|bool|int> */
    protected $bareOptions = [];

    final protected function setUp(): void
    {
        $glob = glob(TMP_DIR . DS . '*');
        static::assertNotFalse($glob);

        (new Filesystem())->remove($glob);

        $this->setUpTest();
    }

    protected function setUpTest(): void
    {
    }

    /**
     * @param array<string, string|bool|int> $argv
     */
    final protected function createOptionsFromArgv(array $argv, ?string $cwd = null): Options
    {
        $inputDefinition = new InputDefinition();
        Options::setInputDefinition($inputDefinition);

        $input = new ArrayInput($argv, $inputDefinition);

        return Options::fromConsoleInput($input, $cwd ?? PARATEST_ROOT);
    }

    final protected function runRunner(?string $runnerClass = null): RunnerResult
    {
        if ($runnerClass === null) {
            $runnerClass = $this->runnerClass;
        }

        $bareOptions              = $this->bareOptions;
        $bareOptions['--tmp-dir'] = TMP_DIR;
        $output                   = new BufferedOutput();
        $wrapperRunner            = new $runnerClass($this->createOptionsFromArgv($this->bareOptions), $output);
        $wrapperRunner->run();

        return new RunnerResult($wrapperRunner->getExitCode(), $output->fetch());
    }

    final protected function assertTestsPassed(
        RunnerResult $proc,
        ?string $testPattern = null,
        ?string $assertionPattern = null
    ): void {
        static::assertMatchesRegularExpression(
            sprintf(
                '/OK \(%s tests?, %s assertions?\)/',
                $testPattern ?? '\d+',
                $assertionPattern ?? '\d+'
            ),
            $proc->getOutput(),
        );
        static::assertEquals(0, $proc->getExitCode());
    }

    /**
     * Get PHPUnit version.
     */
    final protected static function getPhpUnitVersion(): string
    {
        return Version::id();
    }

    final protected function fixture(string $fixture): string
    {
        $fixture = FIXTURES . DS . $fixture;
        if (! file_exists($fixture)) {
            throw new InvalidArgumentException("Fixture {$fixture} not found");
        }

        return $fixture;
    }

    /**
     * @return string[]
     */
    final protected function findTests(string $dir): array
    {
        $it    = new RecursiveDirectoryIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
        $it    = new RecursiveIteratorIterator($it);
        $files = [];
        foreach ($it as $file) {
            $match = preg_match('/Test\.php$/', $file->getPathname());
            self::assertNotFalse($match);
            if ($match === 0) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    /**
     * @return mixed
     */
    final protected function getObjectValue(object $object, string $property)
    {
        $prop = $this->getAccessibleProperty($object, $property);

        return $prop->getValue($object);
    }

    /**
     * @param mixed $value
     */
    final protected function setObjectValue(object $object, string $property, $value): void
    {
        $prop = $this->getAccessibleProperty($object, $property);

        $prop->setValue($object, $value);
    }

    private function getAccessibleProperty(object $object, string $property): ReflectionProperty
    {
        $refl = new ReflectionObject($object);
        $prop = $refl->getProperty($property);
        $prop->setAccessible(true);

        return $prop;
    }

    /**
     * Calls an object method even if it is protected or private.
     *
     * @param object $object     the object to call a method on
     * @param string $methodName the method name to be called
     * @param mixed  $args       0 or more arguments passed in the function
     *
     * @return mixed returns what the object's method call will return
     */
    final public function call(object $object, string $methodName, ...$args)
    {
        return self::callMethod($object, $methodName, $args);
    }

    /**
     * @param mixed[] $args
     *
     * @return mixed
     */
    final protected static function callMethod(object $object, string $methodName, array $args)
    {
        $class = get_class($object);

        $reflectionClass = new ReflectionClass($class);
        $method          = $reflectionClass->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    /**
     * @throws SkippedTestError When code coverage library is not found.
     */
    final protected static function skipIfCodeCoverageNotEnabled(): void
    {
        static $runtime;
        if ($runtime === null) {
            $runtime = new Runtime();
        }

        if ($runtime->canCollectCodeCoverage()) {
            return;
        }

        static::markTestSkipped('No code coverage driver available');
    }

    /**
     * Copy fixture file to tmp folder, cause coverage file will be deleted by merger.
     *
     * @param string $fixture Fixture coverage file name
     *
     * @return string Copied coverage file
     */
    final protected function copyCoverageFile(string $fixture, string $directory): string
    {
        $fixturePath = $this->fixture($fixture);
        $filename    = str_replace('.', '_', $directory . DS . uniqid('cov-', true));
        copy($fixturePath, $filename);

        return $filename;
    }
}
