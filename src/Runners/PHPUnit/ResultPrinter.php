<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use InvalidArgumentException;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\LogInterpreter;
use RuntimeException;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function floor;
use function sprintf;
use function strlen;

use const DIRECTORY_SEPARATOR;

/**
 * Used for outputting ParaTest results
 */
final class ResultPrinter
{
    /**
     * A collection of ExecutableTest objects.
     *
     * @var ExecutableTest[]
     */
    private $suites = [];

    /** @var LogInterpreter */
    private $results;

    /**
     * The number of tests results currently printed.
     * Used to determine when to tally current results
     * and start a new row.
     *
     * @var int
     */
    private $numTestsWidth;

    /**
     * Used for formatting results to a given width.
     *
     * @var int
     */
    private $maxColumn;

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
     * Whether to display a red or green bar.
     *
     * @var bool
     */
    private $colors;

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

    public function __construct(LogInterpreter $results, OutputInterface $output)
    {
        $this->results = $results;
        $this->output  = $output;
    }

    /**
     * Adds an ExecutableTest to the tracked results.
     */
    public function addTest(ExecutableTest $suite): self
    {
        $this->suites[]    = $suite;
        $increment         = $suite->getTestCount();
        $this->totalCases += $increment;

        return $this;
    }

    /**
     * Initializes printing constraints, prints header
     * information and starts the test timer.
     */
    public function start(Options $options): void
    {
        $this->numTestsWidth = strlen((string) $this->totalCases);
        $this->maxColumn     = $this->numberOfColumns
                         + (DIRECTORY_SEPARATOR === '\\' ? -1 : 0) // fix windows blank lines
                         - strlen($this->getProgress());
        $this->output->write(sprintf(
            "\nRunning phpunit in %d process%s with %s%s\n\n",
            $options->processes(),
            $options->processes() > 1 ? 'es' : '',
            $options->phpunit(),
            $options->functional() ? '. Functional mode is ON.' : ''
        ));
        if (isset($options->filtered()['configuration'])) {
            $this->output->write(sprintf(
                "Configuration read from %s\n\n",
                $options->filtered()['configuration']->filename()
            ));
        }

        $this->colors         = $options->colors();
        $this->processSkipped = $this->isSkippedIncompleTestCanBeTracked($options);
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
    public function flush(): void
    {
        $this->printResults();
        $this->clearLogs();
    }

    /**
     * Print final results.
     */
    public function printResults(): void
    {
        $this->output->write($this->getHeader());
        $this->output->write($this->getErrors());
        $this->output->write($this->getFailures());
        $this->output->write($this->getWarnings());
        $this->output->write($this->getFooter());
    }

    /**
     * Prints the individual "quick" feedback for run
     * tests, that is the ".EF" items.
     */
    public function printFeedback(ExecutableTest $test): void
    {
        try {
            $reader = new Reader($test->getTempFile());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new RuntimeException(sprintf(
                "%s\n" .
                "The process: %s\n" .
                "This means a PHPUnit process was unable to run \"%s\"\n",
                $invalidArgumentException->getMessage(),
                $test->getLastCommand(),
                $test->getPath()
            ), 0, $invalidArgumentException);
        }

        $this->results->addReader($reader);
        $this->processReaderFeedback($reader, $test->getTestCount());
    }

    /**
     * Returns the header containing resource usage.
     */
    public function getHeader(): string
    {
        $resourceUsage = (new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest();

        return "\n\n" . $resourceUsage . "\n\n";
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
     * Whether the test run is successful and has no warnings.
     */
    public function isSuccessful(): bool
    {
        return $this->results->isSuccessful();
    }

    /**
     * Return the footer information reporting success
     * or failure.
     */
    public function getFooter(): string
    {
        return $this->isSuccessful()
                    ? $this->getSuccessFooter()
                    : $this->getFailedFooter();
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
     * Returns error messages.
     */
    public function getErrors(): string
    {
        $errors = $this->results->getErrors();

        return $this->getDefects($errors, 'error');
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

        $actualTestCount = count($feedbackItems);

        $this->processTestOverhead($actualTestCount, $expectedTestCount);

        foreach ($feedbackItems as $item) {
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
        if ($this->column !== $this->maxColumn) {
            return;
        }

        $this->output->write($this->getProgress());
        $this->println();
    }

    private function printFeedbackItemColor(string $item): void
    {
        $format = '%s';
        if ($this->colors) {
            switch ($item) {
                case 'E':
                    // fg-red
                    $format = "\x1b[31m%s\x1b[0m";

                    break;

                case 'F':
                    // bg-red
                    $format = "\x1b[41m%s\x1b[0m";

                    break;

                case 'W':
                case 'I':
                case 'R':
                    // fg-yellow
                    $format = "\x1b[33m%s\x1b[0m";

                    break;

                case 'S':
                    // fg-cyan
                    $format = "\x1b[36m%s\x1b[0m";

                    break;
            }
        }

        $this->output->write(sprintf($format, $item));
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
            $count === 1 ? '' : 's'
        );

        for ($i = 1; $i <= count($defects); ++$i) {
            $output .= sprintf("\n%d) %s\n", $i, $defects[$i - 1]);
        }

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
            floor(($this->totalCases > 0 ? $this->casesProcessed / $this->totalCases : 0) * 100)
        );
    }

    /**
     * Get the footer for a test collection that had tests with
     * failures or errors.
     */
    private function getFailedFooter(): string
    {
        $formatString = "FAILURES!\nTests: %d, Assertions: %d, Failures: %d, Errors: %d.\n";

        return "\n" . $this->red(
            sprintf(
                $formatString,
                $this->results->getTotalTests(),
                $this->results->getTotalAssertions(),
                $this->results->getTotalFailures(),
                $this->results->getTotalErrors()
            )
        );
    }

    /**
     * Get the footer for a test collection containing all successful
     * tests.
     */
    private function getSuccessFooter(): string
    {
        $tests   = $this->totalCases;
        $asserts = $this->results->getTotalAssertions();

        if ($this->totalSkippedOrIncomplete > 0) {
            // phpunit 4.5 produce NOT plural version for test(s) and assertion(s) in that case
            // also it shows result in standard color scheme
            return sprintf(
                "OK, but incomplete, skipped, or risky tests!\n"
                . "Tests: %d, Assertions: %d, Incomplete: %d.\n",
                $tests,
                $asserts,
                $this->totalSkippedOrIncomplete
            );
        }

        // phpunit 4.5 produce plural version for test(s) and assertion(s) in that case
        // also it shows result as black text on green background
        return $this->green(sprintf(
            "OK (%d test%s, %d assertion%s)\n",
            $tests,
            $tests === 1 ? '' : 's',
            $asserts,
            $asserts === 1 ? '' : 's'
        ));
    }

    private function green(string $text): string
    {
        if ($this->colors) {
            return "\x1b[30;42m\x1b[2K"
                . $text
                . "\x1b[0m\x1b[2K";
        }

        return $text;
    }

    private function red(string $text): string
    {
        if ($this->colors) {
            return "\x1b[37;41m\x1b[2K"
                . $text
                . "\x1b[0m\x1b[2K";
        }

        return $text;
    }

    /**
     * Deletes all the temporary log files for ExecutableTest objects
     * being printed.
     */
    private function clearLogs(): void
    {
        foreach ($this->suites as $suite) {
            $suite->deleteFile();
        }
    }
}
