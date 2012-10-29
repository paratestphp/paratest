<?php namespace ParaTest\UI\Text;

class PHPUnitTextUI
{
    protected $options = array(
        'processes:' => array(
            'args' => '<number>',
            'message' => 'The number of phpunit processes to run. Defaults to 5.'
        ),
        'path:' => array(
            'args' => '<file|directory>',
            'message' => 'The path to a directory or file containing tests. Default to current working directory.'
        ),
        'phpunit:' => array(
            'args' => '<path>',
            'message' => 'The phpunit binary to execute. Defaults to just "phpunit".'
        ),
        'bootstrap:' => array(
            'args' => '<file>',
            'message' => 'A bootstrap file to be used by phpunit.'
        ),
        'functional' => array(
            'message' => 'Run test methods in separate processes, rather than suites.'
        ),
        'help' => array(
            'alias' => 'h',
            'message' => 'Print usage information.'
        )
    );

    private $padTo;

    public function __construct()
    {
        $this->padTo = $this->getPadTo();
    }

    public function getUsage()
    {
        return $this->getBasicUsage() . $this->getArgumentUsage();
    }

    public function getBasicUsage()
    {
        return "\nUsage: paratest [switches]\n";
    }

    public function getArgumentUsage()
    {
        $usage = "";
        foreach($this->options as $key => $info) {
            $cmd = $this->getCmd($key, $info);
            $arg = $this->getArg($info);
            $usage .= sprintf("\n%s%s%s", $cmd, $arg, 
                              $this->getMessage($cmd . $arg, $info['message']));
        }
        return $usage;
    }

    private function getCmd($key, $info)
    {
        return sprintf("%s--%s",
            (isset($info['alias'])) ? sprintf('-%s|', $info['alias']) : '',
            preg_replace('/:$/', '', $key)
        );
    }

    private function getArg($info)
    {
        return isset($info['args']) ? ' ' . $info['args'] : '';
    }

    private function getMessage($cmdArg, $msg)
    {
        $len = strlen($cmdArg);
        while($len++ < $this->padTo)
            $msg = " " . $msg;
        return $msg;
    }

    private function getPadTo() {
        $len = 0;
        foreach($this->options as $key => $info) {
            $str = $this->getCmd($key, $info) . $this->getArg($info);
            if(strlen($str) > $len) $len = strlen($str);
        }
        return $len + 2;
    }
}