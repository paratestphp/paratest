<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

final class SqliteWorker extends BaseWorker
{
    /** @var string */
    private $dbFileName;

    public function __construct(string $dbFileName)
    {
        $this->dbFileName = $dbFileName;
    }

    /**
     * {@inheritDoc}
     */
    protected function configureParameters(array &$parameters): void
    {
        $parameters[] = $this->dbFileName;
    }

    protected function doStop(): void
    {
    }
}
