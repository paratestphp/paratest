<?php namespace ParaTest\UI\Text;

class PHPUnitTextUITest extends \TestBase
{
    protected $ui;

    public function setUp()
    {
        $this->ui = new PHPUnitTextUI();
    }

    public function testUIShouldHaveOptionsAsKeys()
    {
        $optionKeys = array(
          'processes:',
          'path:',
          'phpunit:',
          'bootstrap:',
          'functional',
          'help',
          'group:'
        );
        $options = $this->getObjectValue($this->ui, 'options');
        $this->assertEquals($optionKeys, array_keys($options));
        return $options;
    }

    /**
     * @depends testUIShouldHaveOptionsAsKeys
     */
    public function testOptionsShouldHaveUsageMessagesForValues($options)
    {
        $usageMessages = array(
            'The number of phpunit processes to run.',
            'The path to a directory or file containing tests.',
            'The phpunit binary to execute.',
            'A bootstrap file to be used by phpunit.',
            'Run methods instead of suites in separate processes.',
            'Print usage information.',
            'Only runs tests from the specified group(s).'
        );
        $i = 0;
        foreach($options as $options => $info) {
            $usage = $usageMessages[$i];
            $this->assertEquals($usage, $info['message']);
            $i++;
        }
        return $usageMessages;
    }

    /**
     * @depends testUIShouldHaveOptionsAsKeys
     */
    public function testOptionsShouldHaveArgumentInformationForValues($options)
    {
        $args = array(
            '<number>',
            '<file|directory>',
            '<path>',
            '<file>',
            '...'
        );
        $keys = array('processes:', 'path:', 'phpunit:', 'bootstrap:', 'group:');
        foreach($keys as $key) {
            $arg = array_shift($args);
            $this->assertEquals($arg, $options[$key]['args']);
        }
    }

    /**
     * @depends testUIShouldHaveOptionsAsKeys
     */
    public function testOptionAlias($options)
    {
        $help = $options['help'];
        $this->assertEquals('h', $help['alias']);
    }

    public function testGetBasicUsage()
    {
        $usage = "\nUsage: paratest [switches]\n";
        $this->assertEquals($usage, $this->ui->getBasicUsage());
    }

    /**
     * @depends testOptionsShouldHaveUsageMessagesForValues
     */
    public function testGetArgumentUsage($messages)
    {
        $usage  = sprintf("\n  --processes <number>     %s", array_shift($messages));
        $usage .= sprintf("\n  --path <file|directory>  %s", array_shift($messages));
        $usage .= sprintf("\n  --phpunit <path>         %s", array_shift($messages));
        $usage .= sprintf("\n  --bootstrap <file>       %s", array_shift($messages));
        $usage .= sprintf("\n  --functional             %s", array_shift($messages));
        $usage .= sprintf("\n  -h|--help                %s", array_shift($messages));
        $usage .= sprintf("\n  --group ...              %s", array_shift($messages));

        $this->assertEquals($usage, $this->ui->getArgumentUsage());
        return $usage;
    }

    /**
     * @depends testGetArgumentUsage
     */
    public function testGetUsage($argUsage)
    {
        $usage  = $this->ui->getBasicUsage();
        $usage .= $argUsage . "\n";
        $this->assertEquals($usage, $this->ui->getUsage());
    }
}