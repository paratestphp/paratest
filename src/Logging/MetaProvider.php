<?php

declare(strict_types=1);

namespace ParaTest\Logging;

/**
 * Class MetaProvider.
 *
 * Adds __call behavior to a logging object
 * for aggregating totals and messages
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
     * @param mixed $method
     * @param mixed $args
     */
    public function __call(string $method, array $args)
    {
        if (\preg_match(self::$totalMethod, $method, $matches) && $property = \strtolower($matches[1])) {
            return $this->getNumericValue($property);
        }
        if (\preg_match(self::$messageMethod, $method, $matches) && $type = \strtolower($matches[1])) {
            return $this->getMessages($type);
        }
    }
}
