<?php namespace ParaTest\UI\Text;

use ParaTest\Runners\PHPUnit\Runner;

class PHPUnitTextUI
{
    protected $options = array(
        'processes:' => array(
            'args' => '<number>',
            'message' => 'The number of phpunit processes to run.'
        ),
        'path:' => array(
            'args' => '<file|directory>',
            'message' => 'The path to a directory or file containing tests.'
        ),
        'phpunit:' => array(
            'args' => '<path>',
            'message' => 'The phpunit binary to execute.'
        ),
        'bootstrap:' => array(
            'args' => '<file>',
            'message' => 'A bootstrap file to be used by phpunit.'
        ),
        'functional' => array(
            'message' => 'Run methods instead of suites in separate processes.'
        ),
        'help' => array(
            'alias' => 'h',
            'message' => 'Print usage information.'
        ),
        'group:' => array(
            'args' => '...',
            'message' => 'Only runs tests from the specified group(s).'
        )
    );

    private $padTo;

    public static function main()
    {
        $ui = new PHPUnitTextUI();
        $opts = $ui->getOptions();
        if(isset($opts['bootstrap']) && file_exists($opts['bootstrap']))
            require_once $opts['bootstrap'];
        if(empty($opts) || isset($opts['h']) || isset($opts['help'])) {
            print $ui->getUsage();
        } else {
            $runner = new Runner($opts);
            $runner->run();
        }
    }

    public function __construct()
    {
        $this->padTo = $this->getPadTo();
    }

    public function getOptions()
    {
        $opts = getopt($this->getAliasString(), array_keys($this->options));
        if(isset($opts['functional']))
            $opts['functional'] = true;
        return $opts;
    }

    public function getUsage()
    {
        return $this->getBasicUsage() . $this->getArgumentUsage() . "\n";
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
            $usage .= sprintf("\n  %s%s%s", $cmd, $arg, 
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

    private function getAliasString()
    {
        $alias = '';
        foreach($this->options as $key => $info)
            $alias .= isset($info['alias']) ? $info['alias'] : '';
        return $alias;
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