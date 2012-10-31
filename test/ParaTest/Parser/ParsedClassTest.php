<?php namespace ParaTest\Parser;

class ParsedClassTest extends \TestBase
{
    protected $class;
    protected $methods;

    public function setUp()
    {
        $this->methods = array(
            new ParsedFunction(
            "/**
              * @group group1
              */",
             'public', 'testFunction'
            ),
            new ParsedFunction(
            "/**
              * @group group2
              */",
             'public', 'testFunction2'
            ),
            new ParsedFunction('', 'public', 'testFunction3')
        );
        $this->class = new ParsedClass('', 'MyTestClass', '', $this->methods);
    }

    public function testGetMethodsReturnsMethods()
    {
        $this->assertEquals($this->methods, $this->class->getMethods());
    }

    public function testGetMethodsExceptsAdditionalAnnotationFilter()
    {
        $group1 = $this->class->getMethods(array('group' => 'group1'));
        $this->assertEquals(1, sizeof($group1));
        $this->assertEquals($this->methods[0], $group1[0]);
    }
}