<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Util\Str;
use PHPUnit\TextUI\XmlConfiguration\Configuration;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

use function array_diff_key;
use function array_key_exists;
use function array_merge;
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
use function sys_get_temp_dir;
use function tempnam;
use function unserialize;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;

/**
 * An object containing all configurable information used
 * to run PHPUnit via ParaTest.
 */
final class Options
{
    /**
     * @see \PHPUnit\Util\Configuration
     * @see https://github.com/sebastianbergmann/phpunit/commit/80754cf323fe96003a2567f5e57404fddecff3bf
     */
    private const TEST_SUITE_FILTER_SEPARATOR = ',';

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
     * @var string[]
     */
    private $testsuite;

    /** @var int|null */
    private $maxBatchSize;

    /** @var string|null */
    private $filter;

    /** @var string[] */
    private $groups;

    /** @var string[] */
    private $excludeGroups;

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

    public static function fromConsoleInput(InputInterface $input): self
    {
        $path    = $input->getArgument('path');
        $options = self::getOptions($input);

        if (self::hasCoverage($options)) {
            $options['coverage-php'] = tempnam(sys_get_temp_dir(), 'paratest_');
        }

        if ($path !== null && $path !== '') {
            $options = array_merge(['path' => $path], $options);
        }

        if (array_key_exists('testsuite', $options)) {
            $options['testsuite'] = Str::explodeWithCleanup(
                self::TEST_SUITE_FILTER_SEPARATOR,
                $options['testsuite']
            );
        }

        return new self($options);
    }

    /**
     * Return whether or not code coverage information should be collected.
     *
     * @param array<string, string> $options
     */
    private static function hasCoverage(array $options): bool
    {
        $isFileFormat = isset($options['coverage-html'])
            || isset($options['coverage-clover'])
            || isset($options['coverage-crap4j'])
            || isset($options['coverage-xml']);
        $isTextFormat = isset($options['coverage-text']);
        $isPHP        = isset($options['coverage-php']);

        return $isTextFormat || $isFileFormat && ! $isPHP;
    }

    /**
     * Returns non-empty options.
     *
     * @return array<string, string>
     */
    private static function getOptions(InputInterface $input): array
    {
        $options = $input->getOptions();
        foreach ($options as $key => $value) {
            if (! empty($options[$key])) {
                continue;
            }

            unset($options[$key]);
        }

        return $options;
    }

    public static function setInputDefinition(InputDefinition $inputDefinition): void
    {
        $inputDefinition->setDefinition([
            // Arguments
            new InputArgument(
                'path',
                InputArgument::OPTIONAL,
                'The path to a directory or file containing tests. <comment>(default: current directory)</comment>'
            ),

            // Options
            new InputOption(
                'bootstrap',
                null,
                InputOption::VALUE_REQUIRED,
                'The bootstrap file to be used by PHPUnit.'
            ),
            new InputOption(
                'colors',
                null,
                InputOption::VALUE_NONE,
                'Displays a colored bar as a test result.'
            ),
            new InputOption(
                'configuration',
                'c',
                InputOption::VALUE_REQUIRED,
                'The PHPUnit configuration file to use.'
            ),
            new InputOption(
                'coverage-clover',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in Clover XML format.'
            ),
            new InputOption(
                'coverage-crap4j',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in Crap4J XML format.'
            ),
            new InputOption(
                'coverage-html',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in HTML format.'
            ),
            new InputOption(
                'coverage-php',
                null,
                InputOption::VALUE_REQUIRED,
                'Serialize PHP_CodeCoverage object to file.'
            ),
            new InputOption(
                'coverage-test-limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Limit the number of tests to record for each line of code. Helps to reduce memory and size of ' .
                'coverage reports.'
            ),
            new InputOption(
                'coverage-text',
                null,
                InputOption::VALUE_NONE,
                'Generate code coverage report in text format.'
            ),
            new InputOption(
                'coverage-xml',
                null,
                InputOption::VALUE_REQUIRED,
                'Generate code coverage report in PHPUnit XML format.'
            ),
            new InputOption(
                'exclude-group',
                null,
                InputOption::VALUE_REQUIRED,
                'Don\'t run tests from the specified group(s).'
            ),
            new InputOption(
                'filter',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter (only for functional mode).'
            ),
            new InputOption(
                'functional',
                'f',
                InputOption::VALUE_NONE,
                'Run test methods instead of classes in separate processes.'
            ),
            new InputOption(
                'group',
                'g',
                InputOption::VALUE_REQUIRED,
                'Only runs tests from the specified group(s).'
            ),
            new InputOption(
                'help',
                'h',
                InputOption::VALUE_NONE,
                'Display this help message.'
            ),
            new InputOption(
                'log-junit',
                null,
                InputOption::VALUE_REQUIRED,
                'Log test execution in JUnit XML format to file.'
            ),
            new InputOption(
                'max-batch-size',
                'm',
                InputOption::VALUE_REQUIRED,
                'Max batch size (only for functional mode).',
                0
            ),
            new InputOption(
                'no-test-tokens',
                null,
                InputOption::VALUE_NONE,
                'Disable TEST_TOKEN environment variables. <comment>(default: variable is set)</comment>'
            ),
            new InputOption(
                'parallel-suite',
                null,
                InputOption::VALUE_NONE,
                'Run the suites of the config in parallel.'
            ),
            new InputOption(
                'passthru',
                null,
                InputOption::VALUE_REQUIRED,
                'Pass the given arguments verbatim to the underlying test framework. Example: ' .
                '--passthru="\'--prepend\' \'xdebug-filter.php\'"'
            ),
            new InputOption(
                'passthru-php',
                null,
                InputOption::VALUE_REQUIRED,
                'Pass the given arguments verbatim to the underlying php process. Example: --passthru-php="\'-d\' ' .
                '\'zend_extension=xdebug.so\'"'
            ),
            new InputOption(
                'path',
                null,
                InputOption::VALUE_REQUIRED,
                'An alias for the path argument.'
            ),
            new InputOption(
                'phpunit',
                null,
                InputOption::VALUE_REQUIRED,
                'The PHPUnit binary to execute. <comment>(default: vendor/bin/phpunit)</comment>'
            ),
            new InputOption(
                'processes',
                'p',
                InputOption::VALUE_REQUIRED,
                'The number of test processes to run.',
                'auto'
            ),
            new InputOption(
                'runner',
                null,
                InputOption::VALUE_REQUIRED,
                'Runner, WrapperRunner or SqliteRunner. <comment>(default: Runner)</comment>'
            ),
            new InputOption(
                'stop-on-failure',
                null,
                InputOption::VALUE_NONE,
                'Don\'t start any more processes after a failure.'
            ),
            new InputOption(
                'testsuite',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filter which testsuite to run'
            ),
            new InputOption(
                'whitelist',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory to add to the coverage whitelist.'
            ),
        ]);
    }

    /**
     * Returns a collection of ParaTest's default
     * option values.
     *
     * @return array<string, string|string[]|bool|int|null>
     */
    private static function defaults(): array
    {
        return [
            'processes' => 'auto',
            'path' => '',
            'phpunit' => static::getPhpunitBinary(),
            'functional' => false,
            'stop-on-failure' => false,
            'runner' => 'Runner',
            'no-test-tokens' => false,
            'colors' => false,
            'testsuite' => [],
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
    private static function getPhpunitBinary(): string
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
            $filtered['configuration'] = (new Loader())->load($configuration);
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

    public function processes(): int
    {
        return $this->processes;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function phpunit(): string
    {
        return $this->phpunit;
    }

    public function functional(): bool
    {
        return $this->functional;
    }

    public function stopOnFailure(): bool
    {
        return $this->stopOnFailure;
    }

    /** @return array<string, (string|bool|int|Configuration|string[]|null)> */
    public function filtered(): array
    {
        return $this->filtered;
    }

    public function runner(): string
    {
        return $this->runner;
    }

    public function noTestTokens(): bool
    {
        return $this->noTestTokens;
    }

    public function colors(): bool
    {
        return $this->colors;
    }

    /** @return string[] */
    public function testsuite(): array
    {
        return $this->testsuite;
    }

    public function maxBatchSize(): ?int
    {
        return $this->maxBatchSize;
    }

    public function filter(): ?string
    {
        return $this->filter;
    }

    /** @return string[] */
    public function groups(): array
    {
        return $this->groups;
    }

    /** @return string[] */
    public function excludeGroups(): array
    {
        return $this->excludeGroups;
    }

    /** @return array<string, string> */
    public function annotations(): array
    {
        return $this->annotations;
    }

    public function parallelSuite(): bool
    {
        return $this->parallelSuite;
    }

    /** @return string[]|null */
    public function passthru(): ?array
    {
        return $this->passthru;
    }

    /** @return string[]|null */
    public function passthruPhp(): ?array
    {
        return $this->passthruPhp;
    }

    public function verbose(): int
    {
        return $this->verbose;
    }

    public function coverageTestLimit(): int
    {
        return $this->coverageTestLimit;
    }
}
