<?php


namespace ParaTest\Runners\PHPUnit;

use Symfony\Component\Process\ProcessBuilder;

class FullSuite extends ExecutableTest
{
    /**
     * @var  string
     */
    protected $suiteName;

    /**
     * @param string $suiteName
     * @param string $configPath
     */
    public function __construct($suiteName, $configPath)
    {
        parent::__construct($suiteName);
        $this->suiteName = $suiteName;
        $this->configPath = $configPath;
    }


    protected function getCommandString($binary, $options = array())
    {
        $builder = new ProcessBuilder();
        $builder->setPrefix($binary);
        foreach ($options as $key => $value) {
            $builder->add("--$key");
            if ($value !== null) {
                $builder->add($value);
            }
        }

        $builder->add('-c');
        $builder->add($this->configPath);

        $builder->add('--testsuite');
        $builder->add($this->suiteName);

        $process = $builder->getProcess();
        return $process->getCommandLine();
    }

    public function getTestCount()
    {
        return 1; //There is no simple way of knowing this
    }
}