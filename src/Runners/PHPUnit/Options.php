<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Util\Str;
use PHPUnit\TextUI\DefaultResultPrinter;
use PHPUnit\TextUI\XmlConfiguration\Configuration;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

use function array_shift;
use function assert;
use function count;
use function dirname;
use function escapeshellarg;
use function explode;
use function fgets;
use function file_exists;
use function file_get_contents;
use function implode;
use function intdiv;
use function is_dir;
use function is_file;
use function is_string;
use function ksort;
use function pclose;
use function popen;
use function preg_match;
use function preg_match_all;
use function realpath;
use function sprintf;
use function strlen;
use function sys_get_temp_dir;
use function uniqid;
use function unserialize;

use const DIRECTORY_SEPARATOR;
use const PHP_BINARY;

/**
 * An object containing all configurable information used
 * to run PHPUnit via ParaTest.
 *
 * @internal
 */
final class Options
{
    public const ENV_KEY_TOKEN        = 'TEST_TOKEN';
    public const ENV_KEY_UNIQUE_TOKEN = 'UNIQUE_TEST_TOKEN';

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
     * @var string|null
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
     * @var array<string, string|null>
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
    private $group;

    /** @var string[] */
    private $excludeGroup;

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
    /** @var string|null */
    private $bootstrap;
    /** @var Configuration|null */
    private $configuration;
    /** @var string|null */
    private $coverageClover;
    /** @var string|null */
    private $coverageCrap4j;
    /** @var string|null */
    private $coverageHtml;
    /** @var string|null */
    private $coveragePhp;
    /** @var bool */
    private $coverageText;
    /** @var string|null */
    private $coverageXml;
    /** @var string */
    private $cwd;
    /** @var string|null */
    private $logJunit;
    /** @var string|null */
    private $whitelist;
    /** @var string */
    private $tmpDir;

    /**
     * @param array<string, string|null> $filtered
     * @param string[]                   $testsuite
     * @param string[]                   $group
     * @param string[]                   $excludeGroup
     * @param string[]|null              $passthru
     * @param string[]|null              $passthruPhp
     */
    private function __construct(
        ?string $bootstrap,
        bool $colors,
        ?Configuration $configuration,
        ?string $coverageClover,
        ?string $coverageCrap4j,
        ?string $coverageHtml,
        ?string $coveragePhp,
        int $coverageTestLimit,
        bool $coverageText,
        ?string $coverageXml,
        string $cwd,
        array $excludeGroup,
        ?string $filter,
        array $filtered,
        bool $functional,
        array $group,
        ?string $logJunit,
        ?int $maxBatchSize,
        bool $noTestTokens,
        bool $parallelSuite,
        ?array $passthru,
        ?array $passthruPhp,
        ?string $path,
        string $phpunit,
        int $processes,
        string $runner,
        bool $stopOnFailure,
        array $testsuite,
        string $tmpDir,
        int $verbose,
        ?string $whitelist
    ) {
        $this->bootstrap         = $bootstrap;
        $this->colors            = $colors;
        $this->configuration     = $configuration;
        $this->coverageClover    = $coverageClover;
        $this->coverageCrap4j    = $coverageCrap4j;
        $this->coverageHtml      = $coverageHtml;
        $this->coveragePhp       = $coveragePhp;
        $this->coverageTestLimit = $coverageTestLimit;
        $this->coverageText      = $coverageText;
        $this->coverageXml       = $coverageXml;
        $this->cwd               = $cwd;
        $this->excludeGroup      = $excludeGroup;
        $this->filter            = $filter;
        $this->filtered          = $filtered;
        $this->functional        = $functional;
        $this->group             = $group;
        $this->logJunit          = $logJunit;
        $this->maxBatchSize      = $maxBatchSize;
        $this->noTestTokens      = $noTestTokens;
        $this->parallelSuite     = $parallelSuite;
        $this->passthru          = $passthru;
        $this->passthruPhp       = $passthruPhp;
        $this->path              = $path;
        $this->phpunit           = $phpunit;
        $this->processes         = $processes;
        $this->runner            = $runner;
        $this->stopOnFailure     = $stopOnFailure;
        $this->testsuite         = $testsuite;
        $this->tmpDir            = $tmpDir;
        $this->verbose           = $verbose;
        $this->whitelist         = $whitelist;
    }

    public static function fromConsoleInput(InputInterface $input, string $cwd): self
    {
        $options = $input->getOptions();
        if ($options['path'] === null) {
            $options['path'] = $input->getArgument('path');
        }

        assert($options['path'] === null || is_string($options['path']));

        if ($options['processes'] === 'auto') {
            $options['processes'] = self::getNumberOfCPUCores();
        } elseif ($options['processes'] === 'half') {
            $options['processes'] = intdiv(self::getNumberOfCPUCores(), 2);
        }

        $testsuite = [];
        if ($options['testsuite'] !== null) {
            $testsuite = Str::explodeWithCleanup(
                self::TEST_SUITE_FILTER_SEPARATOR,
                $options['testsuite']
            );
        }

        // we need to register that options if they are blank but do not get them as
        // key with null value in $this->filtered as it will create problems for
        // phpunit command line generation (it will add them in command line with no value
        // and it's wrong because group and exclude-group options require value when passed
        // to phpunit)
        $group        = isset($options['group']) && $options['group'] !== ''
            ? explode(',', $options['group'])
            : [];
        $excludeGroup = isset($options['exclude-group']) && $options['exclude-group'] !== ''
            ? explode(',', $options['exclude-group'])
            : [];

        if (isset($options['filter']) && strlen($options['filter']) > 0 && ! $options['functional']) {
            throw new InvalidArgumentException('Option --filter is not implemented for non functional mode');
        }

        $filtered = [];
        if ($options['bootstrap'] !== null) {
            $filtered['bootstrap'] = $options['bootstrap'];
        }

        if (count($group) !== 0) {
            $filtered['group'] = implode(',', $group);
        }

        if (count($excludeGroup) !== 0) {
            $filtered['exclude-group'] = implode(',', $excludeGroup);
        }

        if ($options['whitelist'] !== null) {
            $filtered['whitelist'] = $options['whitelist'];
        }

        if ($options['stop-on-failure']) {
            $filtered['stop-on-failure'] = null;
        }

        $colors = $options['colors'];

        $configuration     = null;
        $configurationFile = self::guessConfigurationFile($options['configuration'], $cwd);
        if ($configurationFile !== null) {
            $configuration = (new Loader())->load($configurationFile);

            $colors = $colors || $configuration->phpunit()->colors() !== DefaultResultPrinter::COLOR_NEVER;

            $codeCoverage = $configuration->codeCoverage();

            if ($options['coverage-clover'] === null && $codeCoverage->hasClover()) {
                $options['coverage-clover'] = $codeCoverage->clover()->target()->path();
            }

            if ($options['coverage-crap4j'] === null && $codeCoverage->hasCrap4j()) {
                $options['coverage-crap4j'] = $codeCoverage->crap4j()->target()->path();
            }

            if ($options['coverage-html'] === null && $codeCoverage->hasHtml()) {
                $options['coverage-html'] = $codeCoverage->html()->target()->path();
            }

            if ($options['coverage-php'] === null && $codeCoverage->hasPhp()) {
                $options['coverage-php'] = $codeCoverage->php()->target()->path();
            }

            if ($options['coverage-xml'] === null && $codeCoverage->hasXml()) {
                $options['coverage-xml'] = $codeCoverage->xml()->target()->path();
            }

            $logging = $configuration->logging();
            if ($options['log-junit'] === null && $logging->hasJunit()) {
                $options['log-junit'] = $logging->junit()->target()->path();
            }
        }

        if ($configuration !== null) {
            $filtered['configuration'] = $configuration->filename();
        }

        ksort($filtered);

        return new self(
            $options['bootstrap'],
            $colors,
            $configuration,
            $options['coverage-clover'],
            $options['coverage-crap4j'],
            $options['coverage-html'],
            $options['coverage-php'],
            (int) $options['coverage-test-limit'],
            $options['coverage-text'],
            $options['coverage-xml'],
            $cwd,
            $excludeGroup,
            $options['filter'],
            $filtered,
            $options['functional'],
            $group,
            $options['log-junit'],
            (int) $options['max-batch-size'],
            $options['no-test-tokens'],
            $options['parallel-suite'],
            self::parsePassthru($options['passthru'] ?? null),
            self::parsePassthru($options['passthru-php'] ?? null),
            $options['path'],
            $options['phpunit'],
            (int) $options['processes'],
            $options['runner'],
            $options['stop-on-failure'],
            $testsuite,
            $options['tmp-dir'],
            (int) $options['verbose'],
            $options['whitelist']
        );
    }

    public function hasCoverage(): bool
    {
        return $this->coverageClover !== null
            || $this->coverageCrap4j !== null
            || $this->coverageHtml !== null
            || $this->coverageText
            || $this->coveragePhp !== null
            || $this->coverageXml !== null;
    }

    public static function setInputDefinition(InputDefinition $inputDefinition): void
    {
        $inputDefinition->setDefinition([
            // Arguments
            new InputArgument(
                'path',
                InputArgument::OPTIONAL,
                'The path to a directory or file containing tests.'
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
                'Generate code coverage report in text format.',
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
                'The PHPUnit binary to execute.',
                self::getPhpunitBinary()
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
                'Runner, WrapperRunner or SqliteRunner.',
                'Runner'
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
                InputOption::VALUE_REQUIRED,
                'Filter which testsuite to run'
            ),
            new InputOption(
                'tmp-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Temporary directory for internal ParaTest files',
                sys_get_temp_dir()
            ),
            new InputOption(
                'verbose',
                'v',
                InputOption::VALUE_REQUIRED,
                'If given, debug output is printed. Example: --verbose=1',
                0
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

        return 'phpunit'; // @codeCoverageIgnore
    }

    /**
     * Get the path to the vendor directory
     * First assumes vendor directory is accessible from src (i.e development)
     * Second assumes vendor directory is accessible within src.
     */
    private static function vendorDir(): string
    {
        $vendor = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'vendor';
        if (! file_exists($vendor)) {
            $vendor = dirname(__DIR__, 5); // @codeCoverageIgnore
        }

        return $vendor;
    }

    /**
     * Retrieve the default configuration given a path (directory or file).
     * This will search into the directory, if a directory is specified.
     */
    private static function guessConfigurationFile(?string $configuration, string $cwd): ?string
    {
        if ($configuration !== null && ! self::isAbsolutePath($configuration)) {
            $configuration = $cwd . DIRECTORY_SEPARATOR . $configuration;
        }

        if ($configuration !== null) {
            if (! is_dir($configuration)) {
                return $configuration;
            }

            $cwd = $configuration;
        }

        $suffixes = ['phpunit.xml', 'phpunit.xml.dist'];

        foreach ($suffixes as $suffix) {
            $fileFound = $cwd . DIRECTORY_SEPARATOR . $suffix;
            if (is_file($fileFound) && ($fileFound = realpath($fileFound)) !== false) {
                return $fileFound;
            }
        }

        return null;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return $path[0] === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i', $path) > 0;
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
            assert($cpuinfo !== false);
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        // @codeCoverageIgnoreStart
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

        // @codeCoverageIgnoreEnd

        return $cores;
    }

    /**
     * @return string[]|null
     */
    private static function parsePassthru(?string $param): ?array
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

    public function bootstrap(): ?string
    {
        return $this->bootstrap;
    }

    public function processes(): int
    {
        return $this->processes;
    }

    public function path(): ?string
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

    /** @return array<string, string|null> */
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
    public function group(): array
    {
        return $this->group;
    }

    /** @return string[] */
    public function excludeGroup(): array
    {
        return $this->excludeGroup;
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

    public function configuration(): ?Configuration
    {
        return $this->configuration;
    }

    public function coverageClover(): ?string
    {
        return $this->coverageClover;
    }

    public function coverageCrap4j(): ?string
    {
        return $this->coverageCrap4j;
    }

    public function coverageHtml(): ?string
    {
        return $this->coverageHtml;
    }

    public function coveragePhp(): ?string
    {
        return $this->coveragePhp;
    }

    public function coverageText(): bool
    {
        return $this->coverageText;
    }

    public function coverageXml(): ?string
    {
        return $this->coverageXml;
    }

    public function cwd(): string
    {
        return $this->cwd;
    }

    public function logJunit(): ?string
    {
        return $this->logJunit;
    }

    public function tmpDir(): string
    {
        return $this->tmpDir;
    }

    public function whitelist(): ?string
    {
        return $this->whitelist;
    }

    /**
     * @return array{PARATEST: int, TEST_TOKEN?: int, UNIQUE_TEST_TOKEN?: string}
     */
    public function fillEnvWithTokens(int $inc): array
    {
        $env = ['PARATEST' => 1];
        if (! $this->noTestTokens()) {
            $env[self::ENV_KEY_TOKEN]        = $inc;
            $env[self::ENV_KEY_UNIQUE_TOKEN] = uniqid($inc . '_');
        }

        return $env;
    }
}
