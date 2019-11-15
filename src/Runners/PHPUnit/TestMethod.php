<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

/**
 * Class TestMethod.
 *
 * Represents a set of tests grouped in batch which can be passed to a single phpunit process.
 * Batch limited to run tests only from one php test case file.
 * Used for running ParaTest in functional mode.
 *
 * @todo Rename to Batch
 */
class TestMethod extends ExecutableTest
{
    /**
     * The path to the test case file.
     *
     * @var string
     */
    protected $path;

    /**
     * A set of filters for test, they are merged into phpunit's --filter option.
     *
     * @var string[]
     */
    protected $filters;

    /**
     * Constructor.
     *
     * Passed filters must be unescaped and must represent test name, optionally including
     * dataset name (numeric or named).
     *
     * @param string   $testPath path to phpunit test case file
     * @param string[] $filters  array of filters or single filter
     */
    public function __construct(string $testPath, array $filters)
    {
        $this->path = $testPath;
        // for compatibility with other code (tests), which can pass string (one filter)
        // instead of array of filters
        $this->filters = $filters;
    }

    /**
     * Returns the test method's filters.
     *
     * @return string[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Returns the test method's name.
     *
     * This method will join all filters via pipe character and return as string.
     *
     * @return string
     */
    public function getName(): string
    {
        return \implode('|', $this->filters);
    }

    /**
     * Additional processing for options being passed to PHPUnit.
     *
     * This sets up the --filter switch used to run a single PHPUnit test method.
     * This method also provide escaping for method name to be used as filter regexp.
     *
     * @param array $options
     *
     * @return array
     */
    protected function prepareOptions(array $options): array
    {
        $re = \array_reduce($this->filters, function ($r, $v) {
            $isDataSet = \strpos($v, ' with data set ') !== false;

            return ($r ? $r . '|' : '') . \preg_quote($v, '/') . ($isDataSet ? '$' : "(?:\s|\$)");
        });
        $options['filter'] = '/' . $re . '/';

        return $options;
    }

    /**
     * Get the expected count of tests to be executed.
     *
     * @return int
     */
    public function getTestCount(): int
    {
        return \count($this->filters);
    }
}
