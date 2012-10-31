<?php namespace ParaTest\Runners\PHPUnit;

class OptionsTest extends \TestBase
{
    protected $options;

    public function setUp()
    {
        $unfiltered = array(
            'processes' => 5,
            'path' => '/path/to/tests',
            'phpunit' => 'phpunit',
            'functional' => true,
            'group' => 'group1',
            'bootstrap' => '/path/to/bootstrap'
        );
        $this->options = new Options($unfiltered);
    }

    public function testFilteredOptionsShouldContainExtraneousOptions()
    {
        $this->assertEquals('group1', $this->options->filtered['group']);
        $this->assertEquals('/path/to/bootstrap', $this->options->filtered['bootstrap']);
    }

    public function testAnnotationsReturnsAnnotations()
    {
        $this->assertEquals(1, sizeof($this->options->annotations));
        $this->assertEquals('group1', $this->options->annotations['group']);
    }

    public function testAnnotationsDefaultsToEmptyArray()
    {
        $options = new Options(array());
        $this->assertEmpty($options->annotations);
    }
}