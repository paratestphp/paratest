<?php
class GroupTest extends FunctionalTestBase
{
    public function testGroupSwitchOnlyExecutesThoseGroups()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
        $output = $this->getParaTestOutput(false, array('group' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $output);
    }

    public function testGroupSwitchOnlyExecutesThoseGroupsInFunctionalMode()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
        $output = $this->getParaTestOutput(true, array('group' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $output);
    }

    public function testGroupSwitchOnlyExecutesThoseGroupsWhereTestHasMultipleGroups()
    {
        $this->path = FIXTURES . DS . 'tests' . DS . 'GroupsTest.php';
        $output = $this->getParaTestOutput(true, array('group' => 'group3'));
        $this->assertRegExp('/OK \(1 test, 1 assertion\)/', $output);
    }
}