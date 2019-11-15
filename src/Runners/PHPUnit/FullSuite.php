<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

class FullSuite extends ExecutableTest
{
    /**
     * @var string
     */
    protected $suiteName;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * @param string $suiteName
     * @param string $configPath
     */
    public function __construct($suiteName, $configPath)
    {
        parent::__construct('');

        $this->suiteName = $suiteName;
        $this->configPath = $configPath;
    }

    /**
     * {@inheritdoc}
     */
    protected function getCommandString(string $binary, array $options = [], ?string $passthru = null)
    {
        return parent::getCommandString(
            $binary,
            \array_merge(
                $options,
                [
                    'configuration' => $this->configPath,
                    'testsuite' => $this->suiteName,
                ]
            ),
            $passthru
        );
    }

    public function getTestCount(): int
    {
        return 1; //There is no simple way of knowing this
    }
}
