<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

use ParaTest\Options;
use PHPUnit\Logging\TestDox\TestResultCollection as TestDoxTestResultCollection;
use PHPUnit\Runner\TestSuiteSorter;
use PHPUnit\TestRunner\TestResult\TestResult;
use PHPUnit\TextUI\Output\Default\ResultPrinter as DefaultResultPrinter;
use PHPUnit\TextUI\Output\Printer;
use PHPUnit\TextUI\Output\SummaryPrinter;
use PHPUnit\TextUI\Output\TestDox\ResultPrinter as TestDoxResultPrinter;
use PHPUnit\Util\Color;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\Timer\ResourceUsageFormatter;
use SplFileInfo;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function fclose;
use function feof;
use function floor;
use function fopen;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function sprintf;
use function str_repeat;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;
use const PHP_VERSION;

/** @internal */
final class ResultPrinter
{
    public readonly Printer $printer;

    private int $numTestsWidth  = 0;
    private int $maxColumn      = 0;
    private int $totalCases     = 0;
    private int $column         = 0;
    private int $casesProcessed = 0;
    private int $numberOfColumns;
    /** @var resource|null */
    private $teamcityLogFileHandle;
    /** @var array<non-empty-string, int> */
    private array $tailPositions;

    public function __construct(
        private readonly OutputInterface $output,
        private readonly Options $options
    ) {
        $this->printer = new class ($this->output) implements Printer {
            public function __construct(
                private readonly OutputInterface $output,
            ) {
            }

            public function print(string $buffer): void
            {
                $this->output->write(OutputFormatter::escape($buffer));
            }

            public function flush(): void
            {
            }
        };

        $this->numberOfColumns = $this->options->configuration->columns();

        if (! $this->options->configuration->hasLogfileTeamcity()) {
            return;
        }

        $teamcityLogFileHandle = fopen($this->options->configuration->logfileTeamcity(), 'ab+');
        assert($teamcityLogFileHandle !== false);
        $this->teamcityLogFileHandle = $teamcityLogFileHandle;
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

        if ($this->options->hasShard()) {
            $write('Shard', $this->options->currentShard . '/' . $this->options->totalShards);
        }

        $runtime = 'PHP ' . PHP_VERSION;

        if ($this->options->configuration->hasCoverageReport()) {
            $filter = new Filter();
            if ($this->options->configuration->pathCoverage()) {
                $codeCoverageDriver = (new Selector())->forLineAndPathCoverage($filter); // @codeCoverageIgnore
            } else {
                $codeCoverageDriver = (new Selector())->forLineCoverage($filter);
            }

            $runtime .= ' with ' . $codeCoverageDriver->nameAndVersion();
        }

        $write('Runtime', $runtime);

        if ($this->options->configuration->hasConfigurationFile()) {
            $write('Configuration', $this->options->configuration->configurationFile());
        }

        if ($this->options->configuration->executionOrder() === TestSuiteSorter::ORDER_RANDOMIZED) {
            $write('Random Seed', (string) $this->options->configuration->randomOrderSeed());
        }

        $output->write("\n");
    }

    public function printFeedback(
        SplFileInfo $progressFile,
        SplFileInfo $outputFile,
        SplFileInfo|null $teamcityFile
    ): void {
        if ($this->options->needsTeamcity && $teamcityFile !== null) {
            $teamcityProgress = $this->tailMultiple([$teamcityFile]);

            if ($this->teamcityLogFileHandle !== null) {
                fwrite($this->teamcityLogFileHandle, $teamcityProgress);
            }
        }

        if ($this->options->configuration->outputIsTeamCity()) {
            assert(isset($teamcityProgress));
            $this->output->write($teamcityProgress);

            return;
        }

        if ($this->options->configuration->noProgress()) {
            return;
        }

        $unexpectedOutput = $this->tail($outputFile);
        if ($unexpectedOutput !== '') {
            $this->output->write($unexpectedOutput);
        }

        $feedbackItems = $this->tail($progressFile);
        if ($feedbackItems === '') {
            return;
        }

        $actualTestCount = strlen($feedbackItems);
        for ($index = 0; $index < $actualTestCount; ++$index) {
            $this->printFeedbackItem($feedbackItems[$index]);
        }
    }

    /**
     * @param list<SplFileInfo>                         $teamcityFiles
     * @param array<string,TestDoxTestResultCollection> $testdoxResults
     */
    public function printResults(TestResult $testResult, array $teamcityFiles, array $testdoxResults): void
    {
        if ($this->options->needsTeamcity) {
            $teamcityProgress = $this->tailMultiple($teamcityFiles);

            if ($this->teamcityLogFileHandle !== null) {
                fwrite($this->teamcityLogFileHandle, $teamcityProgress);
                $resource                    = $this->teamcityLogFileHandle;
                $this->teamcityLogFileHandle = null;
                fclose($resource);
            }
        }

        if ($this->options->configuration->outputIsTeamCity()) {
            assert(isset($teamcityProgress));
            $this->output->write($teamcityProgress);

            return;
        }

        $this->printer->print(PHP_EOL . (new ResourceUsageFormatter())->resourceUsageSinceStartOfRequest() . PHP_EOL . PHP_EOL);

        $defaultResultPrinter = new DefaultResultPrinter(
            $this->printer,
            $this->options->configuration->displayDetailsOnPhpunitDeprecations() || $this->options->configuration->displayDetailsOnAllIssues(),
            true,
            $this->options->configuration->displayDetailsOnPhpunitNotices() || $this->options->configuration->displayDetailsOnAllIssues(),
            true,
            true,
            true,
            true,
            $this->options->configuration->displayDetailsOnIncompleteTests() || $this->options->configuration->displayDetailsOnAllIssues(),
            $this->options->configuration->displayDetailsOnSkippedTests() || $this->options->configuration->displayDetailsOnAllIssues(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerDeprecations() || $this->options->configuration->displayDetailsOnAllIssues(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerErrors() || $this->options->configuration->displayDetailsOnAllIssues(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerNotices() || $this->options->configuration->displayDetailsOnAllIssues(),
            $this->options->configuration->displayDetailsOnTestsThatTriggerWarnings() || $this->options->configuration->displayDetailsOnAllIssues(),
            $this->options->configuration->reverseDefectList(),
        );

        if ($this->options->configuration->outputIsTestDox()) {
            (new TestDoxResultPrinter(
                $this->printer,
                $this->options->configuration->colors(),
                $this->options->configuration->columns(),
                $this->options->configuration->testDoxOutputWithSummary(),
            ))->print($testResult, $testdoxResults);

            $defaultResultPrinter = new DefaultResultPrinter(
                $this->printer,
                $this->options->configuration->displayDetailsOnPhpunitDeprecations() || $this->options->configuration->displayDetailsOnAllIssues(),
                true,
                $this->options->configuration->displayDetailsOnPhpunitNotices() || $this->options->configuration->displayDetailsOnAllIssues(),
                true,
                false,
                false,
                true,
                false,
                false,
                $this->options->configuration->displayDetailsOnTestsThatTriggerDeprecations() || $this->options->configuration->displayDetailsOnAllIssues(),
                $this->options->configuration->displayDetailsOnTestsThatTriggerErrors() || $this->options->configuration->displayDetailsOnAllIssues(),
                $this->options->configuration->displayDetailsOnTestsThatTriggerNotices() || $this->options->configuration->displayDetailsOnAllIssues(),
                $this->options->configuration->displayDetailsOnTestsThatTriggerWarnings() || $this->options->configuration->displayDetailsOnAllIssues(),
                $this->options->configuration->reverseDefectList(),
            );
        }

        $defaultResultPrinter->print($testResult);

        (new SummaryPrinter(
            $this->printer,
            $this->options->configuration->colors(),
        ))->print($testResult);
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

        $this->output->write($this->getProgress() . "\n");
        $this->column = 0;
    }

    private function printFeedbackItemColor(string $item): void
    {
        $buffer = match ($item) {
            'E' => $this->colorizeTextBox('fg-red, bold', $item),
            'F' => $this->colorizeTextBox('bg-red, fg-white', $item),
            'I', 'N', 'D', 'R', 'W' => $this->colorizeTextBox('fg-yellow, bold', $item),
            'S' => $this->colorizeTextBox('fg-cyan, bold', $item),
            '.' => $item,
        };
        $this->output->write($buffer);
    }

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

    /** @param list<SplFileInfo> $files */
    private function tailMultiple(array $files): string
    {
        $content = '';
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $content .= $this->tail($file);
        }

        return $content;
    }

    private function tail(SplFileInfo $file): string
    {
        $path = $file->getPathname();
        assert($path !== '');
        $handle = fopen($path, 'r');
        assert($handle !== false);
        $fseek = fseek($handle, $this->tailPositions[$path] ?? 0);
        assert($fseek === 0);

        $contents = '';
        while (! feof($handle)) {
            $fread = fread($handle, 8192);
            assert($fread !== false);
            $contents .= $fread;
        }

        $ftell = ftell($handle);
        assert($ftell !== false);
        $this->tailPositions[$path] = $ftell;
        fclose($handle);

        return $contents;
    }
}
