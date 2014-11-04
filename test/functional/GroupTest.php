<?php
class GroupTest extends FunctionalTestBase
{
    /** @var ParatestInvoker */
    private $invoker;

    public function setUp()
    {
        parent::setUp();
        $this->invoker = new ParaTestInvoker(
            $this->fixture('passing-tests/GroupsTest.php'),
            BOOTSTRAP
        );
    }

    public function testGroupSwitchOnlyExecutesThoseGroups()
    {
        $proc = $this->invoker->execute(array('group' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $proc->getOutput());
    }

    public function testExcludeGroupSwitchDontExecuteThatGroup()
    {
        $proc = $this->invoker->execute(array('exclude-group' => 'group1'));

        $this->assertRegExp('/OK \(3 tests, 3 assertions\)/', $proc->getOutput());
    }

    public function testGroupSwitchExecutesGroupsUsingShortOption()
    {
        $proc = $this->invoker->execute(array('g' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $proc->getOutput());
    }

    public function testGroupSwitchOnlyExecutesThoseGroupsInFunctionalMode()
    {
        $proc = $this->invoker->execute(array('functional', 'g' => 'group1'));
        $this->assertRegExp('/OK \(2 tests, 2 assertions\)/', $proc->getOutput());
    }

    public function testGroupSwitchOnlyExecutesThoseGroupsWhereTestHasMultipleGroups()
    {
        $proc = $this->invoker->execute(array('functional', 'group' => 'group3'));
        $this->assertRegExp('/OK \(1 test, 1 assertion\)/', $proc->getOutput());
    }

    public function testGroupsSwitchExecutesMultipleGroups()
    {
        $proc = $this->invoker->execute(array('functional', 'group' => 'group1,group3'));
        $this->assertRegExp('/OK \(3 tests, 3 assertions\)/', $proc->getOutput());
    }
}
