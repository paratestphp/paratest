<?php

declare(strict_types=1);

namespace ParaTest\Logging;

use Exception;

use function preg_match;
use function strtolower;

/**
 * Adds __call behavior to a logging object
 * for aggregating totals and messages
 *
 * @method int getTotalTests()
 * @method int getTotalAssertions()
 * @method int getTotalFailures()
 * @method int getTotalErrors()
 * @method int getTotalWarning()
 * @method int getTotalTime()
 * @method string[] getFailures()
 * @method string[] getErrors()
 * @method string[] getWarnings()
 */
abstract class MetaProvider
{
    /**
     * This pattern is used to see whether a missing
     * method is a "total" method or not.
     *
     * @var string
     */
    protected static $totalMethod = '/^getTotal([\w]+)$/';

    /**
     * This pattern is used to add message retrieval for a given
     * type - i.e getFailures() or getErrors().
     *
     * @var string
     */
    protected static $messageMethod = '/^get((Failure|Error|Warning)s)$/';

    /**
     * Simplify aggregation of totals or messages.
     *
     * @param mixed[] $args
     *
     * @return float|int|string[]
     */
    public function __call(string $method, array $args)
    {
        if (preg_match(self::$totalMethod, $method, $matches) && $property = strtolower($matches[1])) {
            return $this->getNumericValue($property);
        }

        if (preg_match(self::$messageMethod, $method, $matches) && $type = strtolower($matches[1])) {
            return $this->getMessages($type);
        }

        throw new Exception("Method $method uknown");
    }

    /**
     * Returns a value as either a float or int.
     *
     * @return float|int
     */
    abstract protected function getNumericValue(string $property);

    /**
     * Gets messages of a given type and
     * merges them into a single collection.
     *
     * @return string[]
     */
    abstract protected function getMessages(string $type): array;
}
