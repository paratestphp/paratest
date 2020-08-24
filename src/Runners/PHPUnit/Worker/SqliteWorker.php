<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class SqliteWorker extends BaseWorker
{
    /** @var string */
    private $dbFileName;

    public function __construct(OutputInterface $output, string $dbFileName)
    {
        parent::__construct($output);
        $this->dbFileName = $dbFileName;
    }

    /**
     * {@inheritDoc}
     */
    protected function configureParameters(array &$parameters): void
    {
        $parameters[] = '--database';
        $parameters[] = $this->dbFileName;
    }

    public function isRunning(): bool
    {
        $this->updateProcStatus();

        return $this->running;
    }
}
