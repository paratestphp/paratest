<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

class SqliteWorker extends BaseWorker
{
    /** @var string */
    private $dbFileName;

    public function __construct(string $dbFileName)
    {
        $this->dbFileName = $dbFileName;
    }

    public function start(string $wrapperBinary, $token = 1, $uniqueToken = null, array $parameters = [])
    {
        $parameters[] = $this->dbFileName;

        parent::start($wrapperBinary, $token, $uniqueToken, $parameters);
    }
}
