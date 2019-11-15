<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

use Exception;
use ParaTest\Runners\PHPUnit\Worker\SqliteWorker;
use PDO;
use RuntimeException;

class SqliteRunner extends WrapperRunner
{
    /** @var PDO */
    private $db;

    /** @var string */
    private $dbFileName = null;

    public function __construct(array $opts = [])
    {
        parent::__construct($opts);

        $this->dbFileName = (string) ($opts['database'] ?? \tempnam(\sys_get_temp_dir(), 'paratest_db_'));
        $this->db = new PDO('sqlite:' . $this->dbFileName);
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            unset($this->db);
            \unlink($this->dbFileName);
        }
    }

    public function run()
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
    protected function startWorkers(): void
    {
        $wrapper = \realpath(__DIR__ . '/../../../bin/phpunit-sqlite-wrapper');

        for ($i = 1; $i <= $this->options->processes; ++$i) {
            $worker = new SqliteWorker($this->dbFileName);
            if ($this->options->noTestTokens) {
                $token = null;
                $uniqueToken = null;
            } else {
                $token = $i;
                $uniqueToken = \uniqid();
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
                if (!$worker->isRunning()) {
                    unset($this->workers[$key]);
                }
            }
            \usleep(10000);
            $this->printOutput();
        } while (\count($this->workers) > 0);
    }

    /**
     * Initialize test queue table.
     *
     * @throws Exception
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
            throw new Exception('Error while creating sqlite database table: ' . $this->db->errorCode());
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
                    ':command' => $test->command($this->options->phpunit, $this->options->filtered),
                    ':fileName' => $fileName,
                ]);
        }
    }

    /**
     * Loop through all completed tests and print their output.
     */
    private function printOutput(): void
    {
        foreach ($this->db->query('SELECT id, file_name FROM tests WHERE completed = 1')->fetchAll() as $test) {
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
        if ($this->db->query('SELECT COUNT(id) FROM tests')->fetchColumn(0) === '0') {
            return;
        }

        throw new RuntimeException(
            'Some workers have crashed.' . PHP_EOL
            . '----------------------' . PHP_EOL
            . 'All workers have quit, but some tests are still to be executed.' . PHP_EOL
            . 'This may be the case if some tests were killed forcefully (for example, using exit()).' . PHP_EOL
            . '----------------------' . PHP_EOL
            . 'Failed test command(s):' . PHP_EOL
            . '----------------------' . PHP_EOL
            . \implode(PHP_EOL, $this->db->query('SELECT command FROM tests')->fetchAll(PDO::FETCH_COLUMN))
        );
    }
}
