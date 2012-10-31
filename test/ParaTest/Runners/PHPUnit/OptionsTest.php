<?php namespace ParaTest\Runners\PHPUnit;

class OptionsTest extends \TestBase
{
    public function testFilteredOptionsShouldContainExtraneousOptions()
    {
        $unfiltered = array(
            'processes' => 5,
            'path' => '/path/to/tests',
            'phpunit' => 'phpunit',
            'functional' => true,
            'group' => 'group1',
            'bootstrap' => '/path/to/bootstrap'
        );
        $options = new Options($unfiltered);
        $this->assertEquals('group1', $options->filtered['group']);
        $this->assertEquals('/path/to/bootstrap', $options->filtered['bootstrap']);
    }
}