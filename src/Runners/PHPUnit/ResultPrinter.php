<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\JUnit\LogMerger;
use ParaTest\Logging\JUnit\ErrorTestCase;
use ParaTest\Logging\JUnit\FailureTestCase;
use ParaTest\Logging\JUnit\Reader;
use ParaTest\Logging\JUnit\RiskyTestCase;
use ParaTest\Logging\JUnit\SkippedTestCase;
use ParaTest\Logging\JUnit\SuccessTestCase;
use ParaTest\Logging\JUnit\WarningTestCase;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use PHPUnit\Runner\TestSuiteSorter;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Output\Default\ResultPrinter as DefaultResultPrinter;
use PHPUnit\TextUI\Output\Printer;
use PHPUnit\TextUI\Output\SummaryPrinter;
use PHPUnit\Util\Color;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use function assert;
use function fclose;
use function floor;
use function fopen;
use function sprintf;
use function str_repeat;
use function strlen;
use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const PHP_VERSION;

/**
 * Used for outputting ParaTest results
 *
 * @internal
 */
final class ResultPrinter
{
    public readonly Printer $printer;

    private LogMerger $results;
    private int $numTestsWidth = 0;
    private int $maxColumn = 0;
    private int $totalCases = 0;
    private int $column = 0;
    private int $casesProcessed = 0;

    private int $numberOfColumns = 80;
    /** @var bool */
    private $needsTeamcity;
    /** @var bool */
    private $printsTeamcity;
    /** @var resource|null */
    private $teamcityLogFileHandle;
    /** @var array<non-empty-string, int> */
    private array $tailPositions;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Options $options
    )
    {
        $this->printer = new class($this->output) implements Printer {
            public function __construct(
                private readonly OutputInterface $output,
            ) {}

            public function print(string $buffer): void
            {
                $this->output->write(OutputFormatter::escape($buffer));
            }

            public function flush(): void
            {
            }
        };
//        $this->printsTeamcity = $this->options->teamcity();
//        $this->needsTeamcity  = $this->options->needsTeamcity();
//
//        if (($teamcityLogFile = $this->options->logTeamcity()) === null) {
//            return;
//        }
//
//        $teamcityLogFileHandle = fopen($teamcityLogFile, 'ab+');
//        assert($teamcityLogFileHandle !== false);
//        $this->teamcityLogFileHandle = $teamcityLogFileHandle;
    }

    public function setTestCount(int $testCount): void
    {
        $this->totalCases = $testCount;
    }

    public function start(): void
    {
        $this->numTestsWidth = strlen((string) $this->totalCases);
        $this->maxColumn     = $this->numberOfColumns
                         + (DIRECTORY_SEPARATOR === '\\' ? -1 : 0) // fix windows blank lines
                         - strlen($this->getProgress());

        // @see \PHPUnit\TextUI\TestRunner::writeMessage()
        $output = $this->output;
        $write  = static function (string $type, string $message) use ($output): void {
            $output->write(sprintf("%-15s%s\n", $type . ':', $message));
        };

        // @see \PHPUnit\TextUI\Application::writeRuntimeInformation()
        $write('Processes', (string) $this->options->processes);

        $configuration = $this->options->configuration;

        $runtime = 'PHP ' . PHP_VERSION;

        if ($this->options->configuration->hasCoverageReport()) {
            $filter = new Filter();
            if ($configuration !== null && $configuration->pathCoverage()) {
                $codeCoverageDriver = (new Selector())->forLineAndPathCoverage($filter); // @codeCoverageIgnore
            } else {
                $codeCoverageDriver = (new Selector())->forLineCoverage($filter);
            }

            $runtime .= ' with ' . $codeCoverageDriver->nameAndVersion();
        }

        $write('Runtime', $runtime);

        if ($configuration->hasConfigurationFile()) {
            $write('Configuration', $configuration->configurationFile());
        }

        if ($this->options->configuration->executionOrder() === TestSuiteSorter::ORDER_RANDOMIZED) {
            $write('Random Seed', (string) $this->options->configuration->randomOrderSeed());
        }

        $output->write("\n");
    }

    public function println(string $string = ''): void
    {
        $this->column = 0;
        $this->output->write($string . "\n");
    }

    public function printResults(TestResult $testResult): void
    {
        $resultPrinter = new DefaultResultPrinter(
            $this->printer,
            $this->options->configuration->displayDetailsOnIncompleteTests(),
            $this->options->configuration->displayDetailsOnSkippedTests(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerDeprecations(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerErrors(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerNotices(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerWarnings(),
            false,
        );
        $summaryPrinter = new SummaryPrinter(
            $this->printer,
            $this->options->configuration->colors()
        );

        $this->printer->print(PHP_EOL . (new ResourceUsageFormatter)->resourceUsageSinceStartOfRequest() . PHP_EOL . PHP_EOL);

        $resultPrinter->print($testResult);
        $summaryPrinter->print($testResult);
    }

    public function printFeedback(WrapperWorker $worker): void
    {
        $feedbackItems = $this->tail($worker->progressFile);
        $feedbackItems = preg_replace('/ +\\d+ \\/ \\d+ \\(\\d+%\\)\\s*/', '', $feedbackItems);

        $actualTestCount = strlen($feedbackItems);
        for ($index = 0; $index < $actualTestCount; ++$index) {
            $this->printFeedbackItem($feedbackItems[$index]);
        }
    }

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
        $buffer = match ($item) {
            'E' => $this->colorizeTextBox('fg-red, bold', $item),
            'F' => $this->colorizeTextBox('bg-red, fg-white', $item),
            'W', 'I', 'R' => $this->colorizeTextBox('fg-yellow, bold', $item),
            'S' => $this->colorizeTextBox('fg-cyan, bold', $item),
            default => $item,
        };
        $this->output->write($buffer);
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

    private function colorizeTextBox(string $color, string $buffer): string
    {
        if (! $this->options->configuration->colors()) {
            return $buffer;
        }

        return Color::colorizeTextBox($color, $buffer);
    }

    private function tail(\SplFileInfo $progressFile): string
    {
        $path = $progressFile->getPathname();
        $handle = fopen($path, 'r');
        assert(false !== $handle);
        $fseek = fseek($handle, $this->tailPositions[$path] ?? 0);
        assert(0 === $fseek);

        $contents = '';
        while (!feof($handle)) {
            $fread = fread($handle, 8192);
            assert(false !== $fread);
            $contents .= $fread;
        }
        $ftell = ftell($handle);
        assert(false !== $ftell);
        $this->tailPositions[$path] = $ftell;
        fclose($handle);

        return $contents;
    }
}
