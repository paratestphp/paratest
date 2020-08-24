<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\SqliteRunner;

/**
 * @internal
 *
 * @requires extension pdo_sqlite
 * @covers \ParaTest\Runners\PHPUnit\BaseWrapperRunner
 * @covers \ParaTest\Runners\PHPUnit\SqliteRunner
 * @covers \ParaTest\Runners\PHPUnit\Worker\BaseWorker
 * @covers \ParaTest\Runners\PHPUnit\Worker\SqliteWorker
 */
final class SqliteRunnerTest extends RunnerTestCase
{
    /** {@inheritdoc } */
    protected $runnerClass = SqliteRunner::class;
}
