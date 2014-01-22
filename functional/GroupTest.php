<?php
class GroupTest extends FunctionalTestBase
{
    public function setUp()
    {
        parent::setUp();
        $this->path = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
    }

    public function testGroupSwitchOnlyExecutesThoseGroups()
    {
        $output = $this->getParaTestOutput(false, array('group' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $output);
    }

    public function testExcludeGroupSwitchDontExecuteThatGroup()
    {
        $output = $this->getParaTestOutput(false, array('exclude-group' => 'group1'));
        $this->assertRegExp('/OK \(3 tests, 3 assertions\)/', $output);
    }

    public function testGroupSwitchExecutesGroupsUsingShortOption()
    {
        $output = $this->getParaTestOutput(false, array('g' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $output);
    }

    public function testGroupSwitchOnlyExecutesThoseGroupsInFunctionalMode()
    {
        $output = $this->getParaTestOutput(true, array('group' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $output);
    }

    public function testGroupSwitchOnlyExecutesThoseGroupsWhereTestHasMultipleGroups()
    {
        $output = $this->getParaTestOutput(true, array('group' => 'group3'));
        $this->assertRegExp('/OK \(1 test, 1 assertion\)/', $output);
    }

    public function testGroupsSwitchExecutesMultipleGroups()
    {
        $output = $this->getParaTestOutput(true, array('group' => 'group1,group3'));
        $this->assertRegExp('/OK \(3 tests, 3 assertions\)/', $output);
    }
}