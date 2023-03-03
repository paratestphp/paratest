<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\ErrorTestCase;
use ParaTest\Logging\JUnit\FailureTestCase;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\RiskyTestCase;
use ParaTest\Logging\JUnit\SkippedTestCase;
use ParaTest\Logging\JUnit\SuccessTestCase;
use ParaTest\Logging\JUnit\TestCaseWithMessage;
use ParaTest\Logging\JUnit\TestSuite;
use ParaTest\Logging\JUnit\WarningTestCase;
use ParaTest\Logging\LogInterpreter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Color;
use PHPUnit\Util\TestDox\NamePrettifier;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

use function array_filter;
use function array_map;
use function assert;
use function class_exists;
use function count;
use function explode;
use function fclose;
use function file_get_contents;
use function filesize;
use function floor;
use function fopen;
use function fwrite;
use function get_class;
use function implode;
use function is_array;
use function is_string;
use function max;
use function preg_split;
use function rtrim;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const PHP_SAPI;
use const PHP_VERSION;

/**
 * Used for outputting ParaTest results
 *
 * @internal
 */
final class ResultPrinter
{
    private const TESTDOX_SYMBOLS = [
        SuccessTestCase::class => '✔',
        ErrorTestCase::class  => '✘',
        FailureTestCase::class => '✘',
        SkippedTestCase::class => '↩',
        RiskyTestCase::class  => '☢',
        WarningTestCase::class => '⚠',
    ];

    /** @var LogInterpreter */
    private $results;

    /**
     * The number of tests results currently printed.
     * Used to determine when to tally current results
     * and start a new row.
     *
     * @var int
     */
    private $numTestsWidth = 0;

    /**
     * Used for formatting results to a given width.
     *
     * @var int
     */
    private $maxColumn = 0;

    /**
     * The total number of cases to be run.
     *
     * @var int
     */
    private $totalCases = 0;

    /**
     * The current column being printed to.
     *
     * @var int
     */
    private $column = 0;

    /**
     * The total number of cases printed so far.
     *
     * @var int
     */
    private $casesProcessed = 0;

    /**
     * Number of columns.
     *
     * @var int
     */
    private $numberOfColumns = 80;

    /**
     * Number of skipped or incomplete tests.
     *
     * @var int
     */
    private $totalSkippedOrIncomplete = 0;

    /**
     * Do we need to try to process skipped/incompleted tests.
     *
     * @var bool
     */
    private $processSkipped = false;

    /** @var OutputInterface */
    private $output;
    /** @var Options */
    private $options;
    /** @var bool */
    private $needsTeamcity;
    /** @var bool */
    private $printsTeamcity;
    /** @var resource|null */
    private $teamcityLogFileHandle;

    public function __construct(LogInterpreter $results, OutputInterface $output, Options $options)
    {
        $this->results = $results;
        $this->output  = $output;
        $this->options = $options;

        $this->printsTeamcity = $this->options->teamcity();
        $this->needsTeamcity  = $this->options->needsTeamcity();

        if (($teamcityLogFile = $this->options->logTeamcity()) === null) {
            return;
        }

        $teamcityLogFileHandle = fopen($teamcityLogFile, 'ab+');
        assert($teamcityLogFileHandle !== false);
        $this->teamcityLogFileHandle = $teamcityLogFileHandle;
    }

    /**
     * Adds an ExecutableTest to the tracked results.
     */
    public function addTest(ExecutableTest $suite): void
    {
        $this->totalCases += $suite->getTestCount();
    }

    /**
     * Initializes printing constraints, prints header
     * information and starts the test timer.
     */
    public function start(): void
    {
        $this->numTestsWidth = strlen((string) $this->totalCases);
        $this->maxColumn     = $this->numberOfColumns
                         + (DIRECTORY_SEPARATOR === '\\' ? -1 : 0) // fix windows blank lines
                         - strlen($this->getProgress());

        if ($this->options->verbose()) {
            // @see \PHPUnit\TextUI\TestRunner::writeMessage()
            $output = $this->output;
            $write  = static function (string $type, string $message) use ($output): void {
                $output->write(sprintf("%-15s%s\n", $type . ':', $message));
            };

            // @see \PHPUnit\TextUI\TestRunner::run()
            $write('Processes', $this->options->processes() . ($this->options->functional() ? '. Functional mode is ON.' : ''));

            $configuration = $this->options->configuration();

            if (PHP_SAPI === 'phpdbg') {
                $write('Runtime', 'PHPDBG ' . PHP_VERSION); // @codeCoverageIgnore
            } else {
                $runtime = 'PHP ' . PHP_VERSION;

                if ($this->options->hasCoverage()) {
                    $filter = new Filter();
                    if ($configuration !== null && $configuration->codeCoverage()->pathCoverage()) {
                        $codeCoverageDriver = (new Selector())->forLineAndPathCoverage($filter); // @codeCoverageIgnore
                    } else {
                        $codeCoverageDriver = (new Selector())->forLineCoverage($filter);
                    }

                    $runtime .= ' with ' . $codeCoverageDriver->nameAndVersion();
                }

                $write('Runtime', $runtime);
            }

            if ($configuration !== null) {
                $write('Configuration', $configuration->filename());
            }

            if ($this->options->orderBy() === Options::ORDER_RANDOM) {
                $write('Random Seed', (string) $this->options->randomOrderSeed());
            }

            $output->write("\n");
        }

        $this->processSkipped = $this->isSkippedIncompleTestCanBeTracked($this->options);
    }

    public function println(string $string = ''): void
    {
        $this->column = 0;
        $this->output->write($string . "\n");
    }

    /**
     * Prints all results and removes any log files
     * used for aggregating results.
     */
    public function printResults(): void
    {
        $toFilter = [
            $this->getErrors(),
            $this->getWarnings(),
            $this->getFailures(),
            $this->getRisky(),
        ];
        if ($this->options->verbose()) {
            $toFilter[] = $this->getSkipped();
        }

        $failures        = array_filter($toFilter);
        $escapedFailures = array_map(static function (string $failure): string {
            return OutputFormatter::escape($failure);
        }, $failures);

        $this->output->write($this->getHeader());
        $this->output->write(implode("---\n\n", $escapedFailures));
        $this->output->write($this->getFooter());

        if ($this->teamcityLogFileHandle === null) {
            return;
        }

        $resource                    = $this->teamcityLogFileHandle;
        $this->teamcityLogFileHandle = null;
        fclose($resource);
    }

    /**
     * Prints the individual "quick" feedback for run
     * tests, that is the ".EF" items.
     */
    public function printFeedback(ExecutableTest $test): Reader
    {
        try {
            $reader = new Reader($test->getTempFile());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new EmptyLogFileException(
                $invalidArgumentException->getMessage(),
                0,
                $invalidArgumentException,
            );
        }

        $teamcityContent = null;
        if ($this->needsTeamcity) {
            $teamcityLogFile = $test->getTeamcityTempFile();

            if (filesize($teamcityLogFile) === 0) {
                throw new EmptyLogFileException("Teamcity format file {$teamcityLogFile} is empty");
            }

            $teamcityContent = file_get_contents($teamcityLogFile);
            assert($teamcityContent !== false);

            if ($this->teamcityLogFileHandle !== null) {
                fwrite($this->teamcityLogFileHandle, $teamcityContent);
            }
        }

        $this->results->addReader($reader);

        if ($teamcityContent !== null) {
            $this->output->write(OutputFormatter::escape($teamcityContent));
        } elseif ($this->options->testdox()) {
            $this->processTestdoxReader($reader->getSuite());
        } else {
            $this->processReaderFeedback($reader, $test->getTestCount());
        }

        return $reader;
    }

    /**
     * Returns the header containing resource usage.
     */
    public function getHeader(): string
    {
        $resourceUsage = (new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest();

        return "\n" . $resourceUsage . "\n\n";
    }

    /**
     * Return the footer information reporting success
     * or failure.
     */
    public function getFooter(): string
    {
        if ($this->results->isSuccessful()) {
            if ($this->results->getTotalWarnings() === 0) {
                $footer = $this->getSuccessFooter();
            } else {
                $footer = $this->getWarningFooter();
            }
        } else {
            $footer = $this->getFailedFooter();
        }

        return "{$footer}\n";
    }

    /**
     * Returns error messages.
     */
    public function getErrors(): string
    {
        $errors = $this->results->getErrors();

        return $this->getDefects($errors, 'error');
    }

    /**
     * Returns warning messages as a string.
     */
    public function getWarnings(): string
    {
        $warnings = $this->results->getWarnings();

        return $this->getDefects($warnings, 'warning');
    }

    /**
     * Returns the failure messages.
     */
    public function getFailures(): string
    {
        $failures = $this->results->getFailures();

        return $this->getDefects($failures, 'failure');
    }

    /**
     * Returns the risky messages.
     */
    public function getRisky(): string
    {
        $risky = $this->results->getRisky();

        return $this->getDefects($risky, 'risky');
    }

    /**
     * Returns the skipped messages.
     */
    public function getSkipped(): string
    {
        $risky = $this->results->getSkipped();

        return $this->getDefects($risky, 'skipped');
    }

    /**
     * Returns the total cases being printed.
     */
    public function getTotalCases(): int
    {
        return $this->totalCases;
    }

    /**
     * Process reader feedback and print it.
     */
    private function processReaderFeedback(Reader $reader, int $expectedTestCount): void
    {
        $feedbackItems = $reader->getFeedback();

        $actualTestCount = strlen($feedbackItems);

        $this->processTestOverhead($actualTestCount, $expectedTestCount);

        for ($index = 0; $index < $actualTestCount; ++$index) {
            $item = $feedbackItems[$index];

            $this->printFeedbackItem($item);
            if ($item !== 'S') {
                continue;
            }

            ++$this->totalSkippedOrIncomplete;
        }

        if (! $this->processSkipped) {
            return;
        }

        $this->printSkippedAndIncomplete($actualTestCount, $expectedTestCount);
    }

    /**
     * Is skipped/incomplete amount can be properly processed.
     *
     * @todo Skipped/Incomplete test tracking available only in functional mode for now
     *       or in regular mode but without group/exclude-group filters.
     */
    private function isSkippedIncompleTestCanBeTracked(Options $options): bool
    {
        return $options->functional()
            || (count($options->group()) === 0 && count($options->excludeGroup()) === 0);
    }

    /**
     * Process test overhead.
     *
     * In some situations phpunit can return more tests then we expect and in that case
     * this method correct total amount of tests so paratest progress will be auto corrected.
     *
     * @todo May be we need to throw Exception here instead of silent correction.
     */
    private function processTestOverhead(int $actualTestCount, int $expectedTestCount): void
    {
        $overhead = $actualTestCount - $expectedTestCount;
        if ($this->processSkipped) {
            if ($overhead > 0) {
                $this->totalCases += $overhead;
            } else {
                $this->totalSkippedOrIncomplete += -$overhead;
            }
        } else {
            $this->totalCases += $overhead;
        }
    }

    /**
     * Prints S for skipped and incomplete tests.
     *
     * If for some reason process return less tests than expected then we threat all remaining
     * as skipped or incomplete and print them as skipped (S letter)
     */
    private function printSkippedAndIncomplete(int $actualTestCount, int $expectedTestCount): void
    {
        $overhead = $expectedTestCount - $actualTestCount;
        if ($overhead <= 0) {
            return;
        }

        for ($i = 0; $i < $overhead; ++$i) {
            $this->printFeedbackItem('S');
        }
    }

    /**
     * Prints a single "quick" feedback item and increments
     * the total number of processed cases and the column
     * position.
     */
    private function printFeedbackItem(string $item): void
    {
        $this->printFeedbackItemColor($item);
        ++$this->column;
        ++$this->casesProcessed;
        if ($this->column !== $this->maxColumn && $this->casesProcessed < $this->totalCases) {
            return;
        }

        if (
            $this->casesProcessed > 0
            && $this->casesProcessed === $this->totalCases
            && ($pad = $this->maxColumn - $this->column) > 0
        ) {
            $this->output->write(str_repeat(' ', $pad));
        }

        $this->output->write($this->getProgress());
        $this->println();
    }

    private function printFeedbackItemColor(string $item): void
    {
        $buffer = $item;
        switch ($item) {
            case 'E':
                $buffer = $this->colorizeTextBox('fg-red, bold', $item);

                break;

            case 'F':
                $buffer = $this->colorizeTextBox('bg-red, fg-white', $item);

                break;

            case 'W':
            case 'I':
            case 'R':
                $buffer = $this->colorizeTextBox('fg-yellow, bold', $item);

                break;

            case 'S':
                $buffer = $this->colorizeTextBox('fg-cyan, bold', $item);

                break;
        }

        $this->output->write($buffer);
    }

    /**
     * Method that returns a formatted string
     * for a collection of errors or failures.
     *
     * @param string[] $defects
     */
    private function getDefects(array $defects, string $type): string
    {
        $count = count($defects);
        if ($count === 0) {
            return '';
        }

        $output = sprintf(
            "There %s %d %s%s:\n",
            $count === 1 ? 'was' : 'were',
            $count,
            $type,
            $count === 1 ? '' : 's',
        );

        for ($i = 1; $i <= count($defects); ++$i) {
            $output .= sprintf("\n%d) %s\n", $i, $defects[$i - 1]);
        }

        $output .= "\n";

        return $output;
    }

    /**
     * Prints progress for large test collections.
     */
    private function getProgress(): string
    {
        return sprintf(
            ' %' . $this->numTestsWidth . 'd / %' . $this->numTestsWidth . 'd (%3s%%)',
            $this->casesProcessed,
            $this->totalCases,
            floor(($this->totalCases > 0 ? $this->casesProcessed / $this->totalCases : 0) * 100),
        );
    }

    /**
     * Get the footer for a test collection that had tests with
     * failures or errors.
     */
    private function getFailedFooter(): string
    {
        $formatString = "FAILURES!\n%s";

        return $this->colorizeTextBox(
            'fg-white, bg-red',
            sprintf(
                $formatString,
                $this->getFooterCounts(),
            ),
        );
    }

    /**
     * Get the footer for a test collection containing all successful
     * tests.
     */
    private function getSuccessFooter(): string
    {
        if ($this->totalSkippedOrIncomplete === 0) {
            $tests   = $this->totalCases;
            $asserts = $this->results->getTotalAssertions();

            return $this->colorizeTextBox(
                'fg-black, bg-green',
                sprintf(
                    'OK (%d test%s, %d assertion%s)',
                    $tests,
                    $tests === 1 ? '' : 's',
                    $asserts,
                    $asserts === 1 ? '' : 's',
                ),
            );
        }

        return $this->colorizeTextBox(
            'fg-black, bg-yellow',
            sprintf(
                "OK, but incomplete, skipped, or risky tests!\n"
                . '%s',
                $this->getFooterCounts(),
            ),
        );
    }

    private function getWarningFooter(): string
    {
        $formatString = "WARNINGS!\n%s";

        return $this->colorizeTextBox(
            'fg-black, bg-yellow',
            sprintf(
                $formatString,
                $this->getFooterCounts(),
            ),
        );
    }

    private function getFooterCounts(): string
    {
        $counts = [
            'Tests' => $this->results->getTotalTests(),
            'Assertions' => $this->results->getTotalAssertions(),
        ] + array_filter([
            'Errors' => $this->results->getTotalErrors(),
            'Failures' => $this->results->getTotalFailures(),
            'Warnings' => $this->results->getTotalWarnings(),
            'Skipped' => $this->results->getTotalSkipped(),
        ]);

        $output = '';
        foreach ($counts as $label => $count) {
            $output .= sprintf('%s: %s, ', $label, $count);
        }

        return rtrim($output, ', ') . '.';
    }

    /** @see \PHPUnit\TextUI\DefaultResultPrinter::colorizeTextBox */
    private function colorizeTextBox(string $color, string $buffer): string
    {
        if (! $this->options->colors()) {
            return $buffer;
        }

        $lines = preg_split('/\r\n|\r|\n/', $buffer);
        assert(is_array($lines));
        $padding = max(array_map('\\strlen', $lines));

        $styledLines = [];
        foreach ($lines as $line) {
            $styledLines[] = Color::colorize($color, str_pad($line, $padding));
        }

        return implode(PHP_EOL, $styledLines);
    }

    private function processTestdoxReader(TestSuite $testSuite): void
    {
        foreach ($testSuite->suites as $suite) {
            $this->processTestdoxReader($suite);
        }

        if ($testSuite->cases === []) {
            return;
        }

        $prettifier = new NamePrettifier(false);

        $separator = PHP_EOL;
        foreach ($testSuite->cases as $index => $case) {
            if ($index === 0) {
                $class = $case->class;
                assert(class_exists($class));

                $this->output->writeln($prettifier->prettifyTestClass($class));
            }

            assert(isset($class) && is_string($class) && class_exists($class));

            $separator = PHP_EOL;
            $testCase  = new $class($case->name);
            assert($testCase instanceof TestCase);

            $time = '';
            if ($this->options->verbose()) {
                $time = sprintf(' [%.2f ms]', $case->time * 1000);
            }

            $testName = $prettifier->prettifyTestCase($testCase);
            $this->output->writeln(sprintf(
                ' %s %s%s',
                self::TESTDOX_SYMBOLS[get_class($case)],
                $testName,
                $time,
            ));

            $failingCase = $case instanceof FailureTestCase || $case instanceof ErrorTestCase || $case instanceof WarningTestCase;
            if (! $this->options->verbose() && ! $failingCase) {
                continue;
            }

            if (! $case instanceof TestCaseWithMessage) {
                continue;
            }

            $lines    = explode("\n", $case->text);
            $lines[0] = '';
            if ($case instanceof SkippedTestCase) {
                unset($lines[0]);
            }

            $lines[] = '';
            foreach ($lines as $index => $line) {
                $lines[$index] = '   │' . ($line !== '' ? ' ' . $line : '');
            }

            $this->output->writeln(implode(PHP_EOL, $lines) . PHP_EOL);
            $separator = '';
        }

        $this->output->write($separator);
    }
}
