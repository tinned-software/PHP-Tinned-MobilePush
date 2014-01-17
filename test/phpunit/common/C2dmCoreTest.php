<?php
/**
 * PHPUnit test cases to accompany the C2DM class
 */



require_once(dirname(__FILE__).'/../../../classes/c2dm.class.php');



/**
 * Class to test the core functionality of the C2DM class
 */
class C2dmCoreTest extends PHPUnit_Framework_TestCase
{

	/**
	 * Create and check inheritance and of the C2DM class
	 * @return void
	 */
	public function testCreateObject()
	{
		$class = new C2DM();
        $this->assertInstanceOf('C2DM', $class, 'object does not report to be an C2DM class');
		$this->assertInstanceOf('Mobile_Push', $class, 'object does not report to be a Mobile_Push interface');

	}


}
?>