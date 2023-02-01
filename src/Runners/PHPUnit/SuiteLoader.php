<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Parser\NoClassInFileException;
use ParaTest\Parser\ParsedClass;
use ParaTest\Parser\Parser;
use PHPUnit\Framework\ExecutionOrderDependency;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Metadata\Api\DataProvider;
use PHPUnit\Metadata\Api\Dependencies;
use PHPUnit\Metadata\Api\Groups;
use PHPUnit\Runner\PhptTestCase;
use PHPUnit\TextUI\Command\Result;
use PHPUnit\TextUI\Command\WarmCodeCoverageCacheCommand;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Configuration\TestSuiteBuilder;
use PHPUnit\TextUI\TestSuiteFilterProcessor;
use PHPUnit\TextUI\XmlConfiguration\CodeCoverage\FilterMapper;
use PHPUnit\TextUI\XmlConfiguration\LoadedFromFileConfiguration;
use ReflectionMethod;
use RuntimeException;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\StaticAnalysis\CacheWarmer;
use SebastianBergmann\Environment\Runtime;
use SebastianBergmann\FileIterator\Facade;
use SebastianBergmann\Timer\Timer;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function ksort;
use function preg_match;
use function realpath;
use function sprintf;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function version_compare;

use const PHP_VERSION;

/** @internal */
final class SuiteLoader
{
    public readonly int $testCount;
    /** @var array<non-empty-string, bool> */
    public readonly array $files;

    public function __construct(
        private readonly Options $options,
        OutputInterface $output
    )
    {
        (new PhpHandler)->handle($this->options->configuration->php());

        if ($this->options->configuration->hasBootstrap()) {
            include_once $this->options->configuration->bootstrap();
        }
        
        $testSuite = (new TestSuiteBuilder)->build($this->options->configuration);
        (new TestSuiteFilterProcessor)->process($this->options->configuration, $testSuite);

        $this->testCount = count($testSuite);

        $files = [];
        $this->loadFiles($testSuite, $files);
        $this->files = array_keys($files);

        if (! $this->options->configuration->hasCoverageReport()) {
            return;
        }

        ob_start();
        $result = (new WarmCodeCoverageCacheCommand($this->options->configuration))->execute();
        $output->write(ob_get_clean());
        $output->write($result->output());
        if (Result::SUCCESS !== $result->shellExitCode()) {
            exit($result->shellExitCode());
        }
    }

    private function loadFiles(TestSuite $testSuite, array & $files): void
    {
        foreach ($testSuite as $test) {
            if ($test instanceof TestSuite) {
                $this->loadFiles($test, $files);
                continue;
            }
            
            if ($test instanceof PhptTestCase) {
                $refProperty = new \ReflectionProperty(PhptTestCase::class, 'filename');
                $filename = $refProperty->getValue($test);
                assert(is_string($filename) && '' !== $filename);
                $filename = $this->stripCwd($filename);
                $files[$filename] = true;
                
                continue;
            }
            
            if ($test instanceof TestCase) {
                $refClass = new \ReflectionClass($test);
                $filename = $refClass->getFileName();
                assert(is_string($filename) && '' !== $filename);
                $filename = $this->stripCwd($filename);
                $files[$filename] = true;

                continue;
            }
        }
    }

    /**
     * @param non-empty-string $filename
     * @return non-empty-string
     */
    private function stripCwd(string $filename): string
    {
        if (! str_starts_with($filename, $this->options->cwd)) {
            return $filename;
        }
        
        $filename = substr($filename, 1+strlen($this->options->cwd));
        assert(is_string($filename) && false !== $filename);
        
        return $filename;
    }
}
