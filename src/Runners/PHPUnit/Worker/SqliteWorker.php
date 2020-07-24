<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit\Worker;

use ParaTest\Runners\PHPUnit\Options;

class SqliteWorker extends BaseWorker
{
    /** @var string */
    private $dbFileName;

    public function __construct(string $dbFileName)
    {
        $this->dbFileName = $dbFileName;
    }

    /**
     * @param int|null $token
     * @param null|string $uniqueToken
     */
    public function start(
        string $wrapperBinary,
        ?int $token = 1,
        ?string $uniqueToken = null,
        array $parameters = [],
        ?Options $options = null
    ) {
        $parameters[] = $this->dbFileName;

        parent::start($wrapperBinary, $token, $uniqueToken, $parameters, $options);
    }
}
