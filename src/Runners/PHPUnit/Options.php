<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use RuntimeException;
use Symfony\Component\Process\Process;

use function array_diff_key;
use function array_shift;
use function assert;
use function count;
use function dirname;
use function escapeshellarg;
use function explode;
use function fgets;
use function file_exists;
use function file_get_contents;
use function in_array;
use function intdiv;
use function is_dir;
use function is_file;
use function is_string;
use function pclose;
use function popen;
use function preg_match_all;
use function realpath;
use function rtrim;
use function sprintf;
use function strlen;
use function unserialize;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;

/**
 * An object containing all configurable information used
 * to run PHPUnit via ParaTest.
 *
 * @property-read int $processes
 * @property-read string $path
 * @property-read string $phpunit
 * @property-read bool $functional
 * @property-read bool $stopOnFailure
 * @property-read array<string, (string|bool|int|Configuration|string[]|null)> $filtered
 * @property-read string $runner
 * @property-read bool $noTestTokens
 * @property-read bool $colors
 * @property-read string|string[] $testsuite
 * @property-read int|null $maxBatchSize
 * @property-read string $filter
 * @property-read string[] $groups
 * @property-read string[] $excludeGroups
 * @property-read array<string, string> $annotations
 * @property-read bool $parallelSuite
 * @property-read string[]|null $passthru
 * @property-read string[]|null $passthruPhp
 * @property-read int $verbose
 * @property-read int $coverageTestLimit
 */
final class Options
{
    /**
     * The number of processes to run at a time.
     *
     * @var int
     */
    private $processes;

    /**
     * The test path pointing to tests that will
     * be run.
     *
     * @var string
     */
    private $path;

    /**
     * The path to the PHPUnit binary that will be run.
     *
     * @var string
     */
    private $phpunit;

    /**
     * Determines whether or not ParaTest runs in
     * functional mode. If enabled, ParaTest will run
     * every test method in a separate process.
     *
     * @var bool
     */
    private $functional;

    /**
     * Prevents starting new tests after a test has failed.
     *
     * @var bool
     */
    private $stopOnFailure;

    /**
     * A collection of post-processed option values. This is the collection
     * containing ParaTest specific options.
     *
     * @var array<string, (string|bool|int|Configuration|string[]|null)>
     */
    private $filtered;

    /** @var string */
    private $runner;

    /** @var bool */
    private $noTestTokens;

    /** @var bool */
    private $colors;

    /**
     * Filters which tests to run.
     *
     * @var string|string[]
     */
    private $testsuite;

    /** @var int|null */
    private $maxBatchSize;

    /** @var string */
    private $filter;

    // phpcs:disable SlevomatCodingStandard.Classes.UnusedPrivateElements.WriteOnlyProperty

    /** @var string[] */
    private $groups;

    /** @var string[] */
    private $excludeGroups;

    // phpcs:enable

    /**
     * A collection of option values directly corresponding
     * to certain annotations - i.e group.
     *
     * @var array<string, string>
     */
    private $annotations = [];

    /**
     * Running the suite defined in the config in parallel.
     *
     * @var bool
     */
    private $parallelSuite;

    /**
     * Strings that gets passed verbatim to the underlying phpunit command.
     *
     * @var string[]|null
     */
    private $passthru;

    /**
     * Strings that gets passed verbatim to the underlying php process.
     *
     * @var string[]|null
     */
    private $passthruPhp;

    /**
     * Verbosity. If true, debug output will be printed.
     *
     * @var int
     */
    private $verbose;

    /**
     * Limit the number of tests recorded in coverage reports
     * to avoid them growing too big.
     *
     * @var int
     */
    private $coverageTestLimit;

    /**
     * @param array<string, string|bool|int|string[]> $opts
     */
    public function __construct(array $opts = [])
    {
        foreach (self::defaults() as $opt => $value) {
            $opts[$opt] = $opts[$opt] ?? $value;
        }

        if ($opts['processes'] === 'auto') {
            $opts['processes'] = self::getNumberOfCPUCores();
        } elseif ($opts['processes'] === 'half') {
            $opts['processes'] = intdiv(self::getNumberOfCPUCores(), 2);
        }

        $this->processes         = $opts['processes'];
        $this->path              = $opts['path'];
        $this->phpunit           = $opts['phpunit'];
        $this->functional        = $opts['functional'];
        $this->stopOnFailure     = $opts['stop-on-failure'];
        $this->runner            = $opts['runner'];
        $this->noTestTokens      = $opts['no-test-tokens'];
        $this->colors            = $opts['colors'];
        $this->testsuite         = $opts['testsuite'];
        $this->maxBatchSize      = (int) $opts['max-batch-size'];
        $this->filter            = $opts['filter'];
        $this->parallelSuite     = $opts['parallel-suite'];
        $this->passthru          = $this->parsePassthru($opts['passthru'] ?? null);
        $this->passthruPhp       = $this->parsePassthru($opts['passthru-php'] ?? null);
        $this->verbose           = $opts['verbose'] ?? 0;
        $this->coverageTestLimit = $opts['coverage-test-limit'] ?? 0;

        // we need to register that options if they are blank but do not get them as
        // key with null value in $this->filtered as it will create problems for
        // phpunit command line generation (it will add them in command line with no value
        // and it's wrong because group and exclude-group options require value when passed
        // to phpunit)
        $this->groups        = isset($opts['group']) && $opts['group'] !== ''
            ? explode(',', $opts['group'])
            : [];
        $this->excludeGroups = isset($opts['exclude-group']) && $opts['exclude-group'] !== ''
            ? explode(',', $opts['exclude-group'])
            : [];

        if (isset($opts['filter']) && strlen($opts['filter']) > 0 && ! $this->functional) {
            throw new RuntimeException('Option --filter is not implemented for non functional mode');
        }

        $this->filtered = $this->filterOptions($opts);
        $this->initAnnotations();
    }

    /**
     * Public read accessibility.
     *
     * @return mixed
     */
    public function __get(string $var)
    {
        return $this->{$var};
    }

    /**
     * Public read accessibility
     * (e.g. to make empty($options->property) work as expected).
     */
    public function __isset(string $var): bool
    {
        return isset($this->{$var});
    }

    /**
     * Returns a collection of ParaTest's default
     * option values.
     *
     * @return array<string, string|bool|int|null>
     */
    private static function defaults(): array
    {
        return [
            'processes' => 'auto',
            'path' => '',
            'phpunit' => static::phpunit(),
            'functional' => false,
            'stop-on-failure' => false,
            'runner' => 'Runner',
            'no-test-tokens' => false,
            'colors' => false,
            'testsuite' => '',
            'max-batch-size' => 0,
            'filter' => null,
            'parallel-suite' => false,
            'passthru' => null,
            'passthru-php' => null,
            'verbose' => 0,
            'coverage-test-limit' => 0,
        ];
    }

    /**
     * Get the path to phpunit
     * First checks if a Windows batch script is in the composer vendors directory.
     * Composer automatically handles creating a .bat file, so if on windows this should be the case.
     * Second look for the phpunit binary under nix
     * Defaults to phpunit on the users PATH.
     *
     * @return string $phpunit the path to phpunit
     */
    private static function phpunit(): string
    {
        $vendor = static::vendorDir();

        $phpunit = $vendor . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit' .
            DIRECTORY_SEPARATOR . 'phpunit';
        if (file_exists($phpunit)) {
            return $phpunit;
        }

        return 'phpunit';
    }

    /**
     * Get the path to the vendor directory
     * First assumes vendor directory is accessible from src (i.e development)
     * Second assumes vendor directory is accessible within src.
     */
    private static function vendorDir(): string
    {
        $vendor = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'vendor';
        if (! file_exists($vendor)) {
            $vendor = dirname(dirname(dirname(dirname(dirname(__DIR__)))));
        }

        return $vendor;
    }

    /**
     * Filter options to distinguish between paratest
     * internal options and any other options.
     *
     * @param array<string, (string|bool|int|string[]|null)> $options
     *
     * @return array<string, (string|bool|int|Configuration|string[]|null)>
     */
    private function filterOptions(array $options): array
    {
        $filtered = array_diff_key($options, [
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit,
            'functional' => $this->functional,
            'stop-on-failure' => $this->stopOnFailure,
            'runner' => $this->runner,
            'no-test-tokens' => $this->noTestTokens,
            'colors' => $this->colors,
            'testsuite' => $this->testsuite,
            'max-batch-size' => $this->maxBatchSize,
            'filter' => $this->filter,
            'parallel-suite' => $this->parallelSuite,
            'passthru' => $this->passthru,
            'passthru-php' => $this->passthruPhp,
            'verbose' => $this->verbose,
            'coverage-test-limit' => $this->coverageTestLimit,
        ]);
        if (($configuration = $this->getConfigurationPath($filtered)) !== null) {
            $filtered['configuration'] = new Configuration($configuration);
        }

        return $filtered;
    }

    /**
     * Take an array of filtered options and return a
     * configuration path.
     *
     * @param array<string, (string|bool|int|string[]|null)> $filtered
     */
    private function getConfigurationPath(array $filtered): ?string
    {
        if (isset($filtered['configuration'])) {
            return $this->getDefaultConfigurationForPath($filtered['configuration'], $filtered['configuration']);
        }

        return $this->getDefaultConfigurationForPath();
    }

    /**
     * Retrieve the default configuration given a path (directory or file).
     * This will search into the directory, if a directory is specified.
     *
     * @param string $path    The path to search into
     * @param string $default The default value to give back
     */
    private function getDefaultConfigurationForPath(string $path = '.', ?string $default = null): ?string
    {
        if ($this->isFile($path)) {
            return realpath($path);
        }

        $path     = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $suffixes = ['phpunit.xml', 'phpunit.xml.dist'];

        foreach ($suffixes as $suffix) {
            if ($this->isFile($path . $suffix)) {
                return realpath($path . $suffix);
            }
        }

        return $default;
    }

    /**
     * Load options that are represented by annotations
     * inside of tests i.e @group group1 = --group group1.
     */
    private function initAnnotations(): void
    {
        $annotatedOptions = ['group'];
        foreach ($this->filtered as $key => $value) {
            if (! in_array($key, $annotatedOptions, true)) {
                continue;
            }

            assert(is_string($value));
            $this->annotations[$key] = $value;
        }
    }

    private function isFile(string $file): bool
    {
        return file_exists($file) && ! is_dir($file);
    }

    /**
     * Return number of (logical) CPU cores, use 2 as fallback.
     *
     * Used to set number of processes if argument is set to "auto", allows for portable defaults for doc and scripting.
     *
     * @internal
     */
    public static function getNumberOfCPUCores(): int
    {
        $cores = 2;
        if (is_file('/proc/cpuinfo')) {
            // Linux (and potentially Windows with linux sub systems)
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        } elseif (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            if (($process = @popen('wmic cpu get NumberOfCores', 'rb')) !== false) {
                fgets($process);
                $cores = (int) fgets($process);
                pclose($process);
            }
        } elseif (($process = @popen('sysctl -n hw.ncpu', 'rb')) !== false) {
            // *nix (Linux, BSD and Mac)
            $cores = (int) fgets($process);
            pclose($process);
        }

        return $cores;
    }

    /**
     * @return string[]|null
     */
    private function parsePassthru(?string $param): ?array
    {
        if ($param === null) {
            return null;
        }

        $stringToArgumentProcess = Process::fromShellCommandline(
            sprintf(
                '%s -r %s -- %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg('echo serialize($argv);'),
                $param
            )
        );
        $stringToArgumentProcess->mustRun();

        $passthruAsArguments = unserialize($stringToArgumentProcess->getOutput());
        array_shift($passthruAsArguments);

        if (count($passthruAsArguments) === 0) {
            return null;
        }

        return $passthruAsArguments;
    }
}
