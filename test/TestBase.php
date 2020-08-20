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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionObject;
use SebastianBergmann\Environment\Runtime;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

use function file_exists;
use function glob;
use function preg_match;
use function sprintf;

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

        if (! isset($argv['--processes'])) {
            $argv['--processes'] = PROCESSES_FOR_TESTS;
        }
        if (! isset($argv['--tmp-dir'])) {
            $argv['--tmp-dir'] = TMP_DIR;
        }

        $input = new ArrayInput($argv, $inputDefinition);

        return Options::fromConsoleInput($input, $cwd ?? PARATEST_ROOT);
    }

    final protected function runRunner(?string $runnerClass = null): RunnerResult
    {
        if ($runnerClass === null) {
            $runnerClass = $this->runnerClass;
        }

        $output        = new BufferedOutput();
        $wrapperRunner = new $runnerClass($this->createOptionsFromArgv($this->bareOptions), $output);
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
        $refl = new ReflectionObject($object);
        $prop = $refl->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
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
}
