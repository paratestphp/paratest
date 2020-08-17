<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use ParaTest\Runners\PHPUnit\Worker\SqliteWorker;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function count;
use function dirname;
use function implode;
use function realpath;
use function serialize;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;
use function usleep;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

final class SqliteRunner extends BaseWrapperRunner
{
    /** @var SqliteWorker[] */
    private $workers = [];

    /** @var PDO */
    private $db;

    /** @var string */
    private $dbFileName;

    public function __construct(Options $opts, OutputInterface $output)
    {
        parent::__construct($opts, $output);

        $this->dbFileName = (string) ($opts->filtered()['database'] ?? tempnam(sys_get_temp_dir(), 'paratest_db_'));
        $this->db         = new PDO('sqlite:' . $this->dbFileName);
    }

    public function __destruct()
    {
        unset($this->db);
        unlink($this->dbFileName);
    }

    public function run(): void
    {
        $this->initialize();
        $this->createTable();
        $this->assignAllPendingTests();
        $this->startWorkers();
        $this->waitForAllToFinish();
        $this->complete();
        $this->checkIfWorkersCrashed();
    }

    /**
     * Start all workers.
     */
    private function startWorkers(): void
    {
        $wrapper = realpath(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit-sqlite-wrapper.php'
        );
        assert($wrapper !== false);

        for ($i = 1; $i <= $this->options->processes(); ++$i) {
            $worker = new SqliteWorker($this->output, $this->dbFileName);
            if ($this->options->noTestTokens()) {
                $token       = null;
                $uniqueToken = null;
            } else {
                $token       = $i;
                $uniqueToken = uniqid();
            }

            $worker->start($wrapper, $token, $uniqueToken);
            $this->workers[] = $worker;
        }
    }

    /**
     * Wait for all workers to complete their tests and print output.
     */
    private function waitForAllToFinish(): void
    {
        do {
            foreach ($this->workers as $key => $worker) {
                if ($worker->isRunning()) {
                    continue;
                }

                unset($this->workers[$key]);
            }

            usleep(10000);
            $this->printOutput();
        } while (count($this->workers) > 0);
    }

    /**
     * Initialize test queue table.
     *
     * @throws RuntimeException
     */
    private function createTable(): void
    {
        $statement = 'CREATE TABLE tests (
                          id INTEGER PRIMARY KEY,
                          command TEXT NOT NULL UNIQUE,
                          file_name TEXT NOT NULL,
                          reserved_by_process_id INTEGER,
                          completed INTEGER DEFAULT 0
                        )';

        if ($this->db->exec($statement) === false) {
            throw new RuntimeException('Error while creating sqlite database table: ' . $this->db->errorCode());
        }
    }

    /**
     * Push all tests onto test queue.
     */
    private function assignAllPendingTests(): void
    {
        foreach ($this->pending as $fileName => $test) {
            $this->db->prepare('INSERT INTO tests (command, file_name) VALUES (:command, :fileName)')
                ->execute([
                    ':command' => serialize($test->commandArguments(
                        $this->options->phpunit(),
                        $this->options->filtered()
                    )),
                    ':fileName' => $fileName,
                ]);
        }
    }

    /**
     * Loop through all completed tests and print their output.
     */
    private function printOutput(): void
    {
        $stmt = $this->db->query('SELECT id, file_name FROM tests WHERE completed = 1');
        assert($stmt !== false);
        $tests = $stmt->fetchAll();
        assert($tests !== false);
        foreach ($tests as $test) {
            $this->printer->printFeedback($this->pending[$test['file_name']]);
            $this->db->prepare('DELETE FROM tests WHERE id = :id')->execute([
                'id' => $test['id'],
            ]);
        }
    }

    /**
     * Make sure that all tests were executed successfully.
     */
    private function checkIfWorkersCrashed(): void
    {
        $countStmt = $this->db->query('SELECT COUNT(id) FROM tests');
        assert($countStmt !== false);
        if ($countStmt->fetchColumn(0) === '0') {
            return;
        }

        $commandStmt = $this->db->query('SELECT command FROM tests');
        assert($commandStmt !== false);

        throw new RuntimeException(
            'Some workers have crashed.' . PHP_EOL
            . '----------------------' . PHP_EOL
            . 'All workers have quit, but some tests are still to be executed.' . PHP_EOL
            . 'This may be the case if some tests were killed forcefully (for example, using exit()).' . PHP_EOL
            . '----------------------' . PHP_EOL
            . 'Failed test command(s):' . PHP_EOL
            . '----------------------' . PHP_EOL
            . implode(PHP_EOL, (array) $commandStmt->fetchAll(PDO::FETCH_COLUMN))
        );
    }
}
