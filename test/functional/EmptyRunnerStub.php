<?php
/**
 * EmptyRunnerStub.php
 *
 * @package    Atlas
 * @author     Christopher Biel <christopher.biel@jungheinrich.de>
 * @copyright  2013 Jungheinrich AG
 * @license    Proprietary license
 * @version    $Id$
 */
class EmptyRunnerStub extends \ParaTest\Runners\PHPUnit\BaseRunner
{
    public function run()
    {
        echo "EXECUTED";
    }
}