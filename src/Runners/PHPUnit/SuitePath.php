<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use function preg_quote;

/**
 * Representation of test suite paths found in phpunit.xml.
 */
final class SuitePath
{
    private const DEFAULT_SUFFIX = 'Test.php';

    /** @var string */
    private $path;

    /** @var string */
    private $suffix;

    /** @var string[] */
    private $excludedPaths;

    /**
     * @param string[] $excludedPaths
     */
    public function __construct(string $path, array $excludedPaths, string $suffix)
    {
        if ($suffix === '') {
            $suffix = self::DEFAULT_SUFFIX;
        }

        $this->path          = $path;
        $this->excludedPaths = $excludedPaths;
        $this->suffix        = $suffix;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string[]
     */
    public function getExcludedPaths(): array
    {
        return $this->excludedPaths;
    }

    public function getSuffix(): string
    {
        return $this->suffix;
    }

    public function getPattern(): string
    {
        return '|' . preg_quote($this->getSuffix()) . '$|';
    }
}
